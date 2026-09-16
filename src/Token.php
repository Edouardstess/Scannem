<?php

declare(strict_types=1);

namespace Scannem;

use InvalidArgumentException;

/**
 * Format du contenu d'un QR code.
 *
 *     SCN1<keyid>.<uid>.<signature>
 *     ex. SCN1A.K7M2QP4XRT9VB3ZC.H4N8DQ2WKF6YJM5A
 *
 * - uid : 10 octets tires de random_bytes() -> 16 caracteres en base32.
 * - signature : HMAC-SHA256 du prefixe + uid, tronque a 10 octets -> 16 caracteres.
 *
 * Ce que ca garantit : personne ne peut fabriquer un code valide sans le secret.
 * 80 bits de signature, c'est hors de portee d'une attaque par force brute.
 *
 * Ce que ca NE garantit PAS : qu'un code presente soit un original plutot qu'une
 * photo. Un QR imprime est un jeton au porteur. La detection de copie se fait
 * ailleurs, par l'usage unique cote serveur (voir CardRepository::redeem).
 *
 * Aucune donnee metier n'est placee dans le payload : l'uid est une valeur
 * aleatoire sans signification, tout le reste vit en base.
 */
final class Token
{
    public const PREFIX = 'SCN1';

    /** Longueur en octets de l'identifiant unique avant encodage. */
    public const UID_BYTES = 10;

    /** Longueur en octets de la signature conservee (80 bits). */
    public const SIG_BYTES = 10;

    /**
     * Alphabet base32 volontairement ampute de I, L, O et U.
     *
     * I/1, L/1 et O/0 se confondent a la lecture ; U est retire pour eviter de
     * former des mots involontaires. Il reste 32 caracteres, ce qu'il faut
     * exactement pour du base32, et la saisie manuelle de secours devient fiable.
     */
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @var array<string,string> Trousseau : key_id => secret binaire */
    private array $keys;

    private string $activeKeyId;

    /** @param array<string,string> $keys key_id => secret binaire (>= 32 octets) */
    public function __construct(array $keys, string $activeKeyId)
    {
        if ($keys === []) {
            throw new InvalidArgumentException('Trousseau de clefs vide.');
        }

        if (!array_key_exists($activeKeyId, $keys)) {
            throw new InvalidArgumentException("La clef active '$activeKeyId' est absente du trousseau.");
        }

        foreach ($keys as $id => $secret) {
            if (strlen($secret) < 32) {
                throw new InvalidArgumentException("La clef '$id' est trop courte (minimum 32 octets).");
            }
            if (strlen((string) $id) !== 1 || !str_contains(self::ALPHABET, (string) $id)) {
                throw new InvalidArgumentException(
                    "L'identifiant de clef '$id' doit etre un unique caractere de l'alphabet."
                );
            }
        }

        $this->keys = $keys;
        $this->activeKeyId = $activeKeyId;
    }

    public static function fromConfig(Config $config): self
    {
        return new self($config->keys(), $config->activeKeyId());
    }

    public function activeKeyId(): string
    {
        return $this->activeKeyId;
    }

    /** Tire un nouvel identifiant de carte depuis le generateur cryptographique. */
    public static function newUid(): string
    {
        return self::base32Encode(random_bytes(self::UID_BYTES));
    }

    /**
     * Construit le contenu complet du QR pour un uid donne.
     *
     * @param string|null $keyId clef a utiliser, ou null pour la clef active
     */
    public function build(string $uid, ?string $keyId = null): string
    {
        $keyId ??= $this->activeKeyId;

        if (!array_key_exists($keyId, $this->keys)) {
            throw new InvalidArgumentException("Clef inconnue : $keyId");
        }

        if (!self::isValidUid($uid)) {
            throw new InvalidArgumentException("uid invalide : $uid");
        }

        $body = self::PREFIX . $keyId . '.' . $uid;

        return $body . '.' . $this->signature($body, $keyId);
    }

    /**
     * Verifie un payload scanne et en extrait l'uid.
     *
     * @return array{ok:bool, uid:?string, key_id:?string, reason:?string}
     */
    public function verify(string $payload): array
    {
        $payload = self::normalize($payload);

        $parts = explode('.', $payload);

        if (count($parts) !== 3) {
            return self::rejected('format');
        }

        [$head, $uid, $sig] = $parts;

        if (!str_starts_with($head, self::PREFIX) || strlen($head) !== strlen(self::PREFIX) + 1) {
            return self::rejected('prefixe');
        }

        $keyId = substr($head, -1);

        if (!array_key_exists($keyId, $this->keys)) {
            // Clef revoquee ou payload invente : dans les deux cas, on refuse.
            return self::rejected('clef_inconnue');
        }

        if (!self::isValidUid($uid)) {
            return self::rejected('uid');
        }

        $expectedLength = (int) ceil(self::SIG_BYTES * 8 / 5);

        if (strlen($sig) !== $expectedLength || strspn($sig, self::ALPHABET) !== strlen($sig)) {
            return self::rejected('signature_format');
        }

        $expected = $this->signature($head . '.' . $uid, $keyId);

        // hash_equals et pas == : la comparaison doit etre a temps constant,
        // sinon la duree de reponse laisse deviner la signature octet par octet.
        if (!hash_equals($expected, $sig)) {
            return self::rejected('signature');
        }

        return ['ok' => true, 'uid' => $uid, 'key_id' => $keyId, 'reason' => null];
    }

    /**
     * Nettoie un payload avant analyse : espaces, minuscules, URL enveloppante.
     *
     * Un vigile peut avoir a saisir le code a la main si la camera refuse de
     * cooperer, et certains lecteurs renvoient le contenu avec des espaces.
     */
    public static function normalize(string $raw): string
    {
        $value = trim($raw);

        // Accepte aussi une URL du type https://exemple.fr/s/SCN1A.XXXX.YYYY
        if (str_contains($value, '/')) {
            $tail = substr($value, (int) strrpos($value, '/') + 1);
            if ($tail !== '') {
                $value = $tail;
            }
        }

        if (str_contains($value, '?')) {
            $value = strtok($value, '?') ?: $value;
        }

        $value = strtoupper($value);

        // Retire espaces et tirets de mise en forme, garde le point separateur.
        $value = (string) preg_replace('/[^0-9A-Z.]/', '', $value);

        // Confusions classiques a la saisie manuelle, alignees sur l'alphabet.
        return strtr($value, ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);
    }

    public static function isValidUid(string $uid): bool
    {
        $expected = (int) ceil(self::UID_BYTES * 8 / 5);

        return strlen($uid) === $expected && strspn($uid, self::ALPHABET) === strlen($uid);
    }

    private function signature(string $body, string $keyId): string
    {
        $raw = hash_hmac('sha256', $body, $this->keys[$keyId], true);

        return self::base32Encode(substr($raw, 0, self::SIG_BYTES));
    }

    /** @return array{ok:bool, uid:null, key_id:null, reason:string} */
    private static function rejected(string $reason): array
    {
        return ['ok' => false, 'uid' => null, 'key_id' => null, 'reason' => $reason];
    }

    /**
     * Encode en base32 sur l'alphabet maison, sans remplissage.
     *
     * Les implementations standard utilisent RFC 4648 (A-Z2-7), qui contient
     * I, L et O. On reimplemente pour garder notre alphabet sans confusion.
     */
    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Complete le dernier groupe pour qu'il fasse 5 bits.
        $remainder = strlen($bits) % 5;
        if ($remainder !== 0) {
            $bits .= str_repeat('0', 5 - $remainder);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $bits = '';
        $length = strlen($encoded);

        for ($i = 0; $i < $length; $i++) {
            $index = strpos(self::ALPHABET, $encoded[$i]);
            if ($index === false) {
                throw new InvalidArgumentException('Caractere hors alphabet : ' . $encoded[$i]);
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }

    /**
     * Empreinte publiee dans le pack hors-ligne.
     *
     * Le telephone du vigile recoit ces empreintes, jamais le secret HMAC ni les
     * uid en clair : une fuite du telephone ne permet donc pas de reconstruire
     * des QR valides.
     */
    public static function fingerprint(string $uid): string
    {
        return substr(hash('sha256', 'scannem-fp:' . $uid), 0, 16);
    }
}
