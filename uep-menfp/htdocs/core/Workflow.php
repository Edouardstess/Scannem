<?php
declare(strict_types=1);

/**
 * Circuit de validation des dossiers UPD et DDE.
 *
 *     brouillon ──soumettre──► soumis ──valider──► validé
 *          ▲                     │
 *          │                     └──rejeter──► rejeté ──soumettre──► soumis
 *          └────────────── rouvrir (administrateur) ────────────────┘
 *
 * Un dossier n'est modifiable qu'en « brouillon » ou « rejeté ». Cette règle
 * est appliquée par le contrôleur, pas seulement par l'affichage.
 */
final class Workflow
{
    public const STATUTS = ['brouillon', 'soumis', 'valide', 'rejete'];

    private const MODIFIABLES = ['brouillon', 'rejete'];

    private const TRANSITIONS = [
        'soumettre' => [
            'depuis'  => ['brouillon', 'rejete'],
            'vers'    => 'soumis',
            'roles'   => ['administrateur', 'saisisseur'],
            'libelle' => 'Soumettre pour validation',
            'icone'   => 'bi-send',
            'classe'  => 'btn-primary',
            'confirm' => 'Soumettre ce dossier pour validation ? Il ne sera plus modifiable.',
        ],
        'valider' => [
            'depuis'  => ['soumis'],
            'vers'    => 'valide',
            'roles'   => ['administrateur', 'superviseur'],
            'libelle' => 'Valider le dossier',
            'icone'   => 'bi-check2-circle',
            'classe'  => 'btn-success',
            'confirm' => 'Valider définitivement ce dossier ?',
        ],
        'rejeter' => [
            'depuis'  => ['soumis'],
            'vers'    => 'rejete',
            'roles'   => ['administrateur', 'superviseur'],
            'libelle' => 'Rejeter',
            'icone'   => 'bi-x-circle',
            'classe'  => 'btn-danger',
            'confirm' => 'Rejeter ce dossier et le renvoyer en correction ?',
        ],
        'rouvrir' => [
            'depuis'  => ['soumis', 'valide', 'rejete'],
            'vers'    => 'brouillon',
            'roles'   => ['administrateur'],
            'libelle' => 'Rouvrir en brouillon',
            'icone'   => 'bi-arrow-counterclockwise',
            'classe'  => 'btn-outline',
            'confirm' => 'Rouvrir ce dossier ? Il redeviendra modifiable.',
        ],
    ];

    public static function statutValide(?string $statut): string
    {
        return in_array($statut, self::STATUTS, true) ? (string)$statut : 'brouillon';
    }

    public static function libelle(?string $statut): string
    {
        return match (self::statutValide($statut)) {
            'soumis' => 'Soumis pour validation',
            'valide' => 'Validé',
            'rejete' => 'Rejeté',
            default  => 'Brouillon',
        };
    }

    public static function libelleCourt(?string $statut): string
    {
        return match (self::statutValide($statut)) {
            'soumis' => 'Soumis',
            'valide' => 'Validé',
            'rejete' => 'Rejeté',
            default  => 'Brouillon',
        };
    }

    public static function classeBadge(?string $statut): string
    {
        return 'badge-statut badge-' . self::statutValide($statut);
    }

    public static function peutModifier(?string $statut, ?string $role): bool
    {
        return in_array($role, ['administrateur', 'saisisseur'], true)
            && in_array(self::statutValide($statut), self::MODIFIABLES, true);
    }

    /** @return array<string, array<string, mixed>> */
    public static function actionsDisponibles(?string $statut, ?string $role): array
    {
        $statut = self::statutValide($statut);
        $actions = [];

        foreach (self::TRANSITIONS as $nom => $regle) {
            if (in_array($statut, $regle['depuis'], true) && in_array($role, $regle['roles'], true)) {
                $actions[$nom] = $regle;
            }
        }

        return $actions;
    }

    /** Statut cible d'une action, ou null si l'action est interdite ici. */
    public static function cible(string $action, ?string $statut, ?string $role): ?string
    {
        $actions = self::actionsDisponibles($statut, $role);
        return isset($actions[$action]) ? (string)$actions[$action]['vers'] : null;
    }

    /**
     * Applique la transition. Le nom de table ne vient jamais de la requête :
     * il est fourni par le contrôleur et vérifié ici.
     */
    public static function appliquer(string $table, int $id, string $statut): void
    {
        if (!in_array($table, ['institutions_upd', 'institutions_dde'], true)) {
            throw new InvalidArgumentException('Table de workflow inconnue.');
        }
        if (!in_array($statut, self::STATUTS, true)) {
            throw new InvalidArgumentException('Statut de workflow inconnu.');
        }

        Database::pdo()
            ->prepare("UPDATE {$table} SET statut_validation = :statut WHERE id = :id")
            ->execute([':statut' => $statut, ':id' => $id]);
    }
}
