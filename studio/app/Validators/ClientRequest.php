<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\ClientRepository;

final class ClientRequest extends FormRequest
{
    public function __construct(
        private ?int $exceptId = null,
        private ?ClientRepository $clients = null
    ) {
        $this->clients = $clients ?? new ClientRepository();
    }

    protected function rules(): array
    {
        return [
            'first_name' => 'required|string|min:2|max:100',
            'last_name'  => 'required|string|min:2|max:100',
            'email'      => 'nullable|email|max:190',
            'phone'      => 'nullable|phone',
            'company'    => 'nullable|string|max:150',
            'notes'      => 'nullable|string|max:5000',
        ];
    }

    protected function labels(): array
    {
        return [
            'first_name' => 'prénom',
            'last_name'  => 'nom',
            'email'      => 'e-mail',
            'phone'      => 'téléphone',
            'company'    => 'société',
        ];
    }

    protected function after(Validator $validator, Request $request): void
    {
        $email = trim((string) $request->input('email', ''));

        // A warning, not a rule: two people in one family legitimately share
        // an address, and the photographer is the one who knows whether this
        // is a duplicate.
        if ($email !== '' && $this->clients->emailExists($email, $this->exceptId)) {
            Session::flash('info', 'Un autre client utilise déjà cette adresse e-mail.');
        }
    }

    protected function transform(array $validated, Request $request): array
    {
        return [
            'first_name' => trim((string) $validated['first_name']),
            'last_name'  => trim((string) $validated['last_name']),
            'email'      => self::normaliseEmail($validated['email'] ?? null),
            'phone'      => self::nullIfBlank($validated['phone'] ?? null),
            'company'    => self::nullIfBlank($validated['company'] ?? null),
            'notes'      => self::nullIfBlank($validated['notes'] ?? null),
        ];
    }
}
