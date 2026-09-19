<?php
declare(strict_types=1);

/**
 * Messages à usage unique affichés après une redirection.
 * Le rendu est centralisé dans les layouts : aucune vue n'a à s'en occuper.
 */
final class Flash
{
    private const CLE = 'flash_messages';
    public const TYPES = ['succes', 'erreur', 'info', 'avertissement'];

    public static function succes(string $message): void
    {
        self::ajouter('succes', $message);
    }

    public static function erreur(string $message): void
    {
        self::ajouter('erreur', $message);
    }

    public static function info(string $message): void
    {
        self::ajouter('info', $message);
    }

    public static function avertissement(string $message): void
    {
        self::ajouter('avertissement', $message);
    }

    public static function ajouter(string $type, string $message): void
    {
        if (!in_array($type, self::TYPES, true)) {
            $type = 'info';
        }

        $messages = Session::get(self::CLE, []);
        if (!is_array($messages)) {
            $messages = [];
        }

        $messages[] = ['type' => $type, 'message' => $message];
        Session::set(self::CLE, $messages);
    }

    /** @return list<array{type: string, message: string}> */
    public static function consommer(): array
    {
        $messages = Session::consommer(self::CLE);
        return is_array($messages) ? $messages : [];
    }

    public static function icone(string $type): string
    {
        return match ($type) {
            'succes'        => 'bi-check-circle-fill',
            'erreur'        => 'bi-exclamation-octagon-fill',
            'avertissement' => 'bi-exclamation-triangle-fill',
            default         => 'bi-info-circle-fill',
        };
    }
}
