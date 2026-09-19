<?php
declare(strict_types=1);

/**
 * Routeur : associe « méthode HTTP + chemin » à [Contrôleur, méthode].
 *
 * Les paramètres se déclarent {id} (entier) ou {slug} (segment libre).
 * Un paramètre {id} n'accepte que des chiffres : une URL comme /upd/abc
 * ne peut donc plus atteindre un contrôleur typé « int » et produire une
 * erreur 500 — elle répond 404, comme attendu.
 */
final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    private string $prefixe;

    public function __construct(string $urlBase = '')
    {
        $chemin = parse_url(rtrim($urlBase, '/'), PHP_URL_PATH);
        $this->prefixe = is_string($chemin) ? rtrim($chemin, '/') : '';
    }

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $route, array $action): void
    {
        $this->routes['GET'][$route] = $action;
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $route, array $action): void
    {
        $this->routes['POST'][$route] = $action;
    }

    public function cheminDemande(): string
    {
        $uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = is_string($uri) ? rawurldecode($uri) : '/';

        if ($this->prefixe !== '' && str_starts_with($uri, $this->prefixe)) {
            $uri = substr($uri, strlen($this->prefixe));
        }

        $uri = '/' . trim($uri, '/');

        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public function dispatch(): void
    {
        $methode = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $methode = $methode === 'HEAD' ? 'GET' : $methode;
        $uri = $this->cheminDemande();

        if (!isset($this->routes[$methode])) {
            $this->methodeNonAutorisee();
            return;
        }

        $routes = $this->routes[$methode];

        if (isset($routes[$uri])) {
            $this->executer($routes[$uri], []);
            return;
        }

        foreach ($routes as $motif => $action) {
            $params = $this->confronter($motif, $uri);
            if ($params !== null) {
                $this->executer($action, $params);
                return;
            }
        }

        // Le chemin existe-t-il avec une autre méthode HTTP ?
        foreach ($this->routes as $autreMethode => $autresRoutes) {
            if ($autreMethode === $methode) {
                continue;
            }
            foreach ($autresRoutes as $motif => $_) {
                if ($motif === $uri || $this->confronter($motif, $uri) !== null) {
                    $this->methodeNonAutorisee();
                    return;
                }
            }
        }

        ErreurHttp::afficher(404);
    }

    /**
     * Compare un motif de route à l'URI demandée.
     *
     * @return list<int|string>|null Paramètres extraits, ou null si aucune correspondance.
     */
    private function confronter(string $motif, string $uri): ?array
    {
        if (!str_contains($motif, '{')) {
            return $motif === $uri ? [] : null;
        }

        // Les paramètres sont d'abord remplacés par une marque neutre, pour que
        // preg_quote() n'échappe pas les accolades et ne casse pas le motif.
        $types = [];
        $marque = preg_replace_callback(
            '#\{([a-z_]+)\}#',
            static function (array $m) use (&$types): string {
                $types[] = $m[1];
                return 'ZzPARAM' . (count($types) - 1) . 'PARAMzZ';
            },
            $motif
        );

        $regex = preg_quote((string)$marque, '#');
        foreach ($types as $index => $type) {
            // {id} : entier de 1 à 9 chiffres — jamais de débordement en int.
            $regex = str_replace(
                'ZzPARAM' . $index . 'PARAMzZ',
                $type === 'id' ? '(\d{1,9})' : '([^/]+)',
                $regex
            );
        }

        if (!preg_match('#^' . $regex . '$#u', $uri, $correspondances)) {
            return null;
        }

        $params = [];
        foreach (array_slice($correspondances, 1) as $i => $valeur) {
            $params[] = ($types[$i] ?? '') === 'id' ? (int)$valeur : $valeur;
        }

        return $params;
    }

    /** @param array{0: class-string, 1: string} $action */
    private function executer(array $action, array $params): void
    {
        [$classe, $methode] = $action;

        if (!class_exists($classe) || !method_exists($classe, $methode)) {
            throw new RuntimeException("Action de route introuvable : {$classe}::{$methode}()");
        }

        (new $classe())->{$methode}(...$params);
    }

    private function methodeNonAutorisee(): void
    {
        header('Allow: GET, POST');
        ErreurHttp::afficher(405);
    }
}
