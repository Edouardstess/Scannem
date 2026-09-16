<?php

/**
 * Amorce commune a tous les points d'entree web.
 *
 * Ce fichier s'execute AVANT de savoir si le serveur peut charger le reste du
 * code : il est donc ecrit en PHP 7 et n'utilise aucune syntaxe recente. C'est
 * la seule facon de transformer deux pannes muettes en messages lisibles :
 *
 *   - PHP trop ancien. src/ utilise `readonly` et `never`, qui datent de 8.1.
 *     Sur un WAMP livre avec PHP 8.0, le chargement de la premiere classe
 *     produit une erreur d'analyse : page blanche ou HTTP 500 sans explication.
 *     Un message clair vaut mieux qu'une demi-journee perdue.
 *   - vendor/ absent. Depuis le depot, sans `composer install`, le require
 *     echoue avec un chemin serveur en pleine page.
 *
 * Troisieme role : fixer le prefixe d'installation, parce que les points
 * d'entree sont les seuls a savoir a quelle profondeur ils se trouvent.
 */

if (!function_exists('scannem_est_une_route_api')) {
    /**
     * Une route API ne doit jamais repondre autre chose que du JSON, meme quand
     * la panne est anterieure au chargement de l'application : le scanner
     * prendrait une page HTML pour une coupure reseau et basculerait a tort en
     * mode hors-ligne, ce qui est exactement le contraire du diagnostic utile.
     */
    function scannem_est_une_route_api()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        return strpos($uri, '/api/') !== false;
    }
}

if (!function_exists('scannem_panne')) {
    /**
     * Repond une panne de configuration et s'arrete.
     *
     * @param string       $titre
     * @param array<int,string> $lignes  paragraphes deja echappes
     */
    function scannem_panne($titre, array $lignes)
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        if (scannem_est_une_route_api()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            echo json_encode(array(
                'ok' => false,
                'result' => 'server_error',
                'label' => 'ERREUR SERVEUR',
                'color' => 'red',
                'admitted' => false,
                'consumed' => false,
                'retryable' => false,
                'message' => $titre,
            ));
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><meta charset="utf-8"><title>Scannem</title>'
            . '<div style="font:16px/1.6 system-ui,sans-serif;padding:40px;max-width:40em">'
            . '<h1 style="font-size:20px">' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</h1>';

        foreach ($lignes as $ligne) {
            echo '<p>' . $ligne . '</p>';
        }

        echo '</div>';
        exit;
    }
}

if (!function_exists('scannem_racine')) {
    /**
     * Racine du projet, trouvee en remontant jusqu'a vendor/autoload.php.
     *
     * Chercher vendor/ plutot que supposer une profondeur fixe permet a la meme
     * arborescence de fonctionner avec la racine web sur public/ comme avec tout
     * depose a plat dans htdocs/.
     *
     * @param  string $depart
     * @return string
     */
    function scannem_racine($depart)
    {
        $racine = $depart;

        while (!is_file($racine . '/vendor/autoload.php') && dirname($racine) !== $racine) {
            $racine = dirname($racine);
        }

        return $racine;
    }
}

if (!function_exists('scannem_amorcer')) {
    /**
     * Verifie le terrain, charge l'autoloader, fixe le prefixe d'installation.
     *
     * @param string   $racine   racine du projet (celle qui contient vendor/)
     * @param int|null $remonte  dossiers entre le script appelant et cette
     *                           racine web : 0 pour index.php et install.php,
     *                           1 pour les relais admin/ et api/. null laisse le
     *                           prefixe tel quel — c'est le cas des fichiers de
     *                           app/, qui sont toujours inclus par un point
     *                           d'entree ayant deja tranche.
     */
    function scannem_amorcer($racine, $remonte = null)
    {
        if (PHP_VERSION_ID < 80100) {
            scannem_panne(
                'PHP ' . PHP_VERSION . ' est trop ancien pour Scannem (8.1 minimum)',
                array(
                    'Scannem et ses dependances de generation de QR demandent PHP 8.1 ou plus'
                    . ' recent. Rien d autre ne manque : cette page est la derniere etape.',

                    '<strong>Sous WAMP</strong> — clic gauche sur l icone de la barre des taches,'
                    . ' <em>PHP</em> &rarr; <em>Version</em>. Si 8.1 ou plus figure dans la liste,'
                    . ' choisis-le : Apache redemarre et il suffit de recharger cette page.',

                    'Ce menu ne liste que les versions <em>deja installees</em>, et WampServer n en'
                    . ' livre souvent qu une. Si la liste s arrete a 8.0, telecharge un module PHP'
                    . ' recent sur <code>wampserver.aviatechno.net</code> (rubrique'
                    . ' <em>PHP versions</em>), lance l installateur, puis reviens au menu'
                    . ' <em>PHP &rarr; Version</em> : la nouvelle version y apparait.',

                    '<strong>Sans rien telecharger, ni toucher a Apache</strong> — WampServer'
                    . ' installe ses propres binaires PHP. Ouvre une invite de commandes et tape'
                    . ' <code>dir C:\wamp64\bin\php</code> : si un dossier <code>php8.1</code>'
                    . ' ou plus recent s y trouve, il contient un <code>php.exe</code> utilisable'
                    . ' tel quel, extensions deja configurees. Place-toi dans le dossier de'
                    . ' Scannem et lance-le avec le serveur integre, en adaptant le numero :'
                    . ' <code>C:\wamp64\bin\php\php8.3.0\php.exe -S localhost:8000</code>.'
                    . ' L application repond alors sur <code>http://localhost:8000/</code>.',

                    '<strong>Sous XAMPP</strong> — les versions de PHP ne se changent pas depuis'
                    . ' le panneau. Installe un paquet XAMPP recent (PHP 8.2 ou plus) a cote de'
                    . ' l actuel, et sers Scannem depuis celui-la.',

                    'PHP 8.0 n est plus suivi en securite depuis fin 2023 : la mise a jour est de'
                    . ' toute facon souhaitable.',
                )
            );
        }

        $autoload = $racine . '/vendor/autoload.php';

        if (!is_file($autoload)) {
            scannem_panne(
                'Les dependances ne sont pas installees',
                array(
                    'Le fichier <code>vendor/autoload.php</code> est introuvable.',
                    'Depuis le depot : lance <code>composer install</code> a la racine du projet.',
                    'Depuis l archive de deploiement : <code>vendor/</code> y est deja inclus, il'
                    . ' a donc du etre oublie pendant l envoi par FTP. Renvoie-le en entier.',
                )
            );
        }

        require_once $autoload;

        if ($remonte !== null) {
            Scannem\Url::setBase(Scannem\Url::fromScript($remonte));
        }
    }
}
