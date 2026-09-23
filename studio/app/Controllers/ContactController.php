<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\MessageRepository;
use App\Repositories\ServiceRepository;
use App\Services\MailService;
use App\Services\RateLimiter;
use App\Services\SettingsService;
use App\Validators\ContactRequest;

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

    /**
     * Fold the optional fields into the notification body.
     *
     * The photographer reads one e-mail; a desired date buried in a column
     * they never see is a date they will miss.
     *
     * @param array<string, mixed> $data
     */
    private function mailBody(array $data): string
    {
        $lines = [(string) $data['message']];

        if (($data['subject'] ?? null) !== null) {
            $lines[] = 'Type de séance : ' . (string) $data['subject'];
        }

        if (($data['preferred_date'] ?? null) !== null) {
            $lines[] = 'Date souhaitée : ' . format_date((string) $data['preferred_date']);
        }

        if (($data['phone'] ?? null) !== null) {
            $lines[] = 'Téléphone : ' . (string) $data['phone'];
        }

        return implode("\n\n", $lines);
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

        $form = (new ContactRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'contact');
        }

        $data = $form->data();
        $this->limiter->hit($limiterKey, 3600);

        $this->messages->insert($data + [
            'status'     => 'new',
            'ip_address' => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $recipient = trim((string) $this->settings->get('contact_email', ''));

        if ($recipient !== '') {
            $this->mail->notifyNewMessage($recipient, [
                'name'    => $data['name'],
                'email'   => $data['email'],
                'message' => $this->mailBody($data),
            ]);
        }

        $this->flashSuccess('Merci, votre message a bien été envoyé. Nous vous répondrons rapidement.');

        return $this->redirect('contact?sent=1');
    }
}
