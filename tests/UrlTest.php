<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PHPUnit\Framework\TestCase;
use Scannem\Url;

/**
 * Installation ailleurs qu'a la racine du site.
 *
 * Le symptome que ces tests empechent de revenir : depose dans htdocs/scannem/,
 * Scannem affichait sa propre page « introuvable » sur sa page d'accueil, et le
 * lien « Scanner » menait sur http://localhost/scan/ — la page 404 d'Apache.
 * Toutes les URL etaient absolues et repartaient donc de la racine du site.
 */
final class UrlTest extends TestCase
{
    protected function setUp(): void
    {
        Url::reset();
    }

    protected function tearDown(): void
    {
        Url::reset();
        unset($_SERVER['SCRIPT_NAME']);
    }

    // ------------------------------------------------- Deduction du prefixe

    public function testALaRacineLePrefixeEstVide(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        self::assertSame('', Url::fromScript(0));
    }

    public function testDansUnSousDossierLePrefixeEstCeSousDossier(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/scannem/index.php';

        self::assertSame('/scannem', Url::fromScript(0));
    }

    public function testUnRelaisRemonteDUnDossier(): void
    {
        // Ce que sert Apache pour /scannem/admin/ : le vrai fichier du relais.
        $_SERVER['SCRIPT_NAME'] = '/scannem/admin/index.php';

        self::assertSame('/scannem', Url::fromScript(1));
    }

    public function testUnRelaisALaRacineDonneUnPrefixeVide(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/api/redeem.php';

        self::assertSame('', Url::fromScript(1));
    }

    public function testLesSousDossiersImbriquesSontConserves(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/clients/soiree/api/redeem.php';

        self::assertSame('/clients/soiree', Url::fromScript(1));
    }

    public function testLeSeparateurWindowsEstAccepte(): void
    {
        // Certaines configurations d'Apache sous Windows remontent des
        // antislashs dans SCRIPT_NAME.
        $_SERVER['SCRIPT_NAME'] = '\\scannem\\admin\\index.php';

        self::assertSame('/scannem', Url::fromScript(1));
    }

    // ------------------------------------------------------- Fabrication d'URL

    public function testLesUrlSontPrefixees(): void
    {
        Url::setBase('/scannem');

        self::assertSame('/scannem/scan/', Url::to('/scan/'));
        self::assertSame('/scannem/admin/?p=lots', Url::to('/admin/?p=lots'));
        self::assertSame('/scannem/api/redeem.php', Url::to('api/redeem.php'));
    }

    public function testALaRacineLesUrlSontInchangees(): void
    {
        Url::setBase('');

        self::assertSame('/scan/', Url::to('/scan/'));
        self::assertSame('/admin/?p=lots', Url::to('/admin/?p=lots'));
    }

    public function testUnPrefixeMalFormeEstNormalise(): void
    {
        // Un slash final doublerait les slashs de toutes les URL produites,
        // et dirname() renvoie '.' ou '/' selon les cas.
        foreach (['/scannem/', 'scannem', '/scannem'] as $brut) {
            Url::setBase($brut);
            self::assertSame('/scannem', Url::base(), "prefixe brut : $brut");
        }

        foreach (['/', '.', '', '  '] as $brut) {
            Url::setBase($brut);
            self::assertSame('', Url::base(), "prefixe brut : '$brut'");
        }
    }

    // ---------------------------------------------- Lecture du chemin demande

    public function testLeRouteurRaisonneSurDesCheminsInternes(): void
    {
        Url::setBase('/scannem');

        self::assertSame('/admin', Url::strip('/scannem/admin'));
        self::assertSame('/api/redeem.php', Url::strip('/scannem/api/redeem.php'));
        self::assertSame('/', Url::strip('/scannem'));
        self::assertSame('/', Url::strip('/scannem/'));
    }

    public function testUnCheminQuiCommencePareilMaisDiffereNEstPasTronque(): void
    {
        Url::setBase('/scan');

        // /scannem n'est pas une page de l'installation servie sous /scan.
        self::assertSame('/scannem', Url::strip('/scannem'));
    }

    public function testSansPrefixeLeCheminNeBougePas(): void
    {
        Url::setBase('');

        self::assertSame('/admin', Url::strip('/admin'));
        self::assertSame('/', Url::strip(''));
    }
}
