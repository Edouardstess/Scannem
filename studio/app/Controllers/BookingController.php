<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\BookingRepository;
use App\Repositories\ServiceRepository;
use App\Services\MailService;
use App\Services\RateLimiter;
use App\Services\SettingsService;

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

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|min:2|max:150',
            'email'          => 'required|email|max:190',
            'phone'          => 'nullable|phone',
            'service_id'     => 'nullable|integer',
            'preferred_date' => 'nullable|date',
            'location'       => 'nullable|string|max:190',
            'message'        => 'nullable|string|max:5000',
        ], [], [
            'name'           => 'nom',
            'email'          => 'e-mail',
            'phone'          => 'téléphone',
            'preferred_date' => 'date souhaitée',
            'location'       => 'lieu',
            'message'        => 'message',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'reservation');
        }

        $data = $validator->validated();
        $this->limiter->hit($limiterKey, 3600);

        // The service is resolved against the database rather than trusted
        // from the form, so a tampered id cannot invent a prestation.
        $serviceId = isset($data['service_id']) ? (int) $data['service_id'] : 0;
        $service = $serviceId > 0 ? $this->services->find($serviceId) : null;

        $preferredDate = $data['preferred_date'] ?? null;

        $this->bookings->insert([
            'name'           => (string) $data['name'],
            'email'          => strtolower((string) $data['email']),
            'phone'          => $data['phone'] ?? null,
            'service_id'     => $service === null ? null : (int) $service['id'],
            'service_label'  => $service === null ? null : (string) $service['title'],
            'preferred_date' => $preferredDate === null ? null : date('Y-m-d', (int) strtotime((string) $preferredDate)),
            'location'       => $data['location'] ?? null,
            'message'        => $data['message'] ?? null,
            'status'         => 'pending',
            'ip_address'     => $request->ip(),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $recipient = trim((string) $this->settings->get('contact_email', ''));

        if ($recipient !== '') {
            $this->mail->notifyNewMessage($recipient, [
                'name'    => $data['name'],
                'email'   => $data['email'],
                'message' => sprintf(
                    "Demande de réservation.\nPrestation : %s\nDate souhaitée : %s\nLieu : %s\n\n%s",
                    $service === null ? 'non précisée' : (string) $service['title'],
                    $preferredDate ?? 'non précisée',
                    $data['location'] ?? 'non précisé',
                    $data['message'] ?? ''
                ),
            ]);
        }

        $this->flashSuccess('Votre demande est enregistrée. Nous revenons vers vous très vite.');

        return $this->redirect('reservation?sent=1');
    }
}
