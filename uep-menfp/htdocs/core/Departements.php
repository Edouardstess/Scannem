<?php
declare(strict_types=1);

/** Les dix départements géographiques d'Haïti. */
final class Departements
{
    private const LISTE = [
        'artibonite' => 'Artibonite',
        'centre'     => 'Centre',
        'grand_anse' => "Grand'Anse",
        'nippes'     => 'Nippes',
        'nord'       => 'Nord',
        'nord_est'   => 'Nord-Est',
        'nord_ouest' => 'Nord-Ouest',
        'ouest'      => 'Ouest',
        'sud'        => 'Sud',
        'sud_est'    => 'Sud-Est',
    ];

    /** @return array<string, string> */
    public static function tous(): array
    {
        return self::LISTE;
    }

    public static function nom(?string $code): ?string
    {
        return $code === null ? null : (self::LISTE[$code] ?? null);
    }

    public static function valide(string $code): bool
    {
        return isset(self::LISTE[$code]);
    }

    /**
     * Libellé lisible d'une valeur stockée : le champ peut contenir un code
     * (« nord_est ») ou déjà un libellé saisi à la main (« Nord-Est »).
     */
    public static function affichage(?string $valeur): string
    {
        if ($valeur === null || trim($valeur) === '') {
            return '—';
        }

        return self::LISTE[$valeur] ?? $valeur;
    }
}
