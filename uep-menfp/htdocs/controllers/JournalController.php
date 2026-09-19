<?php
declare(strict_types=1);

/** Journal d'audit — réservé aux administrateurs. */
final class JournalController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::exigerRole(['administrateur']);

        $pdo = Database::pdo();
        $recherche = $this->filtre('q');
        $utilisateur = (int)($this->filtre('utilisateur') ?: 0);

        $conditions = [];
        $parametres = [];

        if ($recherche !== '') {
            // Un placeholder nommé ne peut pas être réutilisé dans la même
            // requête lorsque l'émulation des requêtes préparées est désactivée.
            $conditions[] = '(j.action LIKE :q1 OR j.details LIKE :q2 OR j.table_concernee LIKE :q3)';
            $motif = '%' . $recherche . '%';
            $parametres[':q1'] = $motif;
            $parametres[':q2'] = $motif;
            $parametres[':q3'] = $motif;
        }

        if ($utilisateur > 0) {
            $conditions[] = 'j.utilisateur_id = :uid';
            $parametres[':uid'] = $utilisateur;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM journal_activites j' . $where);
        $stmtTotal->execute($parametres);
        $pagination = new Paginator((int)$stmtTotal->fetchColumn(), Paginator::pageDemandee(), 50);

        $stmt = $pdo->prepare(
            'SELECT j.id, j.action, j.table_concernee, j.enregistrement_id, j.details,
                    j.adresse_ip, j.created_at, u.nom_complet
             FROM journal_activites j
             LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id'
            . $where
            . ' ORDER BY j.id DESC LIMIT :limite OFFSET :offset'
        );
        foreach ($parametres as $cle => $valeur) {
            $stmt->bindValue($cle, $valeur);
        }
        $stmt->bindValue(':limite', $pagination->parPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->offset, PDO::PARAM_INT);
        $stmt->execute();

        $this->render('journal/index', [
            'titrePage'    => 'Journal d\'activités',
            'activites'    => $stmt->fetchAll(),
            'pagination'   => $pagination,
            'recherche'    => $recherche,
            'utilisateurId'=> $utilisateur,
            'utilisateurs' => $pdo->query('SELECT id, nom_complet FROM utilisateurs ORDER BY nom_complet')->fetchAll(),
        ]);
    }
}
