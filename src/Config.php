<?php

declare(strict_types=1);

namespace Scannem;

use RuntimeException;

/**
 * Configuration de l'application.
 *
 * Le fichier de configuration vit dans storage/config.php, HORS du webroot et hors
 * du depot git. Il contient le secret HMAC qui signe les QR : si ce secret fuite,
 * n'importe qui peut fabriquer des cartes valides et tout le systeme s'effondre.
 */
final class Config
{
    private const DEFAULTS = [
        // 'sqlite' ou 'mysql'
        'db_driver' => 'sqlite',
        'db_path' => null,          // sqlite : chemin du fichier
        'db_host' => '127.0.0.1',   // mysql
        'db_port' => 3306,
        'db_name' => 'scannem',
        'db_user' => 'root',
        'db_pass' => '',

        // Clefs de signature : ['A' => '<secret hex>', ...]. La clef active sert
        // aux nouvelles cartes ; les anciennes restent verifiables tant que leur
        // key_id figure ici. C'est ce qui permet une rotation sans reimprimer.
        'keys' => [],
        'active_key_id' => 'A',

        // Quotas anti-abus (fenetre glissante en secondes)
        'rate_limit_window' => 60,
        'rate_limit_max_per_device' => 120,
        'rate_limit_max_per_ip' => 240,

        // Nom affiche dans l'interface
        'app_name' => 'Scannem',

        // Duree de validite d'un code d'enrolement d'appareil (secondes)
        'enroll_code_ttl' => 900,

        // Forcer les cookies "secure" (a laisser a true en production HTTPS)
        'cookie_secure' => false,
    ];

    /** @var array<string,mixed> */
    private array $values;

    /** @param array<string,mixed> $values */
    public function __construct(array $values = [])
    {
        $this->values = array_merge(self::DEFAULTS, $values);

        if ($this->values['db_path'] === null) {
            $this->values['db_path'] = self::storagePath('scannem.sqlite');
        }
    }

    public static function rootPath(string $relative = ''): string
    {
        $root = dirname(__DIR__);

        return $relative === '' ? $root : $root . '/' . ltrim($relative, '/');
    }

    public static function storagePath(string $relative = ''): string
    {
        return self::rootPath('storage' . ($relative === '' ? '' : '/' . ltrim($relative, '/')));
    }

    public static function configFile(): string
    {
        return self::storagePath('config.php');
    }

    /**
     * Charge la configuration depuis storage/config.php.
     *
     * @throws RuntimeException si l'application n'a pas encore ete installee
     */
    public static function load(): self
    {
        $file = self::configFile();

        if (!is_file($file)) {
            throw new RuntimeException(
                "Scannem n'est pas installe : " . $file . " est introuvable.\n"
                . "Lance : php bin/install.php"
            );
        }

        /** @var array<string,mixed> $values */
        $values = require $file;

        if (!is_array($values)) {
            throw new RuntimeException('storage/config.php doit retourner un tableau.');
        }

        return new self($values);
    }

    public static function exists(): bool
    {
        return is_file(self::configFile());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->values[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->values[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) ($this->values[$key] ?? $default);
    }

    /**
     * Trousseau de clefs de signature, en binaire brut.
     *
     * @return array<string,string>
     */
    public function keys(): array
    {
        $keys = $this->values['keys'] ?? [];

        if (!is_array($keys) || $keys === []) {
            throw new RuntimeException(
                "Aucune clef de signature configuree. Relance php bin/install.php."
            );
        }

        $out = [];
        foreach ($keys as $id => $hex) {
            $binary = @hex2bin((string) $hex);
            if ($binary === false || strlen($binary) < 32) {
                throw new RuntimeException("Clef de signature '$id' invalide (attendu : >= 64 caracteres hex).");
            }
            $out[(string) $id] = $binary;
        }

        return $out;
    }

    public function activeKeyId(): string
    {
        $id = $this->str('active_key_id', 'A');

        if (!array_key_exists($id, $this->keys())) {
            throw new RuntimeException("La clef active '$id' n'existe pas dans le trousseau.");
        }

        return $id;
    }

    /**
     * Ecrit le fichier de configuration avec des permissions restrictives.
     *
     * @param array<string,mixed> $values
     */
    public static function write(array $values): void
    {
        $dir = self::storagePath();

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de creer $dir");
        }

        $export = var_export($values, true);
        $php = "<?php\n\n// Genere par bin/install.php - NE PAS COMMITER, NE PAS EXPOSER AU WEB.\n"
            . "// Ce fichier contient le secret qui signe les QR codes.\n\n"
            . "return $export;\n";

        $file = self::configFile();

        if (file_put_contents($file, $php, LOCK_EX) === false) {
            throw new RuntimeException("Impossible d'ecrire $file");
        }

        @chmod($file, 0600);
    }
}
