<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Controle de securite execute SUR l'hebergement reel.
 *
 * Raison d'etre : la protection des fichiers sensibles depend entierement de la
 * configuration du serveur (.htaccess pris en compte ou non, PHP reellement
 * execute, racine web imposee). Rien de tout cela ne peut etre verifie depuis
 * une machine de developpement : seul l'hebergement de destination a la reponse.
 *
 * On interroge donc le site depuis lui-meme, en HTTP, exactement comme le ferait
 * un curieux, et on regarde ce qui sort.
 *
 * Le controle le plus important : storage/config.php contient le secret qui
 * signe les QR. S'il est telechargeable, n'importe qui peut fabriquer des cartes
 * valides a l'infini, et tout le systeme ne vaut plus rien.
 */
final class SecurityCheck
{
    /** Chemins qui ne doivent jamais divulguer leur contenu. */
    private const CHEMINS_SENSIBLES = [
        'storage/config.php' => 'Secret de signature des QR',
        'storage/scannem.sqlite' => 'Base de donnees SQLite',
        'src/Token.php' => 'Code source',
        'composer.json' => 'Fichier de dependances',
        'app/api/redeem.php' => 'Route API appelee hors routeur',
    ];

    /**
     * @return array{base_url:?string, checks:list<array{nom:string, ok:bool, detail:string, critique:bool}>}
     */
    public static function run(?string $baseUrl = null): array
    {
        $baseUrl ??= self::deviner();
        $checks = [];

        // --- Ce qui se verifie sans reseau -----------------------------------

        $horsRacine = !Config::storageIsInsideProject();

        $checks[] = [
            'nom' => 'Emplacement du dossier de donnees',
            'ok' => $horsRacine,
            'critique' => false,
            'detail' => $horsRacine
                ? 'Hors de la racine web : c\'est la meilleure configuration.'
                : 'Dans la racine web. Ca fonctionne, mais la protection repose alors '
                  . 'uniquement sur le .htaccess. Voir le controle HTTP ci-dessous.',
        ];

        $config = Config::configFile();
        $permissions = is_file($config) ? substr(sprintf('%o', fileperms($config)), -4) : null;

        $checks[] = [
            'nom' => 'Permissions du fichier de configuration',
            'ok' => $permissions !== null && in_array($permissions, ['0600', '0400', '0640', '0660'], true),
            'critique' => false,
            'detail' => $permissions === null
                ? 'Fichier introuvable.'
                : 'Permissions ' . $permissions . ($permissions === '0600' ? ' (ideal)' : ''),
        ];

        $checks[] = [
            'nom' => 'Installateur supprime',
            'ok' => !self::installateurPresent(),
            'critique' => true,
            'detail' => self::installateurPresent()
                ? 'install.php est TOUJOURS PRESENT. Supprime-le par FTP : tant qu\'il '
                  . 'est la, il reste une porte d\'entree.'
                : 'install.php n\'est plus sur le serveur.',
        ];

        $checks[] = [
            'nom' => 'Connexion chiffree (HTTPS)',
            'ok' => self::estHttps(),
            'critique' => true,
            'detail' => self::estHttps()
                ? 'Le site repond en HTTPS.'
                : 'Le site est en HTTP simple. Les navigateurs REFUSENT l\'acces a la '
                  . 'camera hors HTTPS : le scanner ne pourra pas fonctionner.',
        ];

        // --- Ce qui demande d'interroger le site depuis l'exterieur -----------

        if ($baseUrl === null) {
            $checks[] = [
                'nom' => 'Exposition des fichiers sensibles',
                'ok' => false,
                'critique' => true,
                'detail' => 'Impossible de determiner l\'adresse du site : verifie a la main '
                    . 'que /storage/config.php n\'affiche rien.',
            ];

            return ['base_url' => null, 'checks' => $checks];
        }

        foreach (self::CHEMINS_SENSIBLES as $chemin => $libelle) {
            $reponse = self::recuperer(rtrim($baseUrl, '/') . '/' . $chemin);

            if ($reponse === null) {
                $checks[] = [
                    'nom' => $libelle . ' (' . $chemin . ')',
                    'ok' => false,
                    'critique' => false,
                    'detail' => 'Verification impossible : l\'hebergeur bloque les requetes '
                        . 'du serveur vers lui-meme. Ouvre l\'adresse dans ton navigateur.',
                ];
                continue;
            }

            [$code, $corps] = $reponse;

            // Refuse ou introuvable : parfait.
            $bloque = $code >= 400;

            // Servi, mais vide : c'est le cas d'un fichier PHP execute, qui ne
            // renvoie rien. Acceptable, meme si moins net qu'un refus franc.
            $vide = $code < 400 && trim($corps) === '';

            // Le cas grave : du contenu sort.
            $fuite = !$bloque && !$vide;

            $checks[] = [
                'nom' => $libelle . ' (' . $chemin . ')',
                'ok' => $bloque || $vide,
                'critique' => str_contains($chemin, 'config') || str_contains($chemin, 'sqlite'),
                'detail' => $fuite
                    ? 'DU CONTENU EST SERVI (HTTP ' . $code . ', ' . strlen($corps) . ' octets). '
                      . 'Corrige immediatement.'
                    : ($bloque ? 'Acces refuse (HTTP ' . $code . ').' : 'Servi mais vide : acceptable.'),
            ];
        }

        return ['base_url' => $baseUrl, 'checks' => $checks];
    }

    public static function installateurPresent(): bool
    {
        foreach ([Config::rootPath('install.php'), Config::rootPath('public/install.php')] as $chemin) {
            if (is_file($chemin)) {
                return true;
            }
        }

        return false;
    }

    public static function estHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /** Adresse publique du site, deduite de la requete en cours. */
    public static function deviner(): ?string
    {
        $hote = $_SERVER['HTTP_HOST'] ?? null;

        if (!is_string($hote) || $hote === '') {
            return null;
        }

        return (self::estHttps() ? 'https://' : 'http://') . $hote;
    }

    /**
     * @return array{int, string}|null [code HTTP, corps] ou null si injoignable
     */
    private static function recuperer(string $url): ?array
    {
        $contexte = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => "User-Agent: Scannem-SecurityCheck\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $corps = @file_get_contents($url, false, $contexte);

        if ($corps === false) {
            return null;
        }

        $code = 0;

        foreach ($http_response_header ?? [] as $ligne) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $ligne, $m) === 1) {
                $code = (int) $m[1];
            }
        }

        // Seuls les premiers octets nous interessent : un fichier volumineux ne
        // doit pas faire exploser la memoire d'un hebergement gratuit.
        return [$code, substr($corps, 0, 2048)];
    }
}
