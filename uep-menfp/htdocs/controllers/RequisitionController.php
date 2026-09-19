<?php
declare(strict_types=1);

/** Réquisitions du service informatique : CRUD complet et circuit de décision. */
final class RequisitionController extends Controller
{
    private const PRIORITES = ['basse', 'normale', 'haute', 'urgente'];
    private const STATUTS   = ['en_attente', 'approuvee', 'rejetee', 'livree'];
    private const PRIX_MAX  = 9999999999.99;
    private const QTE_MAX   = 1000000;

    // ------------------------------------------------------------------ Liste

    public function liste(): void
    {
        AuthMiddleware::exigerConnexion();

        $pdo = Database::pdo();
        $recherche = $this->filtre('q');
        $statut = $this->filtre('statut');
        $priorite = $this->filtre('priorite');

        $conditions = [];
        $parametres = [];

        if ($recherche !== '') {
            // Un placeholder nommé ne peut pas être réutilisé dans la même
            // requête lorsque l'émulation des requêtes préparées est désactivée.
            $conditions[] = '(r.numero_requisition LIKE :q1 OR r.objet LIKE :q2 OR r.service_demandeur LIKE :q3 OR u.nom_complet LIKE :q4)';
            $motif = '%' . $recherche . '%';
            $parametres[':q1'] = $motif;
            $parametres[':q2'] = $motif;
            $parametres[':q3'] = $motif;
            $parametres[':q4'] = $motif;
        }
        if (in_array($statut, self::STATUTS, true)) {
            $conditions[] = 'r.statut = :statut';
            $parametres[':statut'] = $statut;
        }
        if (in_array($priorite, self::PRIORITES, true)) {
            $conditions[] = 'r.priorite = :priorite';
            $parametres[':priorite'] = $priorite;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $stmtTotal = $pdo->prepare(
            'SELECT COUNT(*) FROM requisitions r JOIN utilisateurs u ON u.id = r.demandeur_id' . $where
        );
        $stmtTotal->execute($parametres);
        $pagination = new Paginator((int)$stmtTotal->fetchColumn(), Paginator::pageDemandee());

        $stmt = $pdo->prepare(
            'SELECT r.id, r.numero_requisition, r.objet, r.service_demandeur, r.priorite,
                    r.date_demande, r.statut, u.nom_complet AS demandeur,
                    (SELECT COUNT(*) FROM requisition_articles a WHERE a.requisition_id = r.id) AS nb_articles,
                    (SELECT COALESCE(SUM(a.montant_total), 0) FROM requisition_articles a WHERE a.requisition_id = r.id) AS montant_total
             FROM requisitions r
             JOIN utilisateurs u ON u.id = r.demandeur_id'
            . $where
            . ' ORDER BY r.date_demande DESC, r.id DESC LIMIT :limite OFFSET :offset'
        );
        foreach ($parametres as $cle => $valeur) {
            $stmt->bindValue($cle, $valeur);
        }
        $stmt->bindValue(':limite', $pagination->parPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->offset, PDO::PARAM_INT);
        $stmt->execute();

        $stmtCumul = $pdo->prepare(
            'SELECT COALESCE(SUM(a.montant_total), 0)
             FROM requisitions r
             JOIN utilisateurs u ON u.id = r.demandeur_id
             LEFT JOIN requisition_articles a ON a.requisition_id = r.id'
            . $where
        );
        $stmtCumul->execute($parametres);

        $this->render('requisitions/liste', [
            'titrePage'    => 'Réquisitions — Service informatique',
            'requisitions' => $stmt->fetchAll(),
            'pagination'   => $pagination,
            'recherche'    => $recherche,
            'statut'       => $statut,
            'priorite'     => $priorite,
            'cumul'        => (float)$stmtCumul->fetchColumn(),
            'statuts'      => self::STATUTS,
            'priorites'    => self::PRIORITES,
        ]);
    }

    // --------------------------------------------------------------- Création

    public function nouveau(): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'saisisseur']);

        $this->render('requisitions/formulaire', [
            'titrePage'   => 'Nouvelle réquisition',
            'mode'        => 'creation',
            'requisition' => $this->requisitionVide(),
            'articles'    => [],
            'categories'  => $this->categories(),
            'erreurs'     => [],
            'priorites'   => self::PRIORITES,
            'statuts'     => self::STATUTS,
            'numero'      => $this->prochainNumeroIndicatif(),
        ]);
    }

    public function creer(): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'saisisseur']);
        $this->exigerCsrf();

        [$validateur, $articles] = $this->validerSaisie($_POST);

        if ($validateur->echec()) {
            http_response_code(422);
            $this->render('requisitions/formulaire', [
                'titrePage'   => 'Nouvelle réquisition',
                'mode'        => 'creation',
                'requisition' => $this->requisitionDepuisPost(),
                'articles'    => $articles,
                'categories'  => $this->categories(),
                'erreurs'     => $validateur->erreurs(),
                'priorites'   => self::PRIORITES,
                'statuts'     => self::STATUTS,
                'numero'      => $this->prochainNumeroIndicatif(),
            ]);
            return;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $numero = $this->allouerNumero($pdo);

            $pdo->prepare(
                'INSERT INTO requisitions
                    (numero_requisition, demandeur_id, service_demandeur, objet, priorite,
                     adresse_livraison, justification, observations, date_demande, statut)
                 VALUES (:numero, :demandeur, :service, :objet, :priorite,
                         :adresse, :justification, :observations, CURDATE(), \'en_attente\')'
            )->execute([
                ':numero'        => $numero,
                ':demandeur'     => Auth::id(),
                ':service'       => $this->champNullable('service_demandeur', 150),
                ':objet'         => $this->champTexte('objet', 255),
                ':priorite'      => $this->champTexte('priorite', 20),
                ':adresse'       => $this->champNullable('adresse_livraison', 255),
                ':justification' => $this->champNullable('justification', 5000),
                ':observations'  => $this->champNullable('observations', 5000),
            ]);

            $id = (int)$pdo->lastInsertId();
            $this->enregistrerArticles($pdo, $id, $articles);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Création de réquisition échouée : ' . $e->getMessage());
            Flash::erreur('La création de la réquisition a échoué. Aucune donnée n\'a été enregistrée.');
            $this->redirect('/requisitions/nouveau');
        }

        Auth::journaliser(Auth::id(), 'creation_requisition', 'requisitions', $id, 'Réquisition ' . $numero);
        Flash::succes('Réquisition ' . $numero . ' créée.');

        $this->redirect('/requisitions/' . $id);
    }

    // ---------------------------------------------------------------- Détails

    public function details(int $id): void
    {
        AuthMiddleware::exigerConnexion();

        $requisition = $this->trouver($id);
        if ($requisition === null) {
            ErreurHttp::afficher(404);
        }

        $articles = $this->articles($id);

        $this->render('requisitions/details', [
            'titrePage'    => 'Réquisition ' . $requisition['numero_requisition'],
            'requisition'  => $requisition,
            'articles'     => $articles,
            'montantTotal' => $this->total($articles),
            'statuts'      => self::STATUTS,
        ]);
    }

    /** Version imprimable (bon de réquisition). */
    public function imprimer(int $id): void
    {
        AuthMiddleware::exigerConnexion();

        $requisition = $this->trouver($id);
        if ($requisition === null) {
            ErreurHttp::afficher(404);
        }

        $articles = $this->articles($id);

        $this->render('requisitions/impression', [
            'titrePage'    => 'Bon de réquisition ' . $requisition['numero_requisition'],
            'requisition'  => $requisition,
            'articles'     => $articles,
            'montantTotal' => $this->total($articles),
        ], 'impression');
    }

    // ------------------------------------------------------------ Modification

    public function modifier(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'saisisseur']);

        $requisition = $this->trouver($id);
        if ($requisition === null) {
            ErreurHttp::afficher(404);
        }

        if (!$this->modifiable($requisition)) {
            Flash::erreur('Une réquisition ' . mb_strtolower(Format::statutRequisition($requisition['statut'])) . ' ne peut plus être modifiée.');
            $this->redirect('/requisitions/' . $id);
        }

        $this->render('requisitions/formulaire', [
            'titrePage'   => 'Modifier la réquisition ' . $requisition['numero_requisition'],
            'mode'        => 'modification',
            'requisition' => $requisition,
            'articles'    => $this->articles($id),
            'categories'  => $this->categories(),
            'erreurs'     => [],
            'priorites'   => self::PRIORITES,
            'statuts'     => self::STATUTS,
            'numero'      => $requisition['numero_requisition'],
        ]);
    }

    public function mettreAJour(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'saisisseur']);
        $this->exigerCsrf();

        $requisition = $this->trouver($id);
        if ($requisition === null) {
            ErreurHttp::afficher(404);
        }

        if (!$this->modifiable($requisition)) {
            Flash::erreur('Une réquisition ' . mb_strtolower(Format::statutRequisition($requisition['statut'])) . ' ne peut plus être modifiée.');
            $this->redirect('/requisitions/' . $id);
        }

        [$validateur, $articles] = $this->validerSaisie($_POST);

        if ($validateur->echec()) {
            http_response_code(422);
            $this->render('requisitions/formulaire', [
                'titrePage'   => 'Modifier la réquisition ' . $requisition['numero_requisition'],
                'mode'        => 'modification',
                'requisition' => array_merge($requisition, $this->requisitionDepuisPost()),
                'articles'    => $articles,
                'categories'  => $this->categories(),
                'erreurs'     => $validateur->erreurs(),
                'priorites'   => self::PRIORITES,
                'statuts'     => self::STATUTS,
                'numero'      => $requisition['numero_requisition'],
            ]);
            return;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'UPDATE requisitions SET
                     service_demandeur = :service,
                     objet             = :objet,
                     priorite          = :priorite,
                     adresse_livraison = :adresse,
                     justification     = :justification,
                     observations      = :observations
                 WHERE id = :id'
            )->execute([
                ':service'       => $this->champNullable('service_demandeur', 150),
                ':objet'         => $this->champTexte('objet', 255),
                ':priorite'      => $this->champTexte('priorite', 20),
                ':adresse'       => $this->champNullable('adresse_livraison', 255),
                ':justification' => $this->champNullable('justification', 5000),
                ':observations'  => $this->champNullable('observations', 5000),
                ':id'            => $id,
            ]);

            $pdo->prepare('DELETE FROM requisition_articles WHERE requisition_id = :id')->execute([':id' => $id]);
            $this->enregistrerArticles($pdo, $id, $articles);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Modification de réquisition échouée : ' . $e->getMessage());
            Flash::erreur('La modification a échoué. Aucune donnée n\'a été modifiée.');
            $this->redirect('/requisitions/' . $id . '/modifier');
        }

        Auth::journaliser(Auth::id(), 'modification_requisition', 'requisitions', $id, (string)$requisition['numero_requisition']);
        Flash::succes('Réquisition mise à jour.');

        $this->redirect('/requisitions/' . $id);
    }

    /**
     * Décision d'un responsable : approuver, rejeter, marquer livrée ou
     * remettre en attente. Réservée aux administrateurs et superviseurs.
     */
    public function decision(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'superviseur']);
        $this->exigerCsrf();

        $requisition = $this->trouver($id);
        if ($requisition === null) {
            ErreurHttp::afficher(404);
        }

        $statut = $this->champTexte('statut', 20);
        if (!in_array($statut, self::STATUTS, true)) {
            ErreurHttp::afficher(400, 'Statut de réquisition inconnu.');
        }

        // Une réquisition livrée est définitive.
        if ($requisition['statut'] === 'livree') {
            Flash::erreur('Cette réquisition est livrée : son statut ne peut plus changer.');
            $this->redirect('/requisitions/' . $id);
        }

        // Seule une réquisition approuvée peut être déclarée livrée.
        if ($statut === 'livree' && $requisition['statut'] !== 'approuvee') {
            Flash::erreur('Une réquisition doit d\'abord être approuvée avant d\'être déclarée livrée.');
            $this->redirect('/requisitions/' . $id);
        }

        Database::pdo()->prepare(
            'UPDATE requisitions
             SET statut = :statut,
                 approuve_par = :uid,
                 date_decision = NOW(),
                 commentaire_decision = :commentaire
             WHERE id = :id'
        )->execute([
            ':statut'      => $statut,
            ':uid'         => Auth::id(),
            ':commentaire' => $this->champNullable('commentaire_decision', 2000),
            ':id'          => $id,
        ]);

        Auth::journaliser(
            Auth::id(),
            'decision_requisition',
            'requisitions',
            $id,
            $requisition['statut'] . ' -> ' . $statut
        );
        Flash::succes('Réquisition ' . mb_strtolower(Format::statutRequisition($statut)) . '.');

        $this->redirect('/requisitions/' . $id);
    }

    public function supprimer(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur']);
        $this->exigerCsrf();

        $requisition = $this->trouver($id);
        if ($requisition === null) {
            ErreurHttp::afficher(404);
        }

        try {
            // Les lignes d'articles partent en cascade.
            Database::pdo()->prepare('DELETE FROM requisitions WHERE id = :id')->execute([':id' => $id]);
        } catch (Throwable $e) {
            error_log('Suppression de réquisition échouée : ' . $e->getMessage());
            Flash::erreur('La suppression a échoué.');
            $this->redirect('/requisitions/' . $id);
        }

        Auth::journaliser(Auth::id(), 'suppression_requisition', 'requisitions', $id, (string)$requisition['numero_requisition']);
        Flash::succes('Réquisition ' . $requisition['numero_requisition'] . ' supprimée.');

        $this->redirect('/requisitions');
    }

    // ------------------------------------------------------------- Internes

    private function modifiable(array $requisition): bool
    {
        // Un administrateur peut corriger une demande déjà traitée ; un
        // saisisseur ne modifie que ce qui est encore en attente.
        return Auth::estAdmin() || $requisition['statut'] === 'en_attente';
    }

    private function trouver(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.*, u.nom_complet AS demandeur, u.email AS demandeur_email,
                    d.nom_complet AS decideur
             FROM requisitions r
             JOIN utilisateurs u ON u.id = r.demandeur_id
             LEFT JOIN utilisateurs d ON d.id = r.approuve_par
             WHERE r.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    private function articles(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.id, a.categorie_id, a.designation, a.description, a.unite_mesure,
                    a.quantite, a.prix_unitaire_estime, a.montant_total, c.nom AS nom_categorie
             FROM requisition_articles a
             JOIN categories_articles c ON c.id = a.categorie_id
             WHERE a.requisition_id = :id
             ORDER BY a.id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchAll();
    }

    private function total(array $articles): float
    {
        return array_sum(array_map(static fn (array $a): float => (float)$a['montant_total'], $articles));
    }

    private function categories(): array
    {
        return Database::pdo()->query('SELECT id, nom FROM categories_articles ORDER BY nom')->fetchAll();
    }

    private function requisitionVide(): array
    {
        return [
            'id' => 0, 'numero_requisition' => '', 'objet' => '', 'priorite' => 'normale',
            'service_demandeur' => '', 'adresse_livraison' => '', 'justification' => '',
            'observations' => '', 'statut' => 'en_attente',
        ];
    }

    private function requisitionDepuisPost(): array
    {
        return [
            'objet'             => $this->champTexte('objet', 255),
            'priorite'          => $this->champTexte('priorite', 20),
            'service_demandeur' => (string)$this->champNullable('service_demandeur', 150),
            'adresse_livraison' => (string)$this->champNullable('adresse_livraison', 255),
            'justification'     => (string)$this->champNullable('justification', 5000),
            'observations'      => (string)$this->champNullable('observations', 5000),
        ];
    }

    /**
     * Valide l'en-tête et les lignes d'articles.
     *
     * @return array{0: Validator, 1: list<array<string, mixed>>} validateur, lignes normalisées
     */
    private function validerSaisie(array $post): array
    {
        $v = new Validator($post);
        $v->obligatoire('objet', 'L\'objet de la réquisition est obligatoire.')
          ->longueurMax('objet', 255, 'L\'objet ne peut pas dépasser 255 caractères.')
          ->obligatoire('priorite', 'La priorité est obligatoire.')
          ->dansListe('priorite', self::PRIORITES, 'Priorité invalide.')
          ->longueurMax('service_demandeur', 150, 'Le service ne peut pas dépasser 150 caractères.')
          ->longueurMax('adresse_livraison', 255, 'L\'adresse de livraison ne peut pas dépasser 255 caractères.')
          ->longueurMax('justification', 5000, 'La justification est trop longue.')
          ->longueurMax('observations', 5000, 'Les observations sont trop longues.');

        $designations = $post['designation'] ?? [];
        $categories   = $post['categorie_id'] ?? [];
        $quantites    = $post['quantite'] ?? [];
        $prix         = $post['prix_unitaire'] ?? [];
        $unites       = $post['unite_mesure'] ?? [];

        if (!is_array($designations) || !is_array($categories) || !is_array($quantites) || !is_array($prix)) {
            $v->ajouterErreur('articles', 'Les lignes d\'articles sont mal formées.');
            return [$v, []];
        }

        $categoriesValides = array_map(
            'intval',
            Database::pdo()->query('SELECT id FROM categories_articles')->fetchAll(PDO::FETCH_COLUMN)
        );

        $lignes = [];

        foreach ($designations as $index => $designation) {
            $designation = is_scalar($designation) ? trim((string)$designation) : '';

            // Une ligne entièrement vide est simplement ignorée.
            if ($designation === '') {
                continue;
            }

            if (mb_strlen($designation, 'UTF-8') > 255) {
                $v->ajouterErreur('articles', 'Une désignation dépasse 255 caractères.');
                continue;
            }

            $categorie = filter_var($categories[$index] ?? null, FILTER_VALIDATE_INT);
            if ($categorie === false || !in_array((int)$categorie, $categoriesValides, true)) {
                $v->ajouterErreur('articles', 'Chaque article doit avoir une catégorie valide.');
                continue;
            }

            $quantite = filter_var(
                $quantites[$index] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => self::QTE_MAX]]
            );
            if ($quantite === false) {
                $v->ajouterErreur('articles', 'Chaque quantité doit être un entier compris entre 1 et ' . Format::nombre(self::QTE_MAX) . '.');
                continue;
            }

            $prixUnitaire = $prix[$index] ?? null;
            $prixUnitaire = is_scalar($prixUnitaire) ? str_replace([' ', ','], ['', '.'], (string)$prixUnitaire) : '';
            if ($prixUnitaire === '') {
                $prixUnitaire = '0';
            }
            if (!is_numeric($prixUnitaire) || (float)$prixUnitaire < 0 || (float)$prixUnitaire > self::PRIX_MAX) {
                $v->ajouterErreur('articles', 'Chaque prix unitaire doit être un montant positif valide.');
                continue;
            }

            $unite = is_scalar($unites[$index] ?? null) ? trim((string)$unites[$index]) : '';

            $lignes[] = [
                'categorie_id'  => (int)$categorie,
                'designation'   => $designation,
                'unite_mesure'  => $unite !== '' ? mb_substr($unite, 0, 30) : 'Unité',
                'quantite'      => (int)$quantite,
                'prix_unitaire' => round((float)$prixUnitaire, 2),
            ];
        }

        if ($lignes === []) {
            $v->ajouterErreur('articles', 'Ajoutez au moins un article à la réquisition.');
        }

        return [$v, $lignes];
    }

    /** @param list<array<string, mixed>> $lignes */
    private function enregistrerArticles(PDO $pdo, int $requisitionId, array $lignes): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO requisition_articles
                (requisition_id, categorie_id, designation, unite_mesure, quantite, prix_unitaire_estime)
             VALUES (:rid, :cid, :designation, :unite, :quantite, :prix)'
        );

        foreach ($lignes as $ligne) {
            $stmt->execute([
                ':rid'         => $requisitionId,
                ':cid'         => $ligne['categorie_id'],
                ':designation' => $ligne['designation'],
                ':unite'       => $ligne['unite_mesure'],
                ':quantite'    => $ligne['quantite'],
                ':prix'        => number_format((float)$ligne['prix_unitaire'], 2, '.', ''),
            ]);
        }
    }

    /**
     * Réserve un numéro de réquisition. L'incrément se fait en une seule
     * instruction atomique : deux demandes simultanées ne peuvent pas obtenir
     * le même numéro.
     */
    private function allouerNumero(PDO $pdo): string
    {
        $annee = (int)date('Y');

        $pdo->prepare(
            'INSERT INTO requisition_sequences (annee, prochain) VALUES (:annee, 2)
             ON DUPLICATE KEY UPDATE prochain = prochain + 1'
        )->execute([':annee' => $annee]);

        $stmt = $pdo->prepare('SELECT prochain FROM requisition_sequences WHERE annee = :annee');
        $stmt->execute([':annee' => $annee]);

        return sprintf('REQ-%04d-%04d', $annee, max(1, (int)$stmt->fetchColumn() - 1));
    }

    private function prochainNumeroIndicatif(): string
    {
        $annee = (int)date('Y');
        $stmt = Database::pdo()->prepare('SELECT prochain FROM requisition_sequences WHERE annee = :annee');
        $stmt->execute([':annee' => $annee]);
        $prochain = $stmt->fetchColumn();

        return sprintf('REQ-%04d-%04d', $annee, $prochain === false ? 1 : (int)$prochain);
    }
}
