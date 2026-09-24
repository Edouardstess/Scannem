<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Request;
use App\Models\AuditAction;
use App\Repositories\UserRepository;

/**
 * Administrator password reset without SSH or e-mail.
 *
 * On shared hosting (ByetHost…) there is often no terminal, and outgoing mail
 * is unreliable, so a forgotten password would lock the photographer out for
 * good. Instead they upload, by FTP, a small text file into storage/private:
 *
 *     email=vous@exemple.com
 *     password=VotreNouveauMotDePasse
 *
 * The next visit to the login page applies it and deletes the file at once.
 * Only someone who can already write to the server can do this — the same
 * trust the web installer relies on — and the folder is never served over
 * HTTP, so the password cannot be read from outside.
 */
final class PasswordRecoveryService
{
    public const FILENAME = 'reset-password.txt';

    private const MIN_LENGTH = 10;

    public function __construct(
        private ?string $directory = null,
        private ?UserRepository $users = null,
        private ?RateLimiter $limiter = null,
        private ?AuditService $audit = null
    ) {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/storage/private';
        $this->users = $users ?? new UserRepository();
        $this->limiter = $limiter ?? new RateLimiter();
        $this->audit = $audit ?? new AuditService();
    }

    public function path(): string
    {
        return rtrim((string) $this->directory, '/') . '/' . self::FILENAME;
    }

    public function isPending(): bool
    {
        return is_file($this->path());
    }

    /**
     * Apply the pending reset, if any.
     *
     * The file is always deleted, even when it is wrong: it holds a password
     * in clear and must not outlive this request.
     *
     * @return array{ok: bool, message: string}|null  null when there is nothing to do
     */
    public function apply(Request $request): ?array
    {
        if (!$this->isPending()) {
            return null;
        }

        $content = (string) @file_get_contents($this->path(), false, null, 0, 4096);
        $deleted = $this->destroyFile();
        $result = $this->reset(self::parse($content), $request);

        if (!$deleted) {
            $result['message'] .= ' Attention : supprimez vous-même le fichier storage/private/'
                . self::FILENAME . ' par FTP, il n\'a pas pu être effacé.';
        }

        return $result;
    }

    /**
     * "key=value" or "key: value" lines; blank lines and # comments ignored.
     *
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $values = [];
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // Windows Notepad BOM

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([a-z_ ]+?)\s*[:=]\s*(.*)$/i', $line, $match) === 1) {
                $key = strtolower(str_replace(' ', '_', trim($match[1])));
                $values[$key === 'mot_de_passe' ? 'password' : $key] = trim($match[2]);
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     * @return array{ok: bool, message: string}
     */
    private function reset(array $values, Request $request): array
    {
        $email = strtolower(trim($values['email'] ?? ''));
        $password = $values['password'] ?? '';

        if ($email === '' || $password === '') {
            return $this->failure('Fichier de réinitialisation incomplet : il faut une ligne email=… et une ligne password=….');
        }

        if (mb_strlen($password) < self::MIN_LENGTH) {
            return $this->failure(sprintf('Mot de passe trop court : %d caractères minimum.', self::MIN_LENGTH));
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return $this->failure('Aucun compte administrateur avec cet e-mail : ' . $email . '.');
        }

        $this->users->updatePassword((int) $user['id'], $password);

        // The lockout that brought the photographer here goes with it.
        $this->limiter->clear('login|email|' . $email);
        $this->limiter->clear('login|ip|' . $request->ip());

        $this->audit->record(AuditAction::PASSWORD_CHANGED, $request, null, null, ['via' => 'recovery_file'], (int) $user['id']);
        Logger::warning('Administrator password reset through the recovery file', ['user_id' => (int) $user['id']]);

        return ['ok' => true, 'message' => 'Mot de passe réinitialisé pour ' . $email . '. Vous pouvez vous connecter.'];
    }

    /** @return array{ok: bool, message: string} */
    private function failure(string $message): array
    {
        Logger::warning('Password recovery file rejected', ['reason' => $message]);

        return ['ok' => false, 'message' => $message . ' Le fichier a été supprimé ; corrigez-le et déposez-le à nouveau.'];
    }

    private function destroyFile(): bool
    {
        $path = $this->path();
        $size = (int) @filesize($path);

        // Overwrite before unlinking, so the clear-text password does not
        // linger in a backup or in freed disk blocks.
        if ($size > 0) {
            @file_put_contents($path, str_repeat("\0", $size));
        }

        return @unlink($path) || !is_file($path);
    }
}
