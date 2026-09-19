<?php
declare(strict_types=1);

/**
 * Pages d'erreur HTTP. Un seul gabarit, une seule façon de répondre.
 */
final class ErreurHttp
{
    private const MESSAGES = [
        400 => ['Requête invalide', 'La requête envoyée n\'a pas pu être interprétée.'],
        403 => ['Accès interdit', 'Vous n\'avez pas les autorisations nécessaires pour accéder à cette page.'],
        404 => ['Page introuvable', 'La page demandée n\'existe pas ou a été déplacée.'],
        405 => ['Méthode non autorisée', 'Cette adresse n\'accepte pas ce type de requête.'],
        419 => ['Session expirée', 'Votre session a expiré. Veuillez recharger la page et réessayer.'],
        500 => ['Erreur interne', 'Une erreur inattendue est survenue. L\'incident a été enregistré.'],
        503 => ['Service indisponible', 'L\'application est momentanément indisponible. Réessayez dans quelques instants.'],
    ];

    /** Envoie la page d'erreur puis arrête l'exécution. */
    public static function afficher(int $code, ?string $detail = null): never
    {
        if (!isset(self::MESSAGES[$code])) {
            $code = 500;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($code);

        [$titre, $message] = self::MESSAGES[$code];
        $detail = $detail ?? $message;

        require RACINE_VIEWS . '/errors/erreur.php';
        exit;
    }
}
