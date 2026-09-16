<?php

declare(strict_types=1);

namespace Scannem;

use PDO;
use RuntimeException;

/**
 * Authentification des appareils de controle et de l'organisateur.
 *
 * Sans cette couche, l'API de validation serait ouverte : quiconque devine ou
 * photographie un uid pourrait appeler redeem a distance et BRULER la carte
 * avant que son porteur ne se presente. Le jeton d'appareil est donc obligatoire
 * sur toutes les routes qui touchent aux cartes.
 */
final class Auth
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Config $config,
    ) {
    }

    // ---------------------------------------------------------------- Appareils

    /**
     * Cree un code d'enrolement a usage unique, a communiquer au vigile.
     *
     * Le code est affiche une seule fois dans l'admin : seul son hachage est
     * conserve, exactement comme un mot de passe.
     */
    public function createEnrollCode(string $label): string
    {
        $label = trim($label) !== '' ? trim($label) : 'Appareil';

        // Format lisible a voix haute et a recopier : 3 groupes de 4.
        $raw = strtoupper(bin2hex(random_bytes(6)));
        $code = implode('-', str_split($raw, 4));

        $expires = gmdate('Y-m-d\TH:i:s\Z', time() + $this->config->int('enroll_code_ttl', 900));

        $this->pdo->prepare(
            'INSERT INTO enroll_codes (code_hash, label, expires_at, created_at) VALUES (?, ?, ?, ?)'
        )->execute([self::hash($code), $label, $expires, Db::now()]);

        return $code;
    }

    /**
     * Echange un code d'enrolement contre un jeton d'appareil permanent.
     *
     * @return array{token:string, device_id:int, label:string}
     * @throws RuntimeException si le code est inconnu, expire ou deja consomme
     */
    public function enrollDevice(string $code): array
    {
        $code = strtoupper(trim($code));

        $stmt = $this->pdo->prepare('SELECT * FROM enroll_codes WHERE code_hash = ?');
        $stmt->execute([self::hash($code)]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException("Code d'enrolement inconnu.");
        }

        if ($row['used_at'] !== null) {
            throw new RuntimeException("Ce code a deja servi. Demande un nouveau code a l'organisateur.");
        }

        if (strcmp((string) $row['expires_at'], Db::now()) < 0) {
            throw new RuntimeException('Ce code a expire. Demande un nouveau code.');
        }

        // Consommation atomique : deux telephones ne peuvent pas utiliser le meme code.
        $consume = $this->pdo->prepare(
            'UPDATE enroll_codes SET used_at = ? WHERE id = ? AND used_at IS NULL'
        );
        $consume->execute([Db::now(), $row['id']]);

        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('Ce code vient d etre utilise par un autre appareil.');
        }

        $token = bin2hex(random_bytes(32));

        $this->pdo->prepare(
            'INSERT INTO devices (label, token_hash, active, created_at) VALUES (?, ?, 1, ?)'
        )->execute([$row['label'], self::hash($token), Db::now()]);

        return [
            'token' => $token,
            'device_id' => (int) $this->pdo->lastInsertId(),
            'label' => (string) $row['label'],
        ];
    }

    /**
     * Identifie un appareil a partir de son jeton.
     *
     * @return array<string,mixed>|null null si le jeton est inconnu ou l'appareil desactive
     */
    public function deviceFromToken(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        // La recherche porte sur le hachage, donc une fuite de la base ne livre
        // aucun jeton utilisable.
        $stmt = $this->pdo->prepare('SELECT * FROM devices WHERE token_hash = ? AND active = 1');
        $stmt->execute([self::hash($token)]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        // « Vu pour la derniere fois » n'est que de l'affichage pour l'admin. Le
        // rafraichir a chaque requete couterait une ecriture par scan, et sous
        // SQLite toute ecriture prend le verrou global de la base. On ne le
        // touche donc qu'une fois par minute et par appareil : la ligne est
        // deja lue ci-dessus, la decision ne coute aucune requete de plus.
        if (self::doitRafraichirVu($row['last_seen_at'] ?? null)) {
            $this->pdo->prepare('UPDATE devices SET last_seen_at = ? WHERE id = ?')
                ->execute([Db::now(), $row['id']]);
        }

        return $row;
    }

    /** Intervalle minimal entre deux rafraichissements de last_seen_at, en secondes. */
    private const VU_INTERVALLE = 60;

    public static function doitRafraichirVu(?string $dernierVu): bool
    {
        if ($dernierVu === null || $dernierVu === '') {
            return true;
        }

        $vu = strtotime($dernierVu);

        // Horodatage illisible : on reecrit pour repartir sur une valeur saine.
        return $vu === false || (time() - $vu) >= self::VU_INTERVALLE;
    }

    /** Lit le jeton d'appareil dans l'en-tete Authorization ou X-Device-Token. */
    public static function tokenFromRequest(): ?string
    {
        $headers = [
            $_SERVER['HTTP_AUTHORIZATION'] ?? '',
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        ];

        foreach ($headers as $header) {
            if (is_string($header) && preg_match('/Bearer\s+([A-Za-z0-9]+)/', $header, $m) === 1) {
                return $m[1];
            }
        }

        $direct = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? null;

        return is_string($direct) && $direct !== '' ? $direct : null;
    }

    public function deactivateDevice(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE devices SET active = 0 WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    public function reactivateDevice(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE devices SET active = 1 WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    /** @return list<array<string,mixed>> */
    public function devices(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM devices ORDER BY id DESC');

        return $stmt === false ? [] : $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> Codes en attente, non expires. */
    public function pendingEnrollCodes(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM enroll_codes WHERE used_at IS NULL AND expires_at > ? ORDER BY id DESC'
        );
        $stmt->execute([Db::now()]);

        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------- Organisateur

    public function createAdmin(string $username, string $password): int
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException("Le nom d'utilisateur est obligatoire.");
        }

        if (strlen($password) < 10) {
            throw new RuntimeException('Le mot de passe doit faire au moins 10 caracteres.');
        }

        $this->pdo->prepare(
            'INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)'
        )->execute([$username, password_hash($password, PASSWORD_DEFAULT), Db::now()]);

        return (int) $this->pdo->lastInsertId();
    }

    public function verifyAdmin(string $username, string $password): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Hachage a vide malgre tout : le temps de reponse ne doit pas reveler
            // si le compte existe.
            password_verify($password, '$2y$12$usuallyinvalidhashusuallyinvalidhashusuallyinvalidhashuuuuu');

            return null;
        }

        if (!password_verify($password, (string) $row['password_hash'])) {
            return null;
        }

        return $row;
    }

    public function adminCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }

    /** SHA-256 : les jetons et codes sont deja aleatoires, pas besoin de bcrypt. */
    private static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
