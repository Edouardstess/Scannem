<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\MessageRepository;
use App\Repositories\ServiceRepository;
use App\Services\MailService;
use App\Services\RateLimiter;
use App\Services\SettingsService;

/**
 * Public contact form.
 *
 * Protected by CSRF (globally), server-side validation, a per-IP rate limit
 * and a honeypot field. No CAPTCHA: the combination above stops the volume a
 * photographer's site actually attracts, without sending visitors to a third
 * party or hurting accessibility.
 */
final class ContactController extends Controller
{
    private const HONEYPOT_FIELD = 'website';

    public function __construct(
        private MessageRepository $messages = new MessageRepository(),
        private ServiceRepository $services = new ServiceRepository(),
        private RateLimiter $limiter = new RateLimiter(),
        private MailService $mail = new MailService(),
        private SettingsService $settings = new SettingsService()
    ) {
    }

    /** GET /contact */
    public function show(Request $request): Response
    {
        return $this->view('public.contact', [
            'title'    => 'Contact',
            'services' => $this->services->published(),
        ]);
    }

    /** POST /contact */
    public function submit(Request $request): Response
    {
        // A bot fills every field it finds; a human never sees this one.
        // Answer with the success page anyway, so the bot learns nothing.
        if (trim((string) $request->input(self::HONEYPOT_FIELD, '')) !== '') {
            return $this->redirect('contact?sent=1');
        }

        $limiterKey = 'contact|' . $request->ip();
        $maxPerHour = (int) config('security.contact_max_per_hour', 5);

        if ($this->limiter->tooManyAttempts($limiterKey, $maxPerHour)) {
            return $this->redirectWithErrors($request, [
                'message' => 'Trop de messages envoyés. ' . $this->limiter->retryMessage($limiterKey),
            ], 'contact');
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|min:2|max:150',
            'email'   => 'required|email|max:190',
            'phone'   => 'nullable|phone',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string|min:10|max:5000',
        ], [], [
            'name'    => 'nom',
            'email'   => 'e-mail',
            'phone'   => 'téléphone',
            'subject' => 'sujet',
            'message' => 'message',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'contact');
        }

        $data = $validator->validated();
        $this->limiter->hit($limiterKey, 3600);

        $id = $this->messages->insert([
            'name'       => (string) $data['name'],
            'email'      => strtolower((string) $data['email']),
            'phone'      => $data['phone'] ?? null,
            'subject'    => $data['subject'] ?? null,
            'message'    => (string) $data['message'],
            'status'     => 'new',
            'ip_address' => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $recipient = trim((string) $this->settings->get('contact_email', ''));

        if ($recipient !== '') {
            $this->mail->notifyNewMessage($recipient, [
                'name'    => $data['name'],
                'email'   => $data['email'],
                'message' => $data['message'],
            ]);
        }

        unset($id);
        $this->flashSuccess('Merci, votre message a bien été envoyé. Nous vous répondrons rapidement.');

        return $this->redirect('contact?sent=1');
    }
}
