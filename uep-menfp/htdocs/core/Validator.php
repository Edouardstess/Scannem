<?php
declare(strict_types=1);

/**
 * Validation des données côté serveur. La validation JavaScript est un confort,
 * celle-ci est la seule qui protège réellement la base.
 */
final class Validator
{
    private array $donnees;

    /** @var array<string, string> */
    private array $erreurs = [];

    public function __construct(array $donnees)
    {
        $this->donnees = $donnees;
    }

    /** Valeur scalaire du champ, ou chaîne vide si absente ou non scalaire. */
    private function valeur(string $champ): string
    {
        $valeur = $this->donnees[$champ] ?? '';
        return is_scalar($valeur) ? trim((string)$valeur) : '';
    }

    private function estScalaire(string $champ): bool
    {
        $valeur = $this->donnees[$champ] ?? '';
        return is_scalar($valeur) || $valeur === null;
    }

    public function obligatoire(string $champ, string $message): self
    {
        if (!$this->estScalaire($champ) || $this->valeur($champ) === '') {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    public function email(string $champ, string $message): self
    {
        $valeur = $this->valeur($champ);
        if (!$this->estScalaire($champ) || ($valeur !== '' && !filter_var($valeur, FILTER_VALIDATE_EMAIL))) {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    public function longueurMin(string $champ, int $min, string $message): self
    {
        if (!$this->estScalaire($champ) || mb_strlen($this->valeur($champ), 'UTF-8') < $min) {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    public function longueurMax(string $champ, int $max, string $message): self
    {
        if (!$this->estScalaire($champ) || mb_strlen($this->valeur($champ), 'UTF-8') > $max) {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    public function entierPositif(string $champ, string $message): self
    {
        $valeur = $this->valeur($champ);
        if (!$this->estScalaire($champ) || ($valeur !== '' && filter_var($valeur, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)) {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    public function date(string $champ, string $message): self
    {
        $valeur = $this->valeur($champ);
        if ($valeur !== '' && !self::dateValide($valeur)) {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    public function dansListe(string $champ, array $valeurs, string $message): self
    {
        $valeur = $this->valeur($champ);
        if (!$this->estScalaire($champ) || ($valeur !== '' && !in_array($valeur, $valeurs, true))) {
            $this->ajouterErreur($champ, $message);
        }
        return $this;
    }

    /** Politique de mot de passe : longueur + minuscule + majuscule + chiffre. */
    public function motDePasse(string $champ, string $valeur): self
    {
        if (strlen($valeur) < LONGUEUR_MDP_MIN) {
            $this->ajouterErreur($champ, 'Le mot de passe doit contenir au moins ' . LONGUEUR_MDP_MIN . ' caractères.');
        } elseif (!preg_match('/[a-z]/', $valeur) || !preg_match('/[A-Z]/', $valeur) || !preg_match('/\d/', $valeur)) {
            $this->ajouterErreur($champ, 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.');
        }
        return $this;
    }

    public function ajouterErreur(string $champ, string $message): self
    {
        $this->erreurs[$champ] ??= $message;
        return $this;
    }

    public function echec(): bool
    {
        return $this->erreurs !== [];
    }

    /** @return array<string, string> */
    public function erreurs(): array
    {
        return $this->erreurs;
    }

    public function get(string $champ, mixed $defaut = null): mixed
    {
        $valeur = $this->donnees[$champ] ?? $defaut;
        return is_string($valeur) ? trim($valeur) : $valeur;
    }

    public static function dateValide(string $valeur): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $valeur;
    }

    /** Échappement HTML systématique des sorties (anti-XSS). */
    public static function echapper(mixed $valeur): string
    {
        if ($valeur === null || is_array($valeur) || is_object($valeur)) {
            return '';
        }
        return htmlspecialchars((string)$valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
