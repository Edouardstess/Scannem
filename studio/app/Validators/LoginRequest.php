<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

final class LoginRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'email'    => 'required|email|max:190',
            'password' => 'required|string|max:255',
        ];
    }

    protected function labels(): array
    {
        return ['email' => 'e-mail', 'password' => 'mot de passe'];
    }

    protected function transform(array $validated, Request $request): array
    {
        return [
            'email'    => (string) self::normaliseEmail($validated['email']),
            // Never trimmed: a trailing space is part of the password.
            'password' => (string) ($request->input('password') ?? ''),
        ];
    }
}
