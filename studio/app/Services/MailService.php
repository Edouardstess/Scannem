<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Environment;
use App\Core\Logger;

/**
 * Outgoing mail.
 *
 * Three drivers: 'log' (default, writes to storage/logs so a fresh install
 * never fails at sending), 'mail' (PHP's mail(), which is what most shared
 * hosting offers), and 'smtp' (a minimal SMTP client, for hosts where mail()
 * is disabled or unreliable).
 *
 * Controllers never call mail() directly; they call this, so that changing
 * transport is a .env edit.
 */
final class MailService
{
    public function __construct(private ?SettingsService $settings = null)
    {
        $this->settings = $settings ?? new SettingsService();
    }

    /**
     * Send a message. Never throws: a failed notification must not break the
     * action that triggered it.
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        $to = trim($to);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('Mail not sent: invalid recipient');

            return false;
        }

        $driver = (string) Config::get('mail.driver', 'log');

        try {
            return match ($driver) {
                'smtp'  => $this->sendSmtp($to, $subject, $htmlBody, $replyTo),
                'mail'  => $this->sendNative($to, $subject, $htmlBody, $replyTo),
                default => $this->sendToLog($to, $subject, $htmlBody),
            };
        } catch (\Throwable $e) {
            Logger::error('Mail sending failed', ['driver' => $driver, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendToLog(string $to, string $subject, string $body): bool
    {
        Logger::info('Mail (log driver)', [
            'to'      => $to,
            'subject' => $subject,
            'body'    => mb_substr(strip_tags($body), 0, 500),
        ]);

        return true;
    }

    private function sendNative(string $to, string $subject, string $body, ?string $replyTo): bool
    {
        $headers = implode("\r\n", $this->headerLines($replyTo));

        if (!Environment::functionAvailable('mail')) {
            Logger::error('mail() is disabled on this server; set MAIL_DRIVER=smtp or share links by hand');

            return false;
        }

        return @mail($to, $this->encodeSubject($subject), $body, $headers);
    }

    /** @return array<int, string> */
    private function headerLines(?string $replyTo): array
    {
        $fromAddress = (string) Config::get('mail.from_address');
        $fromName = (string) Config::get('mail.from_name');

        // Header injection defence: a newline in either value would let a
        // caller append arbitrary headers such as Bcc.
        $fromName = str_replace(["\r", "\n"], '', $fromName);
        $fromAddress = str_replace(["\r", "\n"], '', $fromAddress);

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->encodeHeaderWord($fromName) . ' <' . $fromAddress . '>',
        ];

        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return $headers;
    }

    private function encodeSubject(string $subject): string
    {
        $subject = str_replace(["\r", "\n"], '', $subject);

        return $this->encodeHeaderWord($subject);
    }

    /** RFC 2047 encoding, so accented subjects survive the transport. */
    private function encodeHeaderWord(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * Minimal SMTP delivery over a socket.
     *
     * Deliberately small: AUTH LOGIN and STARTTLS cover what shared hosting
     * and the common transactional providers accept. Anything more exotic is
     * a reason to reach for a library, not to grow this.
     */
    private function sendSmtp(string $to, string $subject, string $body, ?string $replyTo): bool
    {
        $host = (string) Config::get('mail.host');
        $port = (int) Config::get('mail.port', 587);
        $timeout = (int) Config::get('mail.timeout', 15);
        $encryption = strtolower((string) Config::get('mail.encryption', 'tls'));

        if ($host === '') {
            Logger::warning('SMTP driver selected but MAIL_HOST is empty');

            return false;
        }

        $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        if (!Environment::functionAvailable('fsockopen')) {
            Logger::error('fsockopen() is disabled on this server; SMTP is unavailable');

            return false;
        }

        $socket = @fsockopen($transport, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            Logger::error('SMTP connection failed', ['host' => $host, 'port' => $port, 'error' => $errstr]);

            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            if (!$this->smtpExpect($socket, '220')) {
                return false;
            }

            $hostname = (string) (parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost');

            $this->smtpWrite($socket, 'EHLO ' . $hostname);

            if (!$this->smtpExpect($socket, '250')) {
                return false;
            }

            if ($encryption === 'tls') {
                $this->smtpWrite($socket, 'STARTTLS');

                if (!$this->smtpExpect($socket, '220')) {
                    return false;
                }

                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    Logger::error('SMTP STARTTLS negotiation failed');

                    return false;
                }

                $this->smtpWrite($socket, 'EHLO ' . $hostname);

                if (!$this->smtpExpect($socket, '250')) {
                    return false;
                }
            }

            $username = (string) Config::get('mail.username');
            $password = (string) Config::get('mail.password');

            if ($username !== '') {
                $this->smtpWrite($socket, 'AUTH LOGIN');

                if (!$this->smtpExpect($socket, '334')) {
                    return false;
                }

                $this->smtpWrite($socket, base64_encode($username));

                if (!$this->smtpExpect($socket, '334')) {
                    return false;
                }

                $this->smtpWrite($socket, base64_encode($password));

                if (!$this->smtpExpect($socket, '235')) {
                    Logger::error('SMTP authentication rejected');

                    return false;
                }
            }

            $from = (string) Config::get('mail.from_address');

            $this->smtpWrite($socket, 'MAIL FROM:<' . $from . '>');

            if (!$this->smtpExpect($socket, '250')) {
                return false;
            }

            $this->smtpWrite($socket, 'RCPT TO:<' . $to . '>');

            if (!$this->smtpExpect($socket, ['250', '251'])) {
                return false;
            }

            $this->smtpWrite($socket, 'DATA');

            if (!$this->smtpExpect($socket, '354')) {
                return false;
            }

            $message = implode("\r\n", array_merge(
                ['To: ' . $to, 'Subject: ' . $this->encodeSubject($subject)],
                $this->headerLines($replyTo),
                ['', $this->stuffDots($body), '.']
            ));

            $this->smtpWrite($socket, $message);

            if (!$this->smtpExpect($socket, '250')) {
                return false;
            }

            $this->smtpWrite($socket, 'QUIT');

            return true;
        } finally {
            @fclose($socket);
        }
    }

    /** @param resource $socket */
    private function smtpWrite($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    /**
     * @param resource              $socket
     * @param string|array<int,string> $expected
     */
    private function smtpExpect($socket, string|array $expected): bool
    {
        $expected = (array) $expected;
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            // A multi-line reply uses "250-" for every line but the last.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = substr(trim($response), 0, 3);

        if (in_array($code, $expected, true)) {
            return true;
        }

        Logger::warning('Unexpected SMTP reply', ['expected' => $expected, 'got' => trim($response)]);

        return false;
    }

    /** A line consisting of a single dot would end the DATA section early. */
    private function stuffDots(string $body): string
    {
        $body = str_replace("\r\n", "\n", $body);
        $body = str_replace("\n", "\r\n", $body);

        return (string) preg_replace('/^\./m', '..', $body);
    }

    // --- Notification templates -------------------------------------------

    /** @param array<string, mixed> $gallery */
    public function notifyGalleryReady(string $recipient, array $gallery, string $viewUrl): bool
    {
        $studio = (string) $this->settings->get('studio_name', 'L\'ENFANT VISUAL');
        $title = (string) $gallery['title'];

        $body = $this->wrap(
            'Vos photos sont prêtes',
            '<p>Bonjour,</p>'
            . '<p>Votre galerie <strong>' . e($title) . '</strong> est disponible.</p>'
            . '<p><a href="' . e($viewUrl) . '" style="display:inline-block;padding:12px 22px;'
            . 'background:#1a1a1a;color:#ffffff;text-decoration:none;border-radius:2px;">Voir mes photos</a></p>'
            . '<p style="font-size:13px;color:#666;">Ou copiez ce lien : ' . e($viewUrl) . '</p>',
            $studio
        );

        return $this->send($recipient, $studio . ' — vos photos sont prêtes', $body);
    }

    /** @param array<string, mixed> $gallery */
    public function notifyDownloadAvailable(string $recipient, array $gallery, string $downloadUrl): bool
    {
        $studio = (string) $this->settings->get('studio_name', 'L\'ENFANT VISUAL');

        $body = $this->wrap(
            'Vos photos sont téléchargeables',
            '<p>Bonjour,</p>'
            . '<p>Le téléchargement de la galerie <strong>' . e((string) $gallery['title']) . '</strong> est ouvert.</p>'
            . '<p><a href="' . e($downloadUrl) . '" style="display:inline-block;padding:12px 22px;'
            . 'background:#1a1a1a;color:#ffffff;text-decoration:none;border-radius:2px;">Télécharger</a></p>',
            $studio
        );

        return $this->send($recipient, $studio . ' — téléchargement disponible', $body);
    }

    /** @param array<string, mixed> $message */
    public function notifyNewMessage(string $recipient, array $message): bool
    {
        $studio = (string) $this->settings->get('studio_name', 'L\'ENFANT VISUAL');

        $body = $this->wrap(
            'Nouveau message',
            '<p><strong>' . e((string) $message['name']) . '</strong> (' . e((string) $message['email']) . ')</p>'
            . '<p>' . nl2br(e((string) $message['message'])) . '</p>',
            $studio
        );

        return $this->send(
            $recipient,
            'Nouveau message via le site',
            $body,
            is_string($message['email'] ?? null) ? $message['email'] : null
        );
    }

    private function wrap(string $heading, string $content, string $studio): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:24px;background:#fbfaf8;font-family:Helvetica,Arial,sans-serif;color:#1a1a1a;">'
            . '<div style="max-width:560px;margin:0 auto;background:#ffffff;padding:32px;">'
            . '<h1 style="font-size:20px;margin:0 0 20px;font-weight:600;">' . e($heading) . '</h1>'
            . $content
            . '<p style="margin-top:32px;font-size:12px;color:#999;">' . e($studio) . '</p>'
            . '</div></body></html>';
    }
}
