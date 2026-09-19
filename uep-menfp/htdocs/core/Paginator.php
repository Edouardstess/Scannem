<?php
declare(strict_types=1);

/**
 * Pagination des listes. Une liste qui grandit ne doit jamais devenir une page
 * de plusieurs milliers de lignes.
 */
final class Paginator
{
    public readonly int $page;
    public readonly int $parPage;
    public readonly int $total;
    public readonly int $pages;
    public readonly int $offset;

    public function __construct(int $total, int $page, int $parPage = LIGNES_PAR_PAGE)
    {
        $this->total = max(0, $total);
        $this->parPage = max(1, $parPage);
        $this->pages = max(1, (int)ceil($this->total / $this->parPage));
        $this->page = min(max(1, $page), $this->pages);
        $this->offset = ($this->page - 1) * $this->parPage;
    }

    /** Numéro de page demandé, lu dans la query string et borné. */
    public static function pageDemandee(string $parametre = 'page'): int
    {
        $valeur = filter_input(INPUT_GET, $parametre, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        return is_int($valeur) ? $valeur : 1;
    }

    /** URL d'une page en conservant les filtres courants. */
    public function url(string $chemin, int $page): string
    {
        $parametres = $_GET;
        $parametres['page'] = $page;

        return URL_BASE . $chemin . '?' . http_build_query($parametres);
    }

    /** @return list<int> Fenêtre de numéros de page à afficher. */
    public function fenetre(int $rayon = 2): array
    {
        $debut = max(1, $this->page - $rayon);
        $fin = min($this->pages, $this->page + $rayon);

        return range($debut, $fin);
    }

    public function premierIndex(): int
    {
        return $this->total === 0 ? 0 : $this->offset + 1;
    }

    public function dernierIndex(): int
    {
        return min($this->offset + $this->parPage, $this->total);
    }
}
