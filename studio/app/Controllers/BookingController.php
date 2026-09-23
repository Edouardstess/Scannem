<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\BookingRepository;
use App\Repositories\ServiceRepository;
use App\Services\MailService;
use App\Services\RateLimiter;
use App\Services\SettingsService;
use App\Validators\BookingRequest;

/**
 * Session booking requests.
 *
 * Version 1 records the request and notifies the photographer. The table and
 * this controller are shaped so that a calendar, availability checks and a
 * deposit can be added later without changing what a request means.
 */
final class BookingController extends Controller
{
    private const HONEYPOT_FIELD = 'website';

    public function __construct(
        private BookingRepository $bookings = new BookingRepository(),
        private ServiceRepository $services = new ServiceRepository(),
        private RateLimiter $limiter = new RateLimiter(),
        private MailService $mail = new MailService(),
        private SettingsService $settings = new SettingsService()
    ) {
    }

    /** GET /reservation */
    public function show(Request $request): Response
    {
        if (!$this->settings->get('booking_enabled', true)) {
            $this->abort(404, 'La réservation en ligne est désactivée.');
        }

        return $this->view('public.booking', [
            'title'       => 'Réserver une séance',
            'services'    => $this->services->published(),
            'preselected' => $request->int('service', 0),
        ]);
    }

    /** POST /reservation */
    public function submit(Request $request): Response
    {
        if (!$this->settings->get('booking_enabled', true)) {
            $this->abort(404, 'La réservation en ligne est désactivée.');
        }

        if (trim((string) $request->input(self::HONEYPOT_FIELD, '')) !== '') {
            return $this->redirect('reservation?sent=1');
        }

        $limiterKey = 'booking|' . $request->ip();

        if ($this->limiter->tooManyAttempts($limiterKey, (int) config('security.contact_max_per_hour', 5))) {
            return $this->redirectWithErrors($request, [
                'message' => 'Trop de demandes envoyées. ' . $this->limiter->retryMessage($limiterKey),
            ], 'reservation');
        }

        $form = (new BookingRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'reservation');
        }

        $data = $form->data();
        $this->limiter->hit($limiterKey, 3600);

        $this->bookings->insert($data + [
            'status'     => 'pending',
            'ip_address' => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $recipient = trim((string) $this->settings->get('contact_email', ''));

        if ($recipient !== '') {
            $this->mail->notifyNewMessage($recipient, [
                'name'    => $data['name'],
                'email'   => $data['email'],
                'message' => sprintf(
                    "Demande de réservation.\nPrestation : %s\nDate souhaitée : %s\nLieu : %s\n\n%s",
                    $data['service_label'] ?? 'non précisée',
                    $data['preferred_date'] === null ? 'non précisée' : format_date((string) $data['preferred_date']),
                    $data['location'] ?? 'non précisé',
                    $data['message'] ?? ''
                ),
            ]);
        }

        $this->flashSuccess('Votre demande est enregistrée. Nous revenons vers vous très vite.');

        return $this->redirect('reservation?sent=1');
    }
}
