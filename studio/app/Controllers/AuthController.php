<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditAction;
use App\Services\AuditService;
use App\Services\RateLimiter;
use App\Validators\LoginRequest;
use App\Validators\PasswordChangeRequest;

/**
 * Administrator authentication.
 */
final class AuthController extends Controller
{
    public function __construct(
        private RateLimiter $limiter = new RateLimiter(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/login */
    public function showLogin(Request $request): Response
    {
        return $this->view('admin.login', ['title' => 'Connexion']);
    }

    /** POST /admin/login */
    public function login(Request $request): Response
    {
        $form = (new LoginRequest())->validate($request);

        if ($form->fails()) {
            return $this->loginFailure($request, $form->errors());
        }

        $email = (string) $form->value('email');
        $password = (string) $form->value('password');

        // Throttle per e-mail *and* per IP. Per-IP alone lets an attacker
        // spread one password across many accounts; per-e-mail alone lets
        // them lock a known account out on purpose.
        $emailKey = 'login|email|' . $email;
        $ipKey = 'login|ip|' . $request->ip();
        $maxAttempts = (int) config('security.login_max_attempts', 5);
        $decay = (int) config('security.login_decay_seconds', 900);

        foreach ([$emailKey, $ipKey] as $key) {
            if ($this->limiter->tooManyAttempts($key, $key === $ipKey ? $maxAttempts * 4 : $maxAttempts)) {
                $this->audit->record(AuditAction::LOGIN_FAILED, $request, null, null, ['reason' => 'throttled']);

                return $this->loginFailure($request, [
                    'email' => 'Trop de tentatives. ' . $this->limiter->retryMessage($key),
                ]);
            }
        }

        $user = Auth::attempt($email, $password);

        if ($user === null) {
            $this->limiter->hit($emailKey, $decay);
            $this->limiter->hit($ipKey, $decay);

            // One message for every failure mode: a distinct "unknown
            // account" reply would confirm which addresses exist.
            $this->audit->record(AuditAction::LOGIN_FAILED, $request, null, null, ['email' => $email]);

            return $this->loginFailure($request, ['email' => 'Identifiants incorrects.']);
        }

        $this->limiter->clear($emailKey);
        $this->limiter->clear($ipKey);

        Auth::login($user, $request);

        $this->audit->record(AuditAction::LOGIN_SUCCESS, $request, null, null, [], (int) $user['id']);

        $intended = Session::get('_intended_url');
        Session::forget('_intended_url');

        $target = is_string($intended) && str_starts_with($intended, '/admin') ? ltrim($intended, '/') : 'admin';

        return $this->redirect($target);
    }

    /** POST /admin/logout */
    public function logout(Request $request): Response
    {
        $userId = Auth::id();
        $this->audit->record(AuditAction::LOGOUT, $request, null, null, [], $userId);

        Auth::logout();
        $this->flashSuccess('Vous êtes déconnecté.');

        return $this->redirect('admin/login');
    }

    /** GET /admin/profile */
    public function profile(Request $request): Response
    {
        return $this->view('admin.profile', ['title' => 'Mon compte']);
    }

    /** POST /admin/profile/password */
    public function updatePassword(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->redirect('admin/login');
        }

        $form = (new PasswordChangeRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/profile');
        }

        if (!password_verify((string) $form->value('current_password'), (string) $user['password_hash'])) {
            return $this->redirectWithErrors(
                $request,
                ['current_password' => 'Mot de passe actuel incorrect.'],
                'admin/profile'
            );
        }

        (new \App\Repositories\UserRepository())->updatePassword(
            (int) $user['id'],
            (string) $form->value('password')
        );

        // Changing a password invalidates any other session riding the old
        // one; the safest way to guarantee that is to start fresh here.
        Auth::logout();
        $this->audit->record(AuditAction::PASSWORD_CHANGED, $request, null, null, [], (int) $user['id']);
        $this->flashSuccess('Mot de passe modifié. Reconnectez-vous.');

        return $this->redirect('admin/login');
    }

    /**
     * Send the visitor back to the login form with the error.
     *
     * 303 so that a refresh re-issues the GET rather than re-posting the
     * credentials.
     *
     * @param array<string, string> $errors
     */
    private function loginFailure(Request $request, array $errors): Response
    {
        Session::flashErrors($errors);
        Session::flashInput(['email' => $request->input('email')]);

        return $this->redirect('admin/login', 303);
    }
}
