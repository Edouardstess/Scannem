<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

/**
 * Site settings.
 *
 * The field list is the single source of truth: SettingsController iterates
 * over rules() rather than keeping its own copy, so adding a setting is one
 * edit rather than two that can drift apart.
 */
final class SettingsRequest extends FormRequest
{
    public const IMAGE_FIELDS = ['hero_image', 'about_image', 'logo_path'];

    protected function rules(): array
    {
        return [
            'studio_name'       => 'required|string|max:120',
            'photographer_name' => 'nullable|string|max:120',
            'tagline'           => 'nullable|string|max:190',
            'speciality'        => 'nullable|string|max:190',
            'hero_title'        => 'nullable|string|max:190',
            'hero_subtitle'     => 'nullable|string|max:255',
            'about_title'       => 'nullable|string|max:190',
            'about_text'        => 'nullable|string|max:5000',
            'contact_email'     => 'nullable|email|max:190',
            'contact_phone'     => 'nullable|phone',
            'contact_address'   => 'nullable|string|max:255',
            'social_instagram'  => 'nullable|url|max:255',
            'social_facebook'   => 'nullable|url|max:255',
            'social_linkedin'   => 'nullable|url|max:255',
            'social_pinterest'  => 'nullable|url|max:255',
            'footer_text'       => 'nullable|string|max:500',
            'meta_description'  => 'nullable|string|max:255',
            'watermark_text'    => 'nullable|string|max:120',
            'color_primary'     => 'nullable|hex',
            'color_accent'      => 'nullable|hex',
            'color_background'  => 'nullable|hex',
        ];
    }

    protected function labels(): array
    {
        return [
            'studio_name'      => 'nom du studio',
            'contact_email'    => 'e-mail de contact',
            'contact_phone'    => 'téléphone',
            'color_primary'    => 'couleur principale',
            'color_accent'     => "couleur d'accent",
            'color_background' => 'couleur de fond',
        ];
    }

    protected function transform(array $validated, Request $request): array
    {
        $values = [];

        foreach (array_keys($this->rules()) as $key) {
            $value = $request->string($key);
            $values[$key] = str_starts_with($key, 'color_') ? self::normaliseColour($value) : $value;
        }

        $values['booking_enabled'] = $request->bool('booking_enabled');
        $values['client_area_enabled'] = $request->bool('client_area_enabled');

        return $values;
    }

    private static function normaliseColour(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return str_starts_with($value, '#') ? $value : '#' . $value;
    }
}
