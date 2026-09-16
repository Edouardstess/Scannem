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
    private ?RateLimiterInterface $limiter = null;
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

    public function limiter(): RateLimiterInterface
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
        $device = $this->auth()->deviceFromToken(Auth::tokenFromRequest());

        if ($device === null) {
            // Message uniforme : on ne dit pas si le jeton est inconnu ou desactive.
            Http::error('Appareil non autorise. Enrole-le depuis l interface d administration.', 401, 'unauthorized');
        }

        if (!$this->quotaAutorise((int) $device['id'])) {
            header('Retry-After: ' . $this->limiter()->retryAfter());
            Http::error('Trop de scans sur cet appareil, patiente quelques secondes.', 429, ScanResult::RATE_LIMITED);
        }

        return $device;
    }

    /**
     * Quota d'un appareil authentifie.
     *
     * Volontairement **sans seau par adresse IP**. A un evenement, toutes les
     * portes passent par le Wi-Fi du lieu ou un partage de connexion : elles
     * sortent donc sur une seule IP publique. Un quota applique a cette IP les
     * briderait collectivement — a douze portes, chacune n'aurait droit qu'a un
     * douzieme du plafond et les vigiles verraient des refus « trop de scans »
     * en pleine entree, sans cause visible.
     *
     * Le seau par appareil suffit : il est deja individuel, et l'appareil a du
     * etre enrole pour exister. Le seau par IP reste en place sur /api/enroll,
     * seule route ouverte sans jeton et donc reellement attaquable.
     *
     * Effet secondaire appreciable : une ecriture en base de moins par scan.
     */
    public function quotaAutorise(int $deviceId): bool
    {
        return $this->limiter()->allow(
            'device:' . $deviceId,
            $this->config->int('rate_limit_max_per_device', 120)
        );
    }
}
