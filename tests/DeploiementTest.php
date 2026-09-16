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
