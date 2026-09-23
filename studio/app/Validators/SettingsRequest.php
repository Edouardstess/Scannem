<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;
use App\Core\Validator;
use App\Services\MapService;

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
            'map_query'         => 'nullable|string|max:255',
            'map_embed_code'    => 'nullable|string|max:5000',
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
            'map_query'        => 'localisation',
            'map_embed_code'   => 'carte Google Maps',
        ];
    }

    protected function after(Validator $validator, Request $request): void
    {
        $code = trim($request->string('map_embed_code'));

        if ($code !== '' && MapService::extractEmbedUrl($code) === null) {
            $validator->addError(
                'map_embed_code',
                'Code non reconnu. Dans Google Maps : Partager → Intégrer une carte → Copier le code HTML, '
                . 'puis collez-le ici. Un lien court (maps.app.goo.gl) ne peut pas être intégré.'
            );
        }
    }

    protected function transform(array $validated, Request $request): array
    {
        $values = [];

        foreach (array_keys($this->rules()) as $key) {
            if ($key === 'map_embed_code') {
                // Only the verified Google URL is kept, never the pasted HTML.
                $values['map_embed_url'] = MapService::extractEmbedUrl($request->string($key)) ?? '';
                continue;
            }

            $value = $request->string($key);
            $values[$key] = str_starts_with($key, 'color_') ? self::normaliseColour($value) : $value;
        }

        $values['booking_enabled'] = $request->bool('booking_enabled');
        $values['client_area_enabled'] = $request->bool('client_area_enabled');
        $values['map_show_home'] = $request->bool('map_show_home');
        $values['map_click_to_load'] = $request->bool('map_click_to_load');

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
