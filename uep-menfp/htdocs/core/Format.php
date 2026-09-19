<?php
declare(strict_types=1);

/** Mise en forme des valeurs affichées (montants, dates, libellés). */
final class Format
{
    /** Montant en gourdes haïtiennes : 1 234 567,89 HTG */
    public static function montant(float|int|string|null $valeur, bool $devise = true): string
    {
        $nombre = number_format((float)$valeur, 2, ',', ' ');
        return $devise ? $nombre . ' HTG' : $nombre;
    }

    public static function nombre(float|int|string|null $valeur): string
    {
        return number_format((float)$valeur, 0, ',', ' ');
    }

    /** Date au format jj/mm/aaaa. */
    public static function date(?string $valeur): string
    {
        if ($valeur === null || $valeur === '' || str_starts_with($valeur, '0000')) {
            return '—';
        }

        $date = date_create($valeur);
        return $date ? $date->format('d/m/Y') : '—';
    }

    /** Date et heure au format jj/mm/aaaa à HH:MM. */
    public static function dateHeure(?string $valeur): string
    {
        if ($valeur === null || $valeur === '' || str_starts_with($valeur, '0000')) {
            return '—';
        }

        $date = date_create($valeur);
        return $date ? $date->format('d/m/Y \à H:i') : '—';
    }

    public static function pourcentage(int|float $partie, int|float $total): int
    {
        return $total > 0 ? (int)round(($partie / $total) * 100) : 0;
    }

    public static function priorite(?string $valeur): string
    {
        return match ($valeur) {
            'basse'   => 'Basse',
            'haute'   => 'Haute',
            'urgente' => 'Urgente',
            default   => 'Normale',
        };
    }

    public static function statutRequisition(?string $valeur): string
    {
        return match ($valeur) {
            'approuvee' => 'Approuvée',
            'rejetee'   => 'Rejetée',
            'livree'    => 'Livrée',
            default     => 'En attente',
        };
    }

    public static function role(?string $valeur): string
    {
        return match ($valeur) {
            'administrateur' => 'Administrateur',
            'superviseur'    => 'Superviseur',
            'saisisseur'     => 'Saisisseur',
            'lecteur'        => 'Lecteur',
            default          => ucfirst((string)$valeur),
        };
    }
}
