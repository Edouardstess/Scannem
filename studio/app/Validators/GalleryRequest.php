<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;
use App\Core\Validator;
use App\Models\GalleryStatus;
use App\Repositories\EventRepository;
use App\Services\TokenService;

final class GalleryRequest extends FormRequest
{
    public function __construct(
        private ?EventRepository $events = null,
        private ?TokenService $tokens = null
    ) {
        $this->events = $events ?? new EventRepository();
        $this->tokens = $tokens ?? new TokenService();
    }

    protected function rules(): array
    {
        return [
            'event_id'    => 'required|integer',
            'title'       => 'required|string|min:2|max:190',
            'description' => 'nullable|string|max:5000',
            'status'      => 'required|in:' . implode(',', GalleryStatus::ALL),
            'password'    => 'nullable|string|min:6|max:255',
        ];
    }

    protected function labels(): array
    {
        return [
            'event_id' => 'événement',
            'title'    => 'titre',
            'status'   => 'statut',
            'password' => 'mot de passe',
        ];
    }

    protected function messages(): array
    {
        return [
            'password.min' => 'Le mot de passe de galerie doit contenir au moins 6 caractères.',
        ];
    }

    protected function after(Validator $validator, Request $request): void
    {
        if ($this->events->find($request->int('event_id')) === null) {
            $validator->addError('event_id', "Cet événement n'existe pas.");
        }

        if ($request->string('expiry_option') !== 'custom') {
            return;
        }

        $date = $request->string('expiry_date');

        if ($date === '' || strtotime($date) === false) {
            $validator->addError('expiry_date', "Indiquez une date d'expiration valide.");

            return;
        }

        if (strtotime($date) < time()) {
            $validator->addError('expiry_date', "La date d'expiration doit être dans le futur.");
        }
    }

    protected function transform(array $validated, Request $request): array
    {
        return [
            'event_id'          => (int) $validated['event_id'],
            'title'             => trim((string) $validated['title']),
            'description'       => self::nullIfBlank($validated['description'] ?? null),
            'status'            => (string) $validated['status'],
            'password'          => $request->input('password'),
            'remove_password'   => $request->bool('remove_password'),
            'watermark_enabled' => $request->bool('watermark_enabled'),
            'download_enabled'  => $request->bool('download_enabled'),
            'selection_enabled' => $request->bool('selection_enabled'),
            'expires_at'        => $this->tokens->resolveExpiry(
                $request->string('expiry_option', 'never'),
                $request->string('expiry_date')
            ),
        ];
    }
}
