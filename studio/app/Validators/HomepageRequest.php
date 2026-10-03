<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;
use App\Core\Validator;
use App\Services\ImmersiveHomeService;

/**
 * Texts of the immersive homepage (Admin → Page d'accueil).
 *
 * The length limits are the template's own: each scene is drawn for short
 * lines (the positioning line is set on one line across the screen, the
 * hand-drawn oval only reads around two or three words…). Past them the
 * animation breaks visually, so they are enforced here rather than hoped for.
 */
final class HomepageRequest extends FormRequest
{
    /** Field => max length. Everything else in DEFAULTS is a 190-char text. */
    private const LIMITS = [
        'hook_line1' => 40, 'hook_line2a' => 18, 'hook_line2b' => 18,
        'positioning' => 42, 'manifesto' => 260,
        'proof_sub' => 120, 'quote_text' => 240, 'quote_unit' => 4, 'quote_figure' => 8,
        'motto_1_word' => 12, 'motto_2_word' => 12, 'motto_3_word' => 12,
        'steps_intro_a' => 14, 'steps_intro_b' => 14, 'steps_intro_c' => 14, 'steps_cta' => 24,
        'step_1_name' => 28, 'step_2_name' => 28, 'step_3_name' => 28,
        'step_1_desc' => 180, 'step_2_desc' => 180, 'step_3_desc' => 180,
        'pill' => 18, 'finale' => 30,
        'nav_proof' => 12, 'nav_steps' => 12, 'nav_cta' => 12,
    ];

    protected function rules(): array
    {
        $rules = ['home_layout' => 'required|in:immersive,classic'];

        foreach (array_keys(ImmersiveHomeService::DEFAULTS) as $key) {
            $rules[$key] = match ($key) {
                'theme'  => 'required|in:light,dark',
                'accent' => 'required|hex',
                default  => 'nullable|string|max:' . (self::LIMITS[$key] ?? 190),
            };
        }

        return $rules;
    }

    protected function labels(): array
    {
        return [
            'positioning'  => 'positionnement',
            'manifesto'    => 'texte de démarche',
            'quote_unit'   => 'unité du chiffre',
            'quote_figure' => 'chiffre',
            'pill'         => 'mot entouré',
            'accent'       => 'couleur d’accent',
        ];
    }

    protected function after(Validator $validator, Request $request): void
    {
        $manifesto = trim($request->string('manifesto'));

        if ($manifesto !== '') {
            $count = preg_match_all('/\[\[(.+?)\]\]/u', $manifesto, $marked);
            $words = $count === 1 ? count(preg_split('/\s+/u', trim($marked[1][0])) ?: []) : 0;

            if ($count !== 1 || $words < 1 || $words > 3) {
                $validator->addError(
                    'manifesto',
                    'Entourez UN passage de 1 à 3 mots avec [[ et ]], par exemple : des [[instants vrais]].'
                );
            }
        }

        $pill = trim($request->string('pill'));

        if ($pill !== '' && count(preg_split('/\s+/u', $pill) ?: []) > 2) {
            $validator->addError('pill', 'Le mot entouré tient en 1 ou 2 mots.');
        }

        foreach ([1, 2, 3] as $i) {
            if (preg_match('/\s/u', trim($request->string("motto_{$i}_word"))) === 1) {
                $validator->addError("motto_{$i}_word", 'Un seul mot : il traverse l’écran en lettres géantes.');
            }
        }

        $figure = trim($request->string('quote_figure'));

        if ($figure !== '' && preg_match('/^[^\d.,+-]*[+\x{2212}-]?\s*-?[\d.,]+$/u', $figure) !== 1) {
            $validator->addError('quote_figure', 'Un nombre, éventuellement précédé de +, − ou × (ex. : +40, 3, ×2).');
        }
    }

    protected function transform(array $validated, Request $request): array
    {
        $texts = [];

        foreach (array_keys(ImmersiveHomeService::DEFAULTS) as $key) {
            $texts[$key] = trim($request->string($key));
        }

        $texts['accent'] = strtolower($texts['accent']);

        return [
            'home_layout' => $request->string('home_layout') === 'classic' ? 'classic' : 'immersive',
            'immersive'   => $texts,
        ];
    }
}
