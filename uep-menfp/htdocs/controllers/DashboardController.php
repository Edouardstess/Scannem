<?php
declare(strict_types=1);

/** Tableau de bord : indicateurs, graphiques et dernières activités. */
final class DashboardController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::exigerConnexion();

        $pdo = Database::pdo();

        // --- Indicateurs -----------------------------------------------------
        $upd = $this->repartitionStatuts($pdo, 'institutions_upd');
        $dde = $this->repartitionStatuts($pdo, 'institutions_dde');

        $requisitionsParStatut = ['en_attente' => 0, 'approuvee' => 0, 'rejetee' => 0, 'livree' => 0];
        foreach ($pdo->query('SELECT statut, COUNT(*) AS nb FROM requisitions GROUP BY statut')->fetchAll() as $ligne) {
            $requisitionsParStatut[$ligne['statut']] = (int)$ligne['nb'];
        }

        $montantEngage = (float)$pdo->query(
            'SELECT COALESCE(SUM(a.montant_total), 0)
             FROM requisitions r
             JOIN requisition_articles a ON a.requisition_id = r.id
             WHERE r.statut IN (\'approuvee\', \'livree\')'
        )->fetchColumn();

        $montantEnAttente = (float)$pdo->query(
            'SELECT COALESCE(SUM(a.montant_total), 0)
             FROM requisitions r
             JOIN requisition_articles a ON a.requisition_id = r.id
             WHERE r.statut = \'en_attente\''
        )->fetchColumn();

        // --- Complétion moyenne des questionnaires ---------------------------
        $completionUpd = $this->completionMoyenne($pdo, 'upd');
        $completionDde = $this->completionMoyenne($pdo, 'dde');

        // --- Graphiques ------------------------------------------------------
        $parDepartement = $this->couvertureDepartementale($pdo);

        $graphiques = [
            'requisitions' => [
                'labels' => ['En attente', 'Approuvées', 'Rejetées', 'Livrées'],
                'valeurs' => [
                    $requisitionsParStatut['en_attente'],
                    $requisitionsParStatut['approuvee'],
                    $requisitionsParStatut['rejetee'],
                    $requisitionsParStatut['livree'],
                ],
                'couleurs' => ['#D97706', '#16A34A', '#C8102E', '#0B4F9E'],
            ],
            'departements' => [
                'labels'  => array_column($parDepartement, 'libelle'),
                'upd'     => array_map('intval', array_column($parDepartement, 'upd')),
                'dde'     => array_map('intval', array_column($parDepartement, 'dde')),
            ],
            'dossiers' => [
                'labels'  => ['Brouillon', 'Soumis', 'Validé', 'Rejeté'],
                'upd'     => [$upd['brouillon'], $upd['soumis'], $upd['valide'], $upd['rejete']],
                'dde'     => [$dde['brouillon'], $dde['soumis'], $dde['valide'], $dde['rejete']],
            ],
        ];

        // --- Listes récentes -------------------------------------------------
        $dernieresRequisitions = $pdo->query(
            'SELECT r.id, r.numero_requisition, r.objet, r.statut, r.priorite, r.date_demande,
                    u.nom_complet AS demandeur,
                    (SELECT COALESCE(SUM(a.montant_total), 0) FROM requisition_articles a WHERE a.requisition_id = r.id) AS montant_total
             FROM requisitions r
             JOIN utilisateurs u ON u.id = r.demandeur_id
             ORDER BY r.date_demande DESC, r.id DESC
             LIMIT 6'
        )->fetchAll();

        $dossiersEnAttente = $pdo->query(
            "SELECT 'upd' AS base, id, nom_upd AS nom, departement, updated_at
             FROM institutions_upd WHERE statut_validation = 'soumis'
             UNION ALL
             SELECT 'dde' AS base, id, nom_dde AS nom, departement, updated_at
             FROM institutions_dde WHERE statut_validation = 'soumis'
             ORDER BY updated_at DESC
             LIMIT 6"
        )->fetchAll();

        $this->render('dashboard/index', [
            'titrePage'             => 'Tableau de bord',
            'statsUpd'              => $upd,
            'statsDde'              => $dde,
            'requisitionsParStatut' => $requisitionsParStatut,
            'nbRequisitions'        => array_sum($requisitionsParStatut),
            'montantEngage'         => $montantEngage,
            'montantEnAttente'      => $montantEnAttente,
            'completionUpd'         => $completionUpd,
            'completionDde'         => $completionDde,
            'graphiques'            => $graphiques,
            'dernieresRequisitions' => $dernieresRequisitions,
            'dossiersEnAttente'     => $dossiersEnAttente,
        ]);
    }

    /** @return array{total: int, brouillon: int, soumis: int, valide: int, rejete: int} */
    private function repartitionStatuts(PDO $pdo, string $table): array
    {
        $stats = ['total' => 0, 'brouillon' => 0, 'soumis' => 0, 'valide' => 0, 'rejete' => 0];

        foreach ($pdo->query('SELECT statut_validation, COUNT(*) AS nb FROM ' . $table . ' GROUP BY statut_validation')->fetchAll() as $ligne) {
            $statut = Workflow::statutValide($ligne['statut_validation']);
            $stats[$statut] += (int)$ligne['nb'];
            $stats['total'] += (int)$ligne['nb'];
        }

        return $stats;
    }

    /** Pourcentage moyen de remplissage des questionnaires d'un module. */
    private function completionMoyenne(PDO $pdo, string $base): int
    {
        $total = (int)$pdo->query('SELECT COUNT(*) FROM ' . $base . '_questions_catalogue WHERE actif = 1')->fetchColumn();
        $dossiers = (int)$pdo->query('SELECT COUNT(*) FROM institutions_' . $base)->fetchColumn();

        if ($total === 0 || $dossiers === 0) {
            return 0;
        }

        $repondues = (int)$pdo->query(
            'SELECT COUNT(*)
             FROM ' . $base . '_reponses r
             JOIN ' . $base . '_questions_catalogue q ON q.id = r.question_id
             WHERE q.actif = 1 AND r.valeur IS NOT NULL AND r.valeur <> \'\''
        )->fetchColumn();

        return Format::pourcentage($repondues, $total * $dossiers);
    }

    /**
     * Nombre de dossiers UPD et DDE par département, pour les dix départements.
     *
     * @return list<array{code: string, libelle: string, upd: int, dde: int}>
     */
    private function couvertureDepartementale(PDO $pdo): array
    {
        $compter = static function (string $table) use ($pdo): array {
            $resultats = [];
            foreach ($pdo->query('SELECT departement, COUNT(*) AS nb FROM ' . $table . ' WHERE departement IS NOT NULL AND departement <> \'\' GROUP BY departement')->fetchAll() as $ligne) {
                $resultats[Departements::affichage((string)$ligne['departement'])] = (int)$ligne['nb'];
            }
            return $resultats;
        };

        $upd = $compter('institutions_upd');
        $dde = $compter('institutions_dde');

        $lignes = [];
        foreach (Departements::tous() as $code => $libelle) {
            $lignes[] = [
                'code'    => $code,
                'libelle' => $libelle,
                'upd'     => $upd[$libelle] ?? 0,
                'dde'     => $dde[$libelle] ?? 0,
            ];
        }

        return $lignes;
    }
}
