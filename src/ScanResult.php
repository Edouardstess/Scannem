<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Verdict d'un scan. Les valeurs sont stockees en base et renvoyees telles quelles
 * par l'API, donc elles ne doivent jamais changer sans migration.
 */
final class ScanResult
{
    /** Premiere presentation d'une carte valide : la personne entre. */
    public const ADMITTED = 'admitted';

    /** Carte authentique mais deja consommee : copie, ou deuxieme passage. */
    public const ALREADY_USED = 'already_used';

    /** Signature invalide : carte fabriquee, ou QR abime au point d'etre illisible. */
    public const FORGED = 'forged';

    /** Carte annulee par l'organisateur (perte, remboursement, exclusion). */
    public const REVOKED = 'revoked';

    /** Signature valide mais aucune carte correspondante en base. */
    public const UNKNOWN = 'unknown';

    /** Admis hors-ligne, en attente de confirmation par le serveur. */
    public const OFFLINE_PENDING = 'offline_pending';

    /** Deux appareils hors-ligne ont admis la meme carte : arbitrage humain requis. */
    public const DISPUTED = 'disputed';

    /** Quota depasse : appareil bride. */
    public const RATE_LIMITED = 'rate_limited';

    /**
     * Serveur momentanement incapable de repondre (contention, panne breve).
     *
     * A ne surtout pas confondre avec un refus : la carte n'a PAS ete consommee
     * et la personne n'a rien fait de mal. L'ecran doit le dire clairement,
     * sinon un vigile presse refuse quelqu'un de parfaitement legitime.
     */
    public const SERVER_BUSY = 'server_busy';

    /**
     * Libelles affiches au vigile. Courts, sans ambiguite, lisibles a bout de bras.
     *
     * @return array<string,string>
     */
    public static function labels(): array
    {
        return [
            self::ADMITTED => 'ENTREE AUTORISEE',
            self::ALREADY_USED => 'DEJA UTILISEE',
            self::FORGED => 'CARTE NON VALIDE',
            self::REVOKED => 'CARTE ANNULEE',
            self::UNKNOWN => 'CARTE INCONNUE',
            self::OFFLINE_PENDING => 'HORS-LIGNE - A CONFIRMER',
            self::DISPUTED => 'LITIGE - DOUBLON HORS-LIGNE',
            self::RATE_LIMITED => 'TROP DE SCANS - PATIENTEZ',
            self::SERVER_BUSY => 'SERVEUR OCCUPE - RESCANNE',
        ];
    }

    public static function label(string $result): string
    {
        return self::labels()[$result] ?? strtoupper($result);
    }

    /** Couleur de l'ecran vigile : green, red ou amber. */
    public static function color(string $result): string
    {
        return match ($result) {
            self::ADMITTED => 'green',
            // Orange et non rouge : rien n'a ete refuse, il faut simplement
            // rescanner. Le rouge signifierait a tort que la carte est mauvaise.
            self::OFFLINE_PENDING, self::SERVER_BUSY, self::RATE_LIMITED => 'amber',
            default => 'red',
        };
    }

    /**
     * La carte a-t-elle ete consommee par ce scan ?
     *
     * Sert a distinguer un vrai verdict d'un incident technique : sur un
     * incident, rescanner la meme carte est sans danger.
     */
    public static function isVerdict(string $result): bool
    {
        return !in_array($result, [self::SERVER_BUSY, self::RATE_LIMITED], true);
    }

    public static function isAdmission(string $result): bool
    {
        return $result === self::ADMITTED || $result === self::OFFLINE_PENDING;
    }
}
