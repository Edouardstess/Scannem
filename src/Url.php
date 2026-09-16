<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Prefixe d'installation.
 *
 * Toutes les URL de l'application etaient absolues : /scan/, /admin/,
 * /api/redeem.php. Ca ne marche qu'a une condition — que Scannem occupe la
 * racine du site. Depose dans un sous-dossier (htdocs/scannem/, le cas normal
 * sur un WAMP local ou un domaine partage), le meme code renvoie sur
 * http://localhost/scan/ : Apache ne trouve rien et affiche sa propre page 404.
 *
 * Cette classe repond a une seule question : sous quel prefixe l'application
 * est-elle servie ? '' a la racine, '/scannem' dans un sous-dossier. Tout le
 * reste du code passe par Url::to() et n'a plus a s'en soucier.
 */
final class Url
{
    /** null tant que personne n'a tranche : Url::base() deduira tout seul. */
    private static ?string $base = null;

    /**
     * Fixe le prefixe. Appele par les points d'entree, qui sont les seuls a
     * savoir a quelle profondeur ils se trouvent.
     */
    public static function setBase(string $base): void
    {
        self::$base = self::nettoyer($base);
    }

    /** Oublie le prefixe retenu. Utile aux tests. */
    public static function reset(): void
    {
        self::$base = null;
    }

    /**
     * Prefixe d'installation : '' a la racine, '/scannem' dans un sous-dossier.
     */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        // Filet de securite : un fichier de app/ appele directement, sans passer
        // par un point d'entree. On retombe sur le dossier du script, en retirant
        // le sous-dossier du point d'entree quand c'en est un.
        $dossier = self::dossierDuScript();

        foreach (['/admin', '/api'] as $sousDossier) {
            if (str_ends_with($dossier, $sousDossier)) {
                $dossier = substr($dossier, 0, -strlen($sousDossier));
                break;
            }
        }

        return self::$base = self::nettoyer($dossier);
    }

    /**
     * URL absolue (cote serveur) d'un chemin interne.
     *
     *     Url::to('/admin/?p=lots')  ->  /scannem/admin/?p=lots
     */
    public static function to(string $chemin): string
    {
        return self::base() . '/' . ltrim($chemin, '/');
    }

    /**
     * Retire le prefixe d'un chemin de requete, pour que le routeur raisonne
     * toujours sur des chemins internes ('/admin', '/api/redeem.php').
     */
    public static function strip(string $chemin): string
    {
        $base = self::base();

        if ($base !== '' && str_starts_with($chemin, $base)) {
            $reste = substr($chemin, strlen($base));

            // Le prefixe doit correspondre a un dossier entier : sous /scannem,
            // un chemin /scannemois n'est pas une page de l'application.
            if ($reste === '' || str_starts_with($reste, '/')) {
                $chemin = $reste;
            }
        }

        return $chemin === '' ? '/' : $chemin;
    }

    /**
     * Deduit le prefixe de l'URL du script en cours d'execution.
     *
     * $remonte est le nombre de dossiers qui separent le script de la racine de
     * l'installation : 0 pour index.php et install.php, poses a la racine ;
     * 1 pour les relais admin/index.php et api/redeem.php.
     *
     * On part de SCRIPT_NAME plutot que de REQUEST_URI parce que SCRIPT_NAME
     * designe le fichier reellement execute : il reste juste que le site soit
     * a la racine, dans un sous-dossier, ou derriere une reecriture.
     */
    public static function fromScript(int $remonte = 0): string
    {
        $dossier = self::dossierDuScript();

        for ($i = 0; $i < $remonte; $i++) {
            $dossier = self::nettoyer(dirname($dossier));
        }

        return self::nettoyer($dossier);
    }

    private static function dossierDuScript(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $script = is_string($script) ? str_replace('\\', '/', $script) : '';

        if ($script === '') {
            return '';
        }

        return self::nettoyer(dirname($script));
    }

    /**
     * Forme canonique : '' ou '/quelque/chose', jamais '/' ni '.', jamais de
     * slash final. dirname() renvoie '.' ou '/' selon les cas, et un '/' final
     * doublerait les slashs dans toutes les URL produites.
     */
    private static function nettoyer(string $base): string
    {
        $base = str_replace('\\', '/', trim($base));

        if ($base === '' || $base === '.' || $base === '/') {
            return '';
        }

        $base = '/' . trim($base, '/');

        return $base === '/' ? '' : $base;
    }
}
