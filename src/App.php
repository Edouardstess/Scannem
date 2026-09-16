<?php

declare(strict_types=1);

namespace Scannem;

use PDO;

/**
 * Point d'assemblage : une seule fabrique pour tous les services.
 */
final class App
{
    private static ?self $instance = null;

    private ?CardRepository $cards = null;
    private ?Auth $auth = null;
    private ?RateLimiter $limiter = null;
    private ?OfflinePack $pack = null;
    private ?Token $token = null;

    private function __construct(
        private readonly Config $config,
        private readonly PDO $pdo,
    ) {
    }

    public static function boot(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $config = Config::load();

        return self::$instance = new self($config, Db::connect($config));
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function token(): Token
    {
        return $this->token ??= Token::fromConfig($this->config);
    }

    public function cards(): CardRepository
    {
        return $this->cards ??= new CardRepository($this->pdo, $this->token());
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this->pdo, $this->config);
    }

    public function limiter(): RateLimiter
    {
        return $this->limiter ??= RateLimiter::fromConfig($this->pdo, $this->config);
    }

    public function offlinePack(): OfflinePack
    {
        return $this->pack ??= new OfflinePack($this->pdo);
    }

    /**
     * Exige un appareil enrole et applique les quotas.
     *
     * Toute route qui touche aux cartes passe par ici. Sans jeton, l'API serait
     * une telecommande pour bruler les cartes des autres a distance.
     *
     * @return array<string,mixed> l'appareil authentifie
     */
    public function requireDevice(): array
    {
        $ip = Http::clientIp();
        $limiter = $this->limiter();

        // Quota par IP d'abord : il s'applique meme sans jeton valide, sinon on
        // offrirait un banc d'essai gratuit pour deviner des jetons.
        if ($ip !== null && !$limiter->allow('ip:' . $ip, $this->config->int('rate_limit_max_per_ip', 240))) {
            header('Retry-After: ' . $limiter->retryAfter());
            Http::error('Trop de requetes depuis cette connexion.', 429, ScanResult::RATE_LIMITED);
        }

        $device = $this->auth()->deviceFromToken(Auth::tokenFromRequest());

        if ($device === null) {
            // Message uniforme : on ne dit pas si le jeton est inconnu ou desactive.
            Http::error('Appareil non autorise. Enrole-le depuis l interface d administration.', 401, 'unauthorized');
        }

        $deviceKey = 'device:' . $device['id'];

        if (!$limiter->allow($deviceKey, $this->config->int('rate_limit_max_per_device', 120))) {
            header('Retry-After: ' . $limiter->retryAfter());
            Http::error('Trop de scans sur cet appareil, patiente quelques secondes.', 429, ScanResult::RATE_LIMITED);
        }

        return $device;
    }
}
