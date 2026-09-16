<?php

declare(strict_types=1);

namespace Scannem\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Scannem\Token;

final class TokenTest extends TestCase
{
    private const KEY_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const KEY_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private function token(): Token
    {
        return new Token(['A' => self::KEY_A, 'B' => self::KEY_B], 'A');
    }

    public function testUnJetonFraichementGenereEstValide(): void
    {
        $token = $this->token();
        $uid = Token::newUid();

        $payload = $token->build($uid);
        $result = $token->verify($payload);

        self::assertTrue($result['ok']);
        self::assertSame($uid, $result['uid']);
        self::assertSame('A', $result['key_id']);
    }

    public function testLeFormatEstCompactEtLisible(): void
    {
        $payload = $this->token()->build(Token::newUid());

        // SCN1A. + 16 + . + 16 = 39 caracteres -> QR version 3, lisible imprime petit.
        self::assertSame(39, strlen($payload));
        self::assertMatchesRegularExpression('/^SCN1A\.[0-9A-Z]{16}\.[0-9A-Z]{16}$/', $payload);
    }

    public function testUnPayloadAltereEstRejete(): void
    {
        $token = $this->token();
        $payload = $token->build(Token::newUid());

        // On modifie un seul caractere de l'uid.
        $position = 6;
        $original = $payload[$position];
        $payload[$position] = $original === '0' ? '1' : '0';

        $result = $token->verify($payload);

        self::assertFalse($result['ok']);
        self::assertSame('signature', $result['reason']);
    }

    public function testUneSignatureInventeeEstRejetee(): void
    {
        $token = $this->token();
        $uid = Token::newUid();

        $result = $token->verify('SCN1A.' . $uid . '.ZZZZZZZZZZZZZZZZ');

        self::assertFalse($result['ok']);
        self::assertSame('signature', $result['reason']);
    }

    public function testUneSignatureTronqueeEstRejetee(): void
    {
        $token = $this->token();
        $payload = $token->build(Token::newUid());

        $result = $token->verify(substr($payload, 0, -1));

        self::assertFalse($result['ok']);
        self::assertSame('signature_format', $result['reason']);
    }

    public function testUneAutreClefNeValidePasLaSignature(): void
    {
        $token = $this->token();
        $uid = Token::newUid();

        // Signe avec B, mais on annonce la clef A : la signature ne correspond pas.
        $signeAvecB = $token->build($uid, 'B');
        $usurpe = 'SCN1A.' . $uid . '.' . substr($signeAvecB, -16);

        $result = $token->verify($usurpe);

        self::assertFalse($result['ok']);
        self::assertSame('signature', $result['reason']);
    }

    public function testUneClefRetireeDuTrousseauInvalideSesCartes(): void
    {
        $avecB = $this->token();
        $payload = $avecB->build(Token::newUid(), 'B');

        // Rotation : on retire la clef B du trousseau.
        $sansB = new Token(['A' => self::KEY_A], 'A');
        $result = $sansB->verify($payload);

        self::assertFalse($result['ok']);
        self::assertSame('clef_inconnue', $result['reason']);
    }

    public function testLesCartesDeLAncienneClefRestentValidesApresRotation(): void
    {
        $avant = new Token(['A' => self::KEY_A], 'A');
        $payload = $avant->build(Token::newUid());

        // La clef active devient B, mais A reste dans le trousseau.
        $apres = new Token(['A' => self::KEY_A, 'B' => self::KEY_B], 'B');

        self::assertTrue($apres->verify($payload)['ok']);
        self::assertSame('B', $apres->activeKeyId());
    }

    public function testUnPayloadEtrangerEstRejete(): void
    {
        $token = $this->token();

        foreach (['', 'bonjour', 'https://exemple.fr', 'SCN1A.TROPCOURT.X', '....'] as $entree) {
            $result = $token->verify($entree);
            self::assertFalse($result['ok'], "Aurait du etre rejete : $entree");
        }
    }

    public function testLaNormalisationRattrapeLesSaisiesImparfaites(): void
    {
        $token = $this->token();
        $payload = $token->build(Token::newUid());

        $variantes = [
            strtolower($payload),
            '  ' . $payload . "\n",
            'https://exemple.fr/s/' . $payload,
            'https://exemple.fr/s/' . $payload . '?src=nfc',
            implode(' ', str_split($payload, 4)),
        ];

        foreach ($variantes as $variante) {
            self::assertTrue($token->verify($variante)['ok'], "Echec sur : $variante");
        }
    }

    public function testLAlphabetEvitLesCaracteresAmbigus(): void
    {
        self::assertSame(32, strlen(Token::ALPHABET));
        self::assertSame(32, strlen(count_chars(Token::ALPHABET, 3)), 'Alphabet avec doublons');

        foreach (['I', 'L', 'O', 'U'] as $ambigu) {
            self::assertStringNotContainsString($ambigu, Token::ALPHABET);
        }
    }

    public function testLeBase32FaitLAllerRetour(): void
    {
        $echecs = [];

        for ($i = 0; $i < 500; $i++) {
            $bytes = random_bytes(10);
            $encoded = Token::base32Encode($bytes);

            if (strlen($encoded) !== 16 || Token::base32Decode($encoded) !== $bytes) {
                $echecs[] = bin2hex($bytes);
            }
        }

        self::assertSame([], $echecs, 'Aller-retour base32 rompu');
    }

    public function testLesUidNeSeRepetentPas(): void
    {
        $total = 50000;
        $vus = [];

        // On accumule sans assertion dans la boucle : 50 000 assertions PHPUnit
        // couteraient des minutes pour la meme information.
        for ($i = 0; $i < $total; $i++) {
            $vus[Token::newUid()] = true;
        }

        self::assertCount($total, $vus, 'Collision d uid detectee sur ' . $total . ' tirages');
    }

    public function testUnTrousseauInvalideEstRefuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Token(['A' => 'trop court'], 'A');
    }

    public function testUneClefActiveAbsenteEstRefusee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Token(['A' => self::KEY_A], 'Z');
    }

    public function testLesEmpreintesNeRevelentPasLUid(): void
    {
        $uid = Token::newUid();
        $empreinte = Token::fingerprint($uid);

        self::assertSame(16, strlen($empreinte));
        self::assertStringNotContainsString($uid, $empreinte);
        self::assertSame($empreinte, Token::fingerprint($uid), 'Empreinte non deterministe');
        self::assertNotSame($empreinte, Token::fingerprint(Token::newUid()));
    }
}
