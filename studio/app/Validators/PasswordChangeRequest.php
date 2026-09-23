<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

final class PasswordChangeRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:10|max:255|confirmed',
        ];
    }

    protected function labels(): array
    {
        return [
            'current_password' => 'mot de passe actuel',
            'password'         => 'nouveau mot de passe',
        ];
    }

    protected function messages(): array
    {
        return [
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 10 caractères.',
            'password.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ];
    }

    protected function transform(array $validated, Request $request): array
    {
        return [
            'current_password' => (string) ($request->input('current_password') ?? ''),
            'password'         => (string) ($request->input('password') ?? ''),
        ];
    }
}
