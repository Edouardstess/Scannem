<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PHPUnit\Framework\TestCase;
use Scannem\Config;
use Scannem\QrRenderer;
use Scannem\Token;

/**
 * Contraintes propres a un hebergement mutualise gratuit.
 *
 * Ces tests protegent des regressions qui ne se verraient qu'une fois en ligne,
 * c'est-a-dire trop tard : pas de SSH pour corriger, pas de Composer pour
 * reinstaller, et un evenement qui commence.
 */
final class DeploiementTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SCANNEM_STORAGE_PATH');
    }

    // ------------------------------------------- Dossier de donnees deplacable

    public function testLeDossierDeDonneesSuitLaVariableDEnvironnement(): void
    {
        $ailleurs = sys_get_temp_dir() . '/scannem-hors-racine';
        putenv('SCANNEM_STORAGE_PATH=' . $ailleurs);

        self::assertSame($ailleurs, Config::storageBase());
        self::assertSame($ailleurs . '/config.php', Config::configFile());
    }

    public function testUnCheminDeDonneesHorsProjetEstReconnuCommeTel(): void
    {
        putenv('SCANNEM_STORAGE_PATH=' . sys_get_temp_dir() . '/scannem-hors-racine');

        self::assertFalse(
            Config::storageIsInsideProject(),
            'Un dossier hors du projet doit etre detecte comme hors de la racine web'
        );
    }

    public function testParDefautLeDossierDeDonneesEstDansLeProjet(): void
    {
        putenv('SCANNEM_STORAGE_PATH');

        self::assertTrue(Config::storageIsInsideProject());
        self::assertSame(Config::rootPath('storage'), Config::storageBase());
    }

    public function testLeCheminEstNettoyeDeSonSlashFinal(): void
    {
        putenv('SCANNEM_STORAGE_PATH=/tmp/scannem-donnees/');

        self::assertSame('/tmp/scannem-donnees', Config::storageBase());
        self::assertSame('/tmp/scannem-donnees/config.php', Config::configFile());
    }

    // --------------------------------------------------------- GD facultatif

    public function testLaPlancheDImpressionNeDependPasDeGd(): void
    {
        $token = new Token(['A' => str_repeat('a', 64)], 'A');
        $payload = $token->build(Token::newUid());

        $planche = (new QrRenderer())->printSheet(
            [['uid' => 'AAAABBBBCCCCDDDD', 'payload' => $payload]],
            'Essai'
        );

        // Le livrable qui compte est en SVG : une installation sans GD reste
        // parfaitement utilisable.
        self::assertStringContainsString('<svg', $planche);
        self::assertStringNotContainsString('data:image/png', $planche);
    }

    public function testLAbsenceDeGdEstAnnonceeClairement(): void
    {
        if (QrRenderer::pngDisponible()) {
            self::assertTrue(true, 'GD present sur cette machine : rien a verifier ici.');

            return;
        }

        $this->expectExceptionMessageMatches('/GD/');
        (new QrRenderer())->png('SCN1A.AAAABBBBCCCCDDDD.EEEEFFFFGGGGHHHH');
    }

    // ------------------------------------- Points d'entree sans mod_rewrite

    public function testLesPointsDEntreeRemontentJusquAVendor(): void
    {
        // public/index.php et public/install.php cherchent vendor/autoload.php en
        // remontant, au lieu de supposer une profondeur fixe. C'est ce qui permet
        // a la meme archive de fonctionner avec la racine web sur public/ comme
        // avec tout depose a plat dans htdocs/.
        foreach (['public/index.php', 'public/install.php'] as $fichier) {
            $source = (string) file_get_contents(Config::rootPath($fichier));

            self::assertStringContainsString(
                "vendor/autoload.php",
                $source,
                "$fichier doit localiser vendor/"
            );
            self::assertStringContainsString(
                'dirname(',
                $source,
                "$fichier doit remonter l'arborescence plutot que supposer un niveau"
            );
        }
    }

    public function testLeRouteurAccepteLesDeuxEcrituresDesRoutesApi(): void
    {
        $source = (string) file_get_contents(Config::rootPath('public/index.php'));

        // Le scanner appelle /api/redeem.php, qui correspond a un vrai fichier
        // chez l'hebergeur. Apache ne devine pas les routes sans extension.
        self::assertStringContainsString(
            "str_ends_with(\$route, '.php')",
            $source,
            'Le routeur doit accepter /api/redeem.php aussi bien que /api/redeem'
        );
    }

    public function testLeScannerAppelleDesUrlsQuiExistentCommeFichiers(): void
    {
        $app = (string) file_get_contents(Config::rootPath('public/scan/app.js'));

        // Sans extension, ces appels tomberaient en 404 sur Apache des que
        // mod_rewrite serait absent ou restreint.
        foreach (['enroll', 'redeem', 'sync', 'pack', 'health'] as $route) {
            self::assertStringContainsString(
                "'/api/$route.php'",
                $app,
                "Le scanner doit appeler /api/$route.php"
            );
        }
    }

    // ------------------------------------------ Installation en sous-dossier

    public function testLesVuesDeLAdminNEmettentAucuneUrlPartantDeLaRacineDuSite(): void
    {
        // Une URL absolue comme href="/admin/?p=lots" sort de l'application des
        // qu'elle n'occupe pas la racine du site : sous htdocs/scannem/, le lien
        // mene sur /admin/ que le serveur ne trouve pas. Les vues doivent donc
        // toutes intercaler le prefixe.
        $vues = array_merge(
            [Config::rootPath('app/admin/layout.php'), Config::rootPath('app/admin/index.php')],
            glob(Config::rootPath('app/admin/pages/*.php')) ?: []
        );

        foreach ($vues as $vue) {
            self::assertDoesNotMatchRegularExpression(
                '#(href|action)="/(admin|scan|api)/#',
                (string) file_get_contents($vue),
                basename($vue) . ' doit prefixer ses liens (voir Scannem\\Url)'
            );
        }
    }

    public function testLeRouteurRetireLePrefixeAvantDeChoisirLaRoute(): void
    {
        $source = (string) file_get_contents(Config::rootPath('public/index.php'));

        self::assertStringContainsString(
            'Url::strip(',
            $source,
            'Sans cela, /scannem/admin ne correspond a aucune route et le routeur'
            . ' repond sa propre page « introuvable »'
        );
    }

    public function testLeScannerPrefixeSesAppels(): void
    {
        $app = (string) file_get_contents(Config::rootPath('public/scan/app.js'));

        self::assertStringContainsString(
            'fetch(BASE + route',
            $app,
            'Les appels API doivent partir du dossier de l installation, pas de'
            . ' la racine du site'
        );

        $sw = (string) file_get_contents(Config::rootPath('public/scan/sw.js'));

        self::assertStringContainsString(
            'self.location.pathname',
            $sw,
            'Le service worker doit deduire son prefixe de son propre emplacement'
        );

        // Une entree de la coquille ecrite en dur ne serait mise en cache qu a
        // la racine du site : ailleurs, addAll echoue et l installation du
        // service worker est abandonnee — plus de demarrage hors reseau, sans le
        // moindre message.
        self::assertDoesNotMatchRegularExpression(
            "#^\s+'/scan/#m",
            $sw,
            'Les entrees de la coquille doivent etre prefixees'
        );
    }

    public function testLeManifestePwaUtiliseDesCheminsRelatifs(): void
    {
        $manifeste = json_decode(
            (string) file_get_contents(Config::rootPath('public/scan/manifest.json')),
            true
        );

        self::assertIsArray($manifeste);

        // Relatifs au manifeste lui-meme : ils designent le bon dossier quel que
        // soit le prefixe d'installation.
        foreach (['start_url', 'scope'] as $clef) {
            self::assertStringStartsWith('.', (string) $manifeste[$clef], "$clef doit etre relatif");
        }

        self::assertStringStartsWith('.', (string) $manifeste['icons'][0]['src']);
    }

    public function testLaPageScannerNeChargeQueDesFichiersRelatifs(): void
    {
        $html = (string) file_get_contents(Config::rootPath('public/scan/index.html'));

        self::assertDoesNotMatchRegularExpression(
            '#(src|href)="/#',
            $html,
            'La page du scanner est servie sous <prefixe>/scan/ : ses ressources'
            . ' doivent etre designees relativement'
        );
    }

    // ---------------------------------------------------- Lanceur Windows

    public function testLeLanceurWindowsNeRetientQuUnPhpAssezRecent(): void
    {
        $source = (string) file_get_contents(Config::rootPath('bin/build-release.php'));

        // Le lanceur interroge PHP lui-meme plutot que de lire un numero dans un
        // nom de dossier : WampServer nomme les siens librement, et un PHP 8.0
        // retenu par erreur ramenerait la page blanche que tout ceci evite.
        self::assertStringContainsString(
            "version_compare(PHP_VERSION,'8.1','ge')",
            $source,
            'Le lanceur doit demander sa version a chaque binaire candidat'
        );

        // < et >= sont des operateurs de redirection pour cmd.exe : les employer
        // dans le test de version enverrait la sortie dans un fichier au lieu de
        // comparer quoi que ce soit.
        self::assertStringNotContainsString(
            'PHP_VERSION_ID <',
            $source,
            'Le test de version ne doit pas contenir de caractere de redirection'
        );
    }

    public function testLeLanceurWindowsChercheDAbordAuOnLAPose(): void
    {
        $source = (string) file_get_contents(Config::rootPath('bin/build-release.php'));

        // Depose dans <wamp>\www\scannem, le dossier des PHP de WampServer est
        // <wamp>\bin\php. Partir de la plutot que d'un C:\wamp64 ecrit en dur,
        // c'est fonctionner quand WampServer est sur un autre disque.
        self::assertStringContainsString(
            '"%~dp0..\\..\\bin\\php"',
            $source,
            'Le lanceur doit chercher relativement a son propre emplacement'
        );
    }

    public function testLeLanceurWindowsNOuvrePasLeNavigateurTropTot(): void
    {
        $source = (string) file_get_contents(Config::rootPath('bin/build-release.php'));

        // Le serveur integre bloque la fenetre qui le lance : l'ouverture doit
        // venir d'ailleurs, sinon le navigateur arrive avant que le port ne soit
        // ouvert et affiche ERR_CONNECTION_REFUSED sur une installation saine.
        self::assertStringContainsString(
            'start "" /min "%~f0" --ouvrir',
            $source,
            'Le lanceur doit differer l ouverture du navigateur'
        );

        self::assertStringContainsString(
            'ping -n 4 127.0.0.1',
            $source,
            'Le detour doit laisser au serveur le temps d ouvrir son port'
        );
    }

    public function testLeLanceurWindowsResteEnAsciiEtEnCrLf(): void
    {
        $source = (string) file_get_contents(Config::rootPath('bin/build-release.php'));

        // Un .bat en fins de ligne Unix est execute de travers par cmd.exe.
        self::assertStringContainsString(
            'str_replace("\n", "\r\n"',
            $source,
            'Le lanceur doit partir en fins de ligne Windows'
        );

        self::assertSame(
            1,
            preg_match("/<<<'BAT'\n(.*?)\nBAT\)/s", $source, $trouve),
            'Le corps du lanceur doit etre un heredoc BAT'
        );

        // La console Windows n'est pas en UTF-8 : un accent y sort en charabia.
        self::assertSame(
            $trouve[1],
            (string) preg_replace('/[^\x09\x0a\x20-\x7e]/', '', $trouve[1]),
            'Le lanceur ne doit contenir aucun caractere hors ASCII'
        );
    }

    // ------------------------------------------- Diagnostic avant le chargement

    public function testLesPointsDEntreePassentParLAmorce(): void
    {
        // L'amorce refuse poliment un PHP trop ancien. Sans elle, le chargement
        // de la premiere classe de src/ produit une erreur d'analyse — page
        // blanche ou HTTP 500 sans la moindre explication.
        $entrees = [
            'public/index.php',
            'public/install.php',
            'app/admin/index.php',
            'app/api/bootstrap.php',
        ];

        foreach ($entrees as $entree) {
            self::assertStringContainsString(
                'scannem_amorcer(',
                (string) file_get_contents(Config::rootPath($entree)),
                "$entree doit passer par app/amorce.php"
            );
        }
    }

    public function testLAmorceResteLisibleParUnPhpAncien(): void
    {
        $source = (string) file_get_contents(Config::rootPath('app/amorce.php'));

        // Elle s'execute AVANT de savoir si la version de PHP convient : la
        // moindre syntaxe recente la rendrait illisible par le moteur qu'elle
        // est justement chargee de signaler.
        foreach (['readonly ', ': never', 'match (', '?->', 'str_starts_with('] as $syntaxe) {
            self::assertStringNotContainsString(
                $syntaxe,
                $source,
                "app/amorce.php doit rester lisible par un PHP ancien (trouve : $syntaxe)"
            );
        }

        self::assertStringContainsString('PHP_VERSION_ID < 80100', $source);
    }

    public function testLeScannerNEmbarqueAucunAppelVersUnCdn(): void
    {
        $sources = ['public/scan/app.js', 'public/scan/index.html', 'public/scan/sw.js'];

        foreach ($sources as $fichier) {
            $contenu = (string) file_get_contents(Config::rootPath($fichier));

            // Le scanner doit demarrer sans reseau : tout doit etre servi en local.
            self::assertDoesNotMatchRegularExpression(
                '#(src|href)=["\']https?://#i',
                $contenu,
                "$fichier ne doit dependre d'aucune ressource distante"
            );
        }
    }
}
