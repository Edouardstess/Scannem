-- ============================================================================
-- UEP / MENFP — Schéma de la base de données
-- Unité d'Études et de Programmation
-- Ministère de l'Éducation Nationale et de la Formation Professionnelle (Haïti)
-- ----------------------------------------------------------------------------
-- Contenu :
--   1. Authentification, rôles, anti-bruteforce et journal d'audit
--   2. Questionnaire dynamique des UPD  (catalogue + réponses, sections A à Q)
--   3. Questionnaire dynamique des DDE  (catalogue + réponses, sections A à F)
--   4. Réquisitions du service informatique
--   5. Vues de consolidation
-- ----------------------------------------------------------------------------
-- IMPORTATION
--   Hébergement mutualisé (ByetHost, InfinityFree…) : le nom de la base est
--   imposé par le panneau de l'hébergeur. Ce script ne crée donc AUCUNE base et
--   ne contient pas d'instruction USE : sélectionnez d'abord votre base dans
--   phpMyAdmin, puis importez ce fichier.
--
--   En local (XAMPP, WAMP, Laragon), décommentez les deux lignes suivantes :
--     CREATE DATABASE IF NOT EXISTS uep_menfp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--     USE uep_menfp;
--
--   ATTENTION : ce script recrée les tables. Toute donnée existante est perdue.
--   Sur une base déjà en service, utilisez plutôt database/migration-2.0.sql.
-- ----------------------------------------------------------------------------
-- Encodage : utf8mb4   ·   Moteur : InnoDB (transactions + clés étrangères)
-- ============================================================================

SET NAMES utf8mb4;
SET SQL_MODE = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET FOREIGN_KEY_CHECKS = 0;

-- Réimportation propre : les objets sont supprimés avant d'être recréés.
DROP VIEW  IF EXISTS vue_requisitions_recap;
DROP VIEW  IF EXISTS vue_completion_upd;
DROP VIEW  IF EXISTS vue_completion_dde;
DROP TABLE IF EXISTS requisition_articles;
DROP TABLE IF EXISTS requisitions;
DROP TABLE IF EXISTS requisition_sequences;
DROP TABLE IF EXISTS categories_articles;
DROP TABLE IF EXISTS dde_reponses;
DROP TABLE IF EXISTS dde_questions_catalogue;
DROP TABLE IF EXISTS institutions_dde;
DROP TABLE IF EXISTS upd_reponses;
DROP TABLE IF EXISTS upd_questions_catalogue;
DROP TABLE IF EXISTS institutions_upd;
DROP TABLE IF EXISTS journal_activites;
DROP TABLE IF EXISTS tentatives_connexion;
DROP TABLE IF EXISTS utilisateurs;
DROP TABLE IF EXISTS roles;

-- ============================================================================
-- MODULE 1 : AUTHENTIFICATION, RÔLES ET SÉCURITÉ
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Table : roles
-- Rôle   : Définit les niveaux d'accès applicatifs (RBAC simple)
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_role      VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Ex: administrateur, saisisseur, superviseur',
    libelle       VARCHAR(100) NOT NULL COMMENT 'Nom affiché à l''écran',
    description   VARCHAR(255) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rôles applicatifs (RBAC)';

INSERT INTO roles (nom_role, libelle, description) VALUES
('administrateur', 'Administrateur', 'Accès total : gestion des utilisateurs, des questionnaires, validation des données et des réquisitions'),
('saisisseur',     'Saisisseur de données', 'Peut saisir et modifier les données des UPD/DDE et créer des réquisitions'),
('superviseur',    'Superviseur', 'Peut consulter, valider ou rejeter les données saisies et décider des réquisitions'),
('lecteur',        'Lecteur', 'Consultation seule : aucun droit de saisie, de validation ni d''administration');

-- ----------------------------------------------------------------------------
-- Table : utilisateurs
-- Rôle   : Comptes applicatifs. Les mots de passe sont TOUJOURS hachés avec
--          l'algorithme bcrypt (password_hash() en PHP) — jamais en clair.
-- ----------------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_complet         VARCHAR(150)    NOT NULL,
    email               VARCHAR(150)    NOT NULL UNIQUE,
    mot_de_passe_hash   VARCHAR(255)    NOT NULL COMMENT 'Haché avec password_hash() PHP (bcrypt)',
    role_id             INT UNSIGNED    NOT NULL,
    institution_type    ENUM('UPD','DDE','MENFP') NULL COMMENT 'Structure d''affectation de l''utilisateur',
    institution_id      INT UNSIGNED    NULL COMMENT 'FK vers institutions_upd ou institutions_dde selon institution_type',
    departement_rattachement VARCHAR(50) NULL COMMENT 'Département géographique de l''utilisateur (ex: ouest, artibonite...)',
    telephone           VARCHAR(30)     NULL,
    actif               TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = compte désactivé',
    doit_changer_mdp    TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Force le changement du mot de passe à la 1ère connexion',
    derniere_connexion  DATETIME        NULL,
    jeton_reinit_mdp    VARCHAR(255)    NULL COMMENT 'Jeton temporaire pour réinitialisation de mot de passe',
    jeton_expire_le     DATETIME        NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_utilisateurs_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_utilisateurs_email (email),
    INDEX idx_utilisateurs_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comptes utilisateurs de l''application';

-- Aucun compte n'est créé ici : les mots de passe ne doivent jamais figurer
-- dans un script versionné. Créez le premier administrateur avec install.php
-- (assistant web) ou avec database/creer-admin.sql depuis phpMyAdmin.
-- ----------------------------------------------------------------------------
-- Table : tentatives_connexion
-- Rôle   : Anti-bruteforce — permet de bloquer temporairement un compte après
--          plusieurs échecs successifs (protection complémentaire au CSRF/XSS).
-- ----------------------------------------------------------------------------
CREATE TABLE tentatives_connexion (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_saisi     VARCHAR(150)  NOT NULL,
    adresse_ip      VARCHAR(45)   NOT NULL COMMENT 'Support IPv4 et IPv6',
    reussie         TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tentatives_email_date (email_saisi, created_at),
    INDEX idx_tentatives_ip_date (adresse_ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique des tentatives de connexion (verrouillage anti-bruteforce)';

-- ----------------------------------------------------------------------------
-- Table : journal_activites
-- Rôle   : Journal d'audit — traçabilité de toutes les actions sensibles
--          (création/modification/suppression) exigée pour une application
--          institutionnelle de l'État.
-- ----------------------------------------------------------------------------
CREATE TABLE journal_activites (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id      INT UNSIGNED  NULL,
    action              VARCHAR(100)  NOT NULL COMMENT 'Ex: connexion, creation_requisition, maj_reponse_upd',
    table_concernee     VARCHAR(100)  NULL,
    enregistrement_id    BIGINT UNSIGNED NULL,
    details             TEXT          NULL,
    adresse_ip          VARCHAR(45)   NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_journal_date (created_at),
    INDEX idx_journal_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal d''audit des actions utilisateurs';

-- ============================================================================
-- MODULE 2 : DONNÉES CONSOLIDÉES DES UPD (Universités Publiques Départementales)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Table : institutions_upd
-- Rôle   : Fiche "en-tête" de chaque UPD. Les colonnes nom_upd/departement
--          sont des colonnes DÉNORMALISÉES (dupliquées depuis les réponses de
--          la Section A) uniquement pour un affichage rapide dans les listes
--          et le tableau de bord SANS avoir à joindre upd_reponses. Elles sont
--          synchronisées par l'application à chaque enregistrement de la
--          Section A.
-- ----------------------------------------------------------------------------
CREATE TABLE institutions_upd (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_upd             VARCHAR(255)  NULL COMMENT 'Dénormalisé depuis la réponse A.1',
    sigle_upd           VARCHAR(50)   NULL COMMENT 'Dénormalisé depuis la réponse A.2',
    departement         VARCHAR(100)  NULL COMMENT 'Dénormalisé depuis la réponse A.3',
    statut_validation    ENUM('brouillon','soumis','valide','rejete') NOT NULL DEFAULT 'brouillon',
    created_by          INT UNSIGNED  NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_institutions_upd_createur FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_institutions_upd_departement (departement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fiche d''identification de chaque Université Publique Départementale';

-- ----------------------------------------------------------------------------
-- Table : upd_questions_catalogue
-- Rôle   : "Dictionnaire" DYNAMIQUE de TOUTES les questions du questionnaire
--          UPD (sections A à Q). Un formulaire web est généré en lisant cette
--          table groupée par section_code puis groupe_code — aucune question
--          n'est codée en dur dans les vues PHP. Un administrateur peut donc
--          ajouter/modifier une question sans toucher au code.
-- Colonnes :
--   section_code / section_libelle : ex 'A' / 'IDENTIFICATION DE L'UPD'
--   groupe_code / groupe_libelle   : pour les sous-tableaux répétitifs
--                                    (ex 'C.1' = "Composition de l'équipe
--                                    dirigeante"), NULL si question simple
--   ligne_code / ligne_libelle     : ex 'C.1.1' / 'Recteur (trice)'
--   colonne_libelle                : nom du champ réponse pour cette ligne
--                                    (ex 'Nom', 'Statut', ou simplement
--                                    'Réponse' si question à réponse unique)
--   type_reponse                   : pilote le type de champ HTML généré
-- ----------------------------------------------------------------------------
CREATE TABLE upd_questions_catalogue (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_code      VARCHAR(5)    NOT NULL COMMENT 'A, B, C ... Q',
    section_libelle   VARCHAR(150)  NOT NULL,
    groupe_code       VARCHAR(20)   NULL COMMENT 'Ex: C.1 (sous-tableau répétitif), NULL si question simple',
    groupe_libelle    VARCHAR(255)  NULL,
    ligne_code        VARCHAR(20)   NOT NULL COMMENT 'Ex: A.1, C.1.1, D.2.10',
    ligne_libelle     VARCHAR(500)  NULL,
    colonne_libelle   VARCHAR(100)  NOT NULL DEFAULT 'Réponse',
    type_reponse      ENUM('texte','texte_long','nombre','date','oui_non') NOT NULL DEFAULT 'texte',
    obligatoire       TINYINT(1)    NOT NULL DEFAULT 0,
    ordre_affichage   INT UNSIGNED  NOT NULL,
    actif             TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Permet de désactiver une question sans la supprimer',
    UNIQUE KEY uq_upd_question (ligne_code, colonne_libelle),
    INDEX idx_upd_question_section (section_code, ordre_affichage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catalogue dynamique des questions du questionnaire UPD (sections A-Q)';

-- ----------------------------------------------------------------------------
-- Table : upd_reponses
-- Rôle   : Stocke la réponse de chaque UPD pour chaque question du catalogue.
--          Une ligne = une réponse à une question, pour une UPD donnée.
-- ----------------------------------------------------------------------------
CREATE TABLE upd_reponses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    upd_id          INT UNSIGNED  NOT NULL,
    question_id     INT UNSIGNED  NOT NULL,
    valeur          TEXT          NULL,
    saisi_par       INT UNSIGNED  NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_upd_reponse (upd_id, question_id),
    CONSTRAINT fk_upd_reponses_institution FOREIGN KEY (upd_id) REFERENCES institutions_upd(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_upd_reponses_question FOREIGN KEY (question_id) REFERENCES upd_questions_catalogue(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_upd_reponses_saisisseur FOREIGN KEY (saisi_par) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_upd_reponses_upd (upd_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Réponses saisies par chaque UPD pour chaque question du catalogue';

-- ============================================================================
-- MODULE 3 : DONNÉES CONSOLIDÉES DES DDE (Directions Départementales d'Éducation)
-- ============================================================================
-- Même logique "catalogue dynamique + réponses" que pour les UPD ci-dessus,
-- appliquée aux 6 sections A-F du questionnaire DDE.
-- ----------------------------------------------------------------------------

CREATE TABLE institutions_dde (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_dde             VARCHAR(255)  NULL COMMENT 'Dénormalisé depuis la réponse A.1',
    departement         VARCHAR(100)  NULL COMMENT 'Dénormalisé depuis la Section A',
    statut_validation    ENUM('brouillon','soumis','valide','rejete') NOT NULL DEFAULT 'brouillon',
    created_by          INT UNSIGNED  NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_institutions_dde_createur FOREIGN KEY (created_by) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_institutions_dde_departement (departement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fiche d''identification de chaque Direction Départementale d''Éducation';

CREATE TABLE dde_questions_catalogue (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_code      VARCHAR(5)    NOT NULL COMMENT 'A, B, C, D, E, F',
    section_libelle   VARCHAR(150)  NOT NULL,
    groupe_code       VARCHAR(20)   NULL,
    groupe_libelle    VARCHAR(255)  NULL,
    ligne_code        VARCHAR(20)   NOT NULL,
    ligne_libelle     VARCHAR(500)  NULL,
    colonne_libelle   VARCHAR(100)  NOT NULL DEFAULT 'Réponse',
    type_reponse      ENUM('texte','texte_long','nombre','date','oui_non') NOT NULL DEFAULT 'texte',
    obligatoire       TINYINT(1)    NOT NULL DEFAULT 0,
    ordre_affichage   INT UNSIGNED  NOT NULL,
    actif             TINYINT(1)    NOT NULL DEFAULT 1,
    UNIQUE KEY uq_dde_question (section_code, ordre_affichage),
    INDEX idx_dde_question_section (section_code, ordre_affichage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catalogue dynamique des questions du questionnaire DDE (sections A-F)';

CREATE TABLE dde_reponses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dde_id          INT UNSIGNED  NOT NULL,
    question_id     INT UNSIGNED  NOT NULL,
    valeur          TEXT          NULL,
    saisi_par       INT UNSIGNED  NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dde_reponse (dde_id, question_id),
    CONSTRAINT fk_dde_reponses_institution FOREIGN KEY (dde_id) REFERENCES institutions_dde(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_dde_reponses_question FOREIGN KEY (question_id) REFERENCES dde_questions_catalogue(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_dde_reponses_saisisseur FOREIGN KEY (saisi_par) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_dde_reponses_dde (dde_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Réponses saisies par chaque DDE pour chaque question du catalogue';

-- ============================================================================
-- MODULE 4 : RÉQUISITIONS DU SERVICE INFORMATIQUE
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Table : categories_articles
-- Rôle   : Catégories d'articles pouvant être demandés (correspond au titre
--          du document "Équipement Informatique, Électrique et Solutions
--          Logicielles").
-- ----------------------------------------------------------------------------
CREATE TABLE categories_articles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catégories d''articles de réquisition';

INSERT INTO categories_articles (nom, description) VALUES
('Équipement Informatique', 'Ordinateurs, imprimantes, scanners, accessoires réseau, etc.'),
('Équipement Électrique', 'Onduleurs, régulateurs, câblage, matériel électrique divers'),
('Solutions Logicielles', 'Licences logicielles, abonnements SaaS, développements sur mesure');

-- ----------------------------------------------------------------------------
-- Table : requisitions
-- Rôle   : En-tête d'une fiche de réquisition (une demande = plusieurs
--          articles, voir requisition_articles).
-- ----------------------------------------------------------------------------
CREATE TABLE requisition_sequences (
    annee       SMALLINT UNSIGNED PRIMARY KEY,
    prochain    INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Séquence atomique des numéros de réquisition par année';

CREATE TABLE requisitions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_requisition      VARCHAR(30)   NOT NULL UNIQUE COMMENT 'Ex: REQ-2026-0001, généré par l''application',
    demandeur_id            INT UNSIGNED  NOT NULL,
    service_demandeur       VARCHAR(150)  NULL COMMENT 'Direction/Service à l''origine de la demande',
    objet                   VARCHAR(255)  NOT NULL,
    priorite                ENUM('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
    adresse_livraison       VARCHAR(255)  NULL,
    justification           TEXT          NULL,
    observations            TEXT          NULL,
    date_demande            DATE          NOT NULL,
    statut                  ENUM('en_attente','approuvee','rejetee','livree') NOT NULL DEFAULT 'en_attente',
    approuve_par            INT UNSIGNED  NULL,
    date_decision           DATETIME      NULL,
    commentaire_decision    TEXT          NULL,
    created_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requisitions_demandeur FOREIGN KEY (demandeur_id) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requisitions_approbateur FOREIGN KEY (approuve_par) REFERENCES utilisateurs(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_requisitions_statut (statut),
    INDEX idx_requisitions_date (date_demande)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fiches de réquisition du service informatique';

-- ----------------------------------------------------------------------------
-- Table : requisition_articles
-- Rôle   : Lignes d'articles demandés dans une réquisition (CRUD complet
--          possible sur chaque ligne depuis le tableau récapitulatif).
--          montant_total est une colonne CALCULÉE automatiquement par MySQL.
-- ----------------------------------------------------------------------------
CREATE TABLE requisition_articles (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id      INT UNSIGNED  NOT NULL,
    categorie_id        INT UNSIGNED  NOT NULL,
    designation         VARCHAR(255)  NOT NULL COMMENT 'Ex: Ordinateur portable Dell Latitude',
    description         TEXT          NULL,
    unite_mesure        VARCHAR(30)   NOT NULL DEFAULT 'Unité',
    quantite            INT UNSIGNED  NOT NULL DEFAULT 1,
    prix_unitaire_estime DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'En gourdes haïtiennes (HTG)',
    montant_total        DECIMAL(14,2) GENERATED ALWAYS AS (quantite * prix_unitaire_estime) STORED,
    created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requisition_articles_requisition FOREIGN KEY (requisition_id) REFERENCES requisitions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_requisition_articles_categorie FOREIGN KEY (categorie_id) REFERENCES categories_articles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_requisition_articles_requisition (requisition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Articles (lignes) demandés dans chaque réquisition';

-- ============================================================================
-- VUES UTILES POUR LE TABLEAU DE BORD
-- ============================================================================

-- Vue : montant total et nombre d'articles par réquisition
CREATE OR REPLACE VIEW vue_requisitions_recap AS
SELECT
    r.id,
    r.numero_requisition,
    u.nom_complet AS demandeur,
    r.demandeur_id,
    r.service_demandeur,
    r.objet,
    r.priorite,
    r.adresse_livraison,
    r.justification,
    r.observations,
    r.date_demande,
    r.statut,
    COUNT(ra.id) AS nb_articles,
    COALESCE(SUM(ra.montant_total), 0) AS montant_total_estime
FROM requisitions r
JOIN utilisateurs u ON u.id = r.demandeur_id
LEFT JOIN requisition_articles ra ON ra.requisition_id = r.id
GROUP BY r.id, r.numero_requisition, u.nom_complet, r.demandeur_id,
         r.service_demandeur, r.objet, r.priorite, r.adresse_livraison,
         r.justification, r.observations, r.date_demande, r.statut;

-- Vue : taux de complétion du questionnaire par UPD (nb réponses / nb questions actives)
CREATE OR REPLACE VIEW vue_completion_upd AS
SELECT
    i.id AS upd_id,
    i.nom_upd,
    i.departement,
    i.statut_validation,
    (SELECT COUNT(*) FROM upd_questions_catalogue WHERE actif = 1) AS total_questions,
    (SELECT COUNT(*) FROM upd_reponses r
        JOIN upd_questions_catalogue q ON q.id = r.question_id
        WHERE r.upd_id = i.id AND q.actif = 1 AND r.valeur IS NOT NULL AND r.valeur <> '') AS questions_repondues
FROM institutions_upd i;

-- Vue : taux de complétion du questionnaire par DDE
CREATE OR REPLACE VIEW vue_completion_dde AS
SELECT
    d.id AS dde_id,
    d.nom_dde,
    d.departement,
    d.statut_validation,
    (SELECT COUNT(*) FROM dde_questions_catalogue WHERE actif = 1) AS total_questions,
    (SELECT COUNT(*) FROM dde_reponses r
        JOIN dde_questions_catalogue q ON q.id = r.question_id
        WHERE r.dde_id = d.id AND q.actif = 1 AND r.valeur IS NOT NULL AND r.valeur <> '') AS questions_repondues
FROM institutions_dde d;

-- ============================================================================
-- CHARGEMENT DU CATALOGUE DE QUESTIONS
-- (Extrait automatiquement de "Les données consolidées des UPD et DDE.xlsx")
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Catalogue des questions UPD (624 questions réelles, sections A à Q)
-- ---------------------------------------------------------------------------
INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.1','Nom de l\'Université Publique Départementale','Réponses','texte',1),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.2','Sigle de l\'Université Publique Départementale','Réponses','texte',2),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.3','Département d\'implantation de l\'Université Publique Départementale','Réponses','texte',3),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.4','Date officielle d\'ouverture de l\'Université Publique Départementale','Réponses','texte',4),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.5','Code d\'identification de l\'Université Publique Départementale','Réponses','texte',5),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.6','Nom du (de la) Recteur (trice) de l\'Université Publique Départementale','Réponses','texte',6),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.7','Téléphone du (de la) Recteur (trice) de l\'Université Publique Départementale','Réponses','texte',7),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.8','Adresse électronique du (de la) Recteur (trice) de l\'Université Publique Départementale','Réponses','texte',8),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.9','Nom du (de la) Vice-Recteur (trice) de l\'Université Publique Départementale','Réponses','texte',9),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.10','Téléphone du (de la) Vice-Recteur (trice) de l\'Université Publique Départementale','Réponses','texte',10),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.11','Nom du (de la) Doyen (ne) des Facultés de l\'Université Publique Départementale','Réponses','texte',11),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.12','Nom du (de la) Directeur (trice) Administratif (ve) et Financier (ère) de l\'Université Publique Départementale','Réponses','texte',12),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.13','Nom du Responsable des données dans l\'Université Publique Départementale','Réponses','texte',13),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.14','Téléphone du Responsable des données dans l\'Université Publique Départementale','Réponses','texte',14),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.15','Nom du Responsable du Suivi-Évaluation dans l\'Université Publique Départementale','Réponses','texte',15),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.16','Téléphone du Responsable du Suivi-Évaluation dans l\'Université Publique Départementale','Réponses','texte',16),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.17','Date du remplissage des données','Réponses','texte',17),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE','A.17','Date du remplissage des données','A.18','Période couverte par les données','Réponses','texte',18),
('A','IDENTIFICATION DE L\'UNIVERSITE PUBLIQUE DEPARTEMENTALE',NULL,NULL,'A.19','Le (la) Recteur (trice) de l\'Université Publique Départementale, fait-il (elle) partie du Conseil des Recteurs ?','Réponses','texte',19),
('B','MISSION ET OBJECTIFS DE L\'UPD',NULL,NULL,'B.1','Mission de l\'UPD','Réponses','texte_long',20),
('B','MISSION ET OBJECTIFS DE L\'UPD',NULL,NULL,'B.2','Objectif général de l\'UPD','Réponses','texte_long',21),
('B','MISSION ET OBJECTIFS DE L\'UPD',NULL,NULL,'B.3','Objectifs spécifiques de l\'UPD','Réponses','texte_long',22),
('B','MISSION ET OBJECTIFS DE L\'UPD','B.3','Objectifs spécifiques de l\'UPD','B.3.1',NULL,'Réponses','texte',23),
('B','MISSION ET OBJECTIFS DE L\'UPD','B.3','Objectifs spécifiques de l\'UPD','B.3.2',NULL,'Réponses','texte',24),
('B','MISSION ET OBJECTIFS DE L\'UPD','B.3','Objectifs spécifiques de l\'UPD','B.3.3',NULL,'Réponses','texte',25),
('C','GOUVERNANCE ET ADMINISTRATION',NULL,NULL,'C.1','Composition de l\'équipe dirigeante','Réponse','texte',26),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.1','Recteur (trice)','Nom','texte',27),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.1','Recteur (trice)','Statut','texte',28),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.2','Vice-recteur (trice) aux Affaires académiques','Nom','texte',29),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.2','Vice-recteur (trice) aux Affaires académiques','Statut','texte',30),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.3','Vice-recteur (trice) à la Recherche','Nom','texte',31),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.3','Vice-recteur (trice) à la Recherche','Statut','texte',32),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.4','Secrétaire Général/e','Nom','texte',33),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.4','Secrétaire Général/e','Statut','texte',34),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.5','Directeur/trice Administratif (ve) et Financier (ère) (DAF)','Nom','texte',35),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.5','Directeur/trice Administratif (ve) et Financier (ère) (DAF)','Statut','texte',36),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.6','Directeur/trice des Études','Nom','texte',37),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.6','Directeur/trice des Études','Statut','texte',38),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.7','Directeur/trice de la Recherche','Nom','texte',39),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.7','Directeur/trice de la Recherche','Statut','texte',40),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.8','Directeur/trice des Services Informatiques','Nom','texte',41),
('C','GOUVERNANCE ET ADMINISTRATION','C.1','Composition de l\'équipe dirigeante','C.1.8','Directeur/trice des Services Informatiques','Statut','texte',42),
('C','GOUVERNANCE ET ADMINISTRATION',NULL,NULL,'C.2','Structures administratives','Nom','texte',43),
('C','GOUVERNANCE ET ADMINISTRATION',NULL,NULL,'C.2','Structures administratives','Statut','texte',44),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.1','Direction des Ressources Humaines','Existe-t-il ?','oui_non',45),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.1','Direction des Ressources Humaines','Nombre d\'agents','nombre',46),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.2','Direction des Finances','Existe-t-il ?','oui_non',47),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.2','Direction des Finances','Nombre d\'agents','nombre',48),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.3','Direction de la Scolariteté','Existe-t-il ?','oui_non',49),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.3','Direction de la Scolariteté','Nombre d\'agents','nombre',50);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.4','Direction des Affaires Académiques','Existe-t-il ?','oui_non',51),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.4','Direction des Affaires Académiques','Nombre d\'agents','nombre',52),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.5','Service de Communication','Existe-t-il ?','oui_non',53),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.5','Service de Communication','Nombre d\'agents','nombre',54),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.6','Service de Coopération Internationale','Existe-t-il ?','oui_non',55),
('C','GOUVERNANCE ET ADMINISTRATION','C.2','Structures administratives','C.2.6','Service de Coopération Internationale','Nombre d\'agents','nombre',56),
('C','GOUVERNANCE ET ADMINISTRATION',NULL,NULL,'C.3','Organes de gouvernance','Existe-t-il ?','oui_non',57),
('C','GOUVERNANCE ET ADMINISTRATION',NULL,NULL,'C.3','Organes de gouvernance','Nombre d\'agents','nombre',58),
('C','GOUVERNANCE ET ADMINISTRATION','C.3','Organes de gouvernance','C.3.1','Conseil d\'Administration','Existe-t-il ?','oui_non',59),
('C','GOUVERNANCE ET ADMINISTRATION','C.3','Organes de gouvernance','C.3.1','Conseil d\'Administration','Date dernière réunion','date',60),
('C','GOUVERNANCE ET ADMINISTRATION','C.3','Organes de gouvernance','C.3.2','Conseil Académique','Existe-t-il ?','oui_non',61),
('C','GOUVERNANCE ET ADMINISTRATION','C.3','Organes de gouvernance','C.3.2','Conseil Académique','Date dernière réunion','date',62),
('C','GOUVERNANCE ET ADMINISTRATION','C.3','Organes de gouvernance','C.3.3','Conseil Scientifique','Existe-t-il ?','oui_non',63),
('C','GOUVERNANCE ET ADMINISTRATION','C.3','Organes de gouvernance','C.3.3','Conseil Scientifique','Date dernière réunion','date',64),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.1','Facultés et Départements','Réponse','texte',65),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.1','Sciences de l\'Education','Nombre de départements','nombre',66),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.1','Sciences de l\'Education','Nombre de filières','nombre',67),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.1','Sciences de l\'Education','Date de création','date',68),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.2','Economie et Gestion','Nombre de départements','nombre',69),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.2','Economie et Gestion','Nombre de filières','nombre',70),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.2','Economie et Gestion','Date de création','date',71),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.3','Sciences Administratives, Economiques et Gouvernance locale','Nombre de départements','nombre',72),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.3','Sciences Administratives, Economiques et Gouvernance locale','Nombre de filières','nombre',73),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.3','Sciences Administratives, Economiques et Gouvernance locale','Date de création','date',74),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.4','Sciences Agronomiques et Environnementales','Nombre de départements','nombre',75),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.4','Sciences Agronomiques et Environnementales','Nombre de filières','nombre',76),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.4','Sciences Agronomiques et Environnementales','Date de création','date',77),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.5','Sciences de la Santé','Nombre de départements','nombre',78),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.5','Sciences de la Santé','Nombre de filières','nombre',79),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.5','Sciences de la Santé','Date de création','date',80),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.6','Sciences Juridiques','Nombre de départements','nombre',81),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.6','Sciences Juridiques','Nombre de filières','nombre',82),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.6','Sciences Juridiques','Date de création','date',83),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.7','Sciences Humaines et Sociales','Nombre de départements','nombre',84),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.7','Sciences Humaines et Sociales','Nombre de filières','nombre',85),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.7','Sciences Humaines et Sociales','Date de création','date',86),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.8','Sciences et Technologies','Nombre de départements','nombre',87),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.8','Sciences et Technologies','Nombre de filières','nombre',88),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.8','Sciences et Technologies','Date de création','date',89),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.9','École Supérieure de Tourisme','Nombre de départements','nombre',90),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.9','École Supérieure de Tourisme','Nombre de filières','nombre',91),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.9','École Supérieure de Tourisme','Date de création','date',92),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Nombre de départements','nombre',93),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Nombre de filières','nombre',94),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Date de création','date',95),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Nombre de départements','nombre',96),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Nombre de filières','nombre',97),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Date de création','date',98),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Nombre de départements','nombre',99),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Nombre de filières','nombre',100);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Date de création','date',101),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.13','Formation en cycle court','Nombre de départements','nombre',102),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.13','Formation en cycle court','Nombre de filières','nombre',103),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.13','Formation en cycle court','Date de création','date',104),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.14','Autres, préciser','Nombre de départements','nombre',105),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.14','Autres, préciser','Nombre de filières','nombre',106),
('D','DONNEES ACADEMIQUES','D.1','Facultés et Départements','D.1.14','Autres, préciser','Date de création','date',107),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.2','Effectif des étudiants','Nombre de départements','nombre',108),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.2','Effectif des étudiants','Nombre de filières','nombre',109),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.2','Effectif des étudiants','Date de création','date',110),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Femmes','texte',111),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Hommes','texte',112),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Total','nombre',113),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Licence (L1-L3)','texte',114),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Master (M1-M2)','texte',115),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Doctorat','texte',116),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.1','Sciences de l\'Education','Formation continue','texte',117),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Femmes','texte',118),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Hommes','texte',119),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Total','nombre',120),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Licence (L1-L3)','texte',121),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Master (M1-M2)','texte',122),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Doctorat','texte',123),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.2','Economie et Gestion','Formation continue','texte',124),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Femmes','texte',125),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Hommes','texte',126),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Total','nombre',127),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Licence (L1-L3)','texte',128),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Master (M1-M2)','texte',129),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Doctorat','texte',130),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.3','Sciences Administratives, Economiques et Gouvernance locale','Formation continue','texte',131),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Femmes','texte',132),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Hommes','texte',133),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Total','nombre',134),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Licence (L1-L3)','texte',135),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Master (M1-M2)','texte',136),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Doctorat','texte',137),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.4','Sciences Agronomiques et Environnementales','Formation continue','texte',138),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Femmes','texte',139),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Hommes','texte',140),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Total','nombre',141),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Licence (L1-L3)','texte',142),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Master (M1-M2)','texte',143),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Doctorat','texte',144),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.5','Sciences de la Santé','Formation continue','texte',145),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Femmes','texte',146),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Hommes','texte',147),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Total','nombre',148),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Licence (L1-L3)','texte',149),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Master (M1-M2)','texte',150);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Doctorat','texte',151),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.6','Sciences Juridiques','Formation continue','texte',152),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Femmes','texte',153),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Hommes','texte',154),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Total','nombre',155),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Licence (L1-L3)','texte',156),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Master (M1-M2)','texte',157),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Doctorat','texte',158),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.7','Scinces Humaines et Sociales','Formation continue','texte',159),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Femmes','texte',160),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Hommes','texte',161),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Total','nombre',162),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Licence (L1-L3)','texte',163),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Master (M1-M2)','texte',164),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Doctorat','texte',165),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.8','Sciences et Technologies','Formation continue','texte',166),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Femmes','texte',167),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Hommes','texte',168),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Total','nombre',169),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Licence (L1-L3)','texte',170),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Master (M1-M2)','texte',171),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Doctorat','texte',172),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.9','École Supérieure de Tourisme','Formation continue','texte',173),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Femmes','texte',174),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Hommes','texte',175),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Total','nombre',176),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Licence (L1-L3)','texte',177),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Master (M1-M2)','texte',178),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Doctorat','texte',179),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Formation continue','texte',180),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Femmes','texte',181),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Hommes','texte',182),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Total','nombre',183),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Licence (L1-L3)','texte',184),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Master (M1-M2)','texte',185),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Doctorat','texte',186),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Formation continue','texte',187),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Femmes','texte',188),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Hommes','texte',189),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Total','nombre',190),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Licence (L1-L3)','texte',191),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Master (M1-M2)','texte',192),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Doctorat','texte',193),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Formation continue','texte',194),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Femmes','texte',195),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Hommes','texte',196),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Total','nombre',197),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Licence (L1-L3)','texte',198),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Master (M1-M2)','texte',199),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Doctorat','texte',200);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.13','Formation en cycle court','Formation continue','texte',201),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Femmes','texte',202),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Hommes','texte',203),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Total','nombre',204),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Licence (L1-L3)','texte',205),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Master (M1-M2)','texte',206),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Doctorat','texte',207),
('D','DONNEES ACADEMIQUES','D.2','Effectif des étudiants','D.2.14','Autres, préciser','Formation continue','texte',208),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Femmes','texte',209),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Hommes','texte',210),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Total','nombre',211),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Licence (L1-L3)','texte',212),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Master (M1-M2)','texte',213),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Doctorat','texte',214),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.3','Offre de formations','Formation continue','texte',215),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.1','Licence','Nombre de filières','nombre',216),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.1','Licence','Capacité d\'accueil','texte',217),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.1','Licence','Durée (année)','texte',218),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.2','Master','Nombre de filières','nombre',219),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.2','Master','Capacité d\'accueil','texte',220),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.2','Master','Durée (année)','texte',221),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.3','Doctorat','Nombre de filières','nombre',222),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.3','Doctorat','Capacité d\'accueil','texte',223),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.3','Doctorat','Durée (année)','texte',224),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.4','Formation cycle court','Nombre de filières','nombre',225),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.4','Formation cycle court','Capacité d\'accueil','texte',226),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.4','Formation cycle court','Durée (année)','texte',227),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.5','Formation technique et professionnelle','Nombre de filières','nombre',228),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.5','Formation technique et professionnelle','Capacité d\'accueil','texte',229),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.5','Formation technique et professionnelle','Durée (année)','texte',230),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.6','Existe-t-il une école doctorale ?','Nombre de filières','nombre',231),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.6','Existe-t-il une école doctorale ?','Capacité d\'accueil','texte',232),
('D','DONNEES ACADEMIQUES','D.3','Offre de formations','D.3.6','Existe-t-il une école doctorale ?','Durée (année)','texte',233),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.4','Résultats académiques','Nombre de filières','nombre',234),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.4','Résultats académiques','Capacité d\'accueil','texte',235),
('D','DONNEES ACADEMIQUES',NULL,NULL,'D.4','Résultats académiques','Durée (année)','texte',236),
('D','DONNEES ACADEMIQUES','D.4','Résultats académiques','D.4.1','Nombre de diplômés (Licence) - (Année N-1)','Réponse','texte',237),
('D','DONNEES ACADEMIQUES','D.4','Résultats académiques','D.4.2','Nombre de diplômés (Master) - (Année N-1)','Réponse','texte',238),
('D','DONNEES ACADEMIQUES','D.4','Résultats académiques','D.4.3','Nombre de diplômés (Doctorat) - (Année N-1)','Réponse','texte',239),
('D','DONNEES ACADEMIQUES','D.4','Résultats académiques','D.4.4','Taux de réussite aux examens','Réponse','texte',240),
('D','DONNEES ACADEMIQUES','D.4','Résultats académiques','D.4.5','Taux de diplômation','Réponse','texte',241),
('D','DONNEES ACADEMIQUES','D.4','Résultats académiques','D.4.6','Taux d\'insertion professionnelle des diplômés','Réponse','texte',242),
('E','CORPS ENSEIGNANT ET RECHERCHE',NULL,NULL,'E.1','Personnel enseignant','Réponse','texte',243),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.1','Professeur Titulaire','Nombre','nombre',244),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.1','Professeur Titulaire','Total','nombre',245),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.1','Professeur Titulaire','Bâtiments loués','texte',246),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.2','Professeur à temps plein','Nombre','nombre',247),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.2','Professeur à temps plein','Total','nombre',248),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.2','Professeur à temps plein','Bâtiments loués','texte',249),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.3','Professeur à temps partiel','Nombre','nombre',250);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.3','Professeur à temps partiel','Total','nombre',251),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.3','Professeur à temps partiel','Bâtiments loués','texte',252),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.4','Professeur Agrégé','Nombre','nombre',253),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.4','Professeur Agrégé','Total','nombre',254),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.4','Professeur Agrégé','Bâtiments loués','texte',255),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.5','Maître de Conférences','Nombre','nombre',256),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.5','Maître de Conférences','Total','nombre',257),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.5','Maître de Conférences','Bâtiments loués','texte',258),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.6','Assistant-Professeur','Nombre','nombre',259),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.6','Assistant-Professeur','Total','nombre',260),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.6','Assistant-Professeur','Bâtiments loués','texte',261),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.7','Professeur Encadreur','Nombre','nombre',262),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.7','Professeur Encadreur','Total','nombre',263),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.7','Professeur Encadreur','Bâtiments loués','texte',264),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.8','Professeur Chargé de Cours','Nombre','nombre',265),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.8','Professeur Chargé de Cours','Total','nombre',266),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.8','Professeur Chargé de Cours','Bâtiments loués','texte',267),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.9','Autres','Nombre','nombre',268),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.9','Autres','Total','nombre',269),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.1','Personnel enseignant','E.1.9','Autres','Bâtiments loués','texte',270),
('E','CORPS ENSEIGNANT ET RECHERCHE',NULL,NULL,'E.2','Personnel administratif et de soutien','Nombre','nombre',271),
('E','CORPS ENSEIGNANT ET RECHERCHE',NULL,NULL,'E.2','Personnel administratif et de soutien','Total','nombre',272),
('E','CORPS ENSEIGNANT ET RECHERCHE',NULL,NULL,'E.2','Personnel administratif et de soutien','Bâtiments loués','texte',273),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.1','Personnel administratif','Nombre','nombre',274),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.1','Personnel administratif','Total','nombre',275),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.1','Personnel administratif','Fibre','texte',276),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.2','Personnel de soutien','Nombre','nombre',277),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.2','Personnel de soutien','Total','nombre',278),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.2','Personnel de soutien','Fibre','texte',279),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.3','Personnel technique','Nombre','nombre',280),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.3','Personnel technique','Total','nombre',281),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.3','Personnel technique','Fibre','texte',282),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.4','Cadres','Nombre','nombre',283),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.4','Cadres','Total','nombre',284),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.2.4','Cadres','Fibre','texte',285),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3','Recherche scientifique','Nombre','nombre',286),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3','Recherche scientifique','Total','nombre',287),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3','Recherche scientifique','Fibre','texte',288),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.1','Nombre d\'enseignants titulaires d\'un doctorat','ADSL/Satellite','texte',289),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.2','Nombre de projets de recherche en cours','ADSL/Satellite','texte',290),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.3','Nombre de publications scientifiques (Année N)','ADSL/Satellite','texte',291),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.4','Nombre d\'articles dans des revues indexées','ADSL/Satellite','texte',292),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.5','Nombre de communications dans des conférences','ADSL/Satellite','texte',293),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.6','Nombre d\'ouvrages/chapitres d\'ouvrages','ADSL/Satellite','texte',294),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.3.7','Nombre de rapports de recherche','ADSL/Satellite','texte',295),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4','Taux d\'encadrement pédagogique','ADSL/Satellite','texte',296),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.1','Sciences de l\'Education','Enseignants/Etudiants','texte',297),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.1','Sciences de l\'Education','Mixtes','oui_non',298),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.2','Economie et Gestion','Enseignants/Etudiants','texte',299),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.2','Economie et Gestion','Mixtes','oui_non',300);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.3','Sciences Administratives, Economiques et Gouvernance locale','Enseignants/Etudiants','texte',301),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.3','Sciences Administratives, Economiques et Gouvernance locale','Mixtes','oui_non',302),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.4','Sciences Agronomiques et Environnementales','Enseignants/Etudiants','texte',303),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.4','Sciences Agronomiques et Environnementales','Mixtes','oui_non',304),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.5','Sciences de la Santé','Enseignants/Etudiants','texte',305),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.5','Sciences de la Santé','Mixtes','oui_non',306),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.6','Sciences Juridiques','Enseignants/Etudiants','texte',307),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.6','Sciences Juridiques','Mixtes','oui_non',308),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.7','Sciences Humaines et Sociales','Enseignants/Etudiants','texte',309),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.7','Sciences Humaines et Sociales','Mixtes','oui_non',310),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.8','Sciences et Technologies','Enseignants/Etudiants','texte',311),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.8','Sciences et Technologies','Mixtes','oui_non',312),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.9','École Supérieure de Tourisme','Enseignants/Etudiants','texte',313),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.9','École Supérieure de Tourisme','Mixtes','oui_non',314),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Enseignants/Etudiants','texte',315),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.10','Maîtrise en Economie et en Gestion des Collectivités Territoriales','Mixtes','oui_non',316),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Enseignants/Etudiants','texte',317),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.11','Maîtrise en Accessibilité Pédagogique et en Éducation Inclusive','Mixtes','oui_non',318),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Enseignants/Etudiants','texte',319),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.12','Maîtrise en Administration, Direction et en Gestion des Etablissements Scolaires Publics','Mixtes','oui_non',320),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.13','Formation de cycle court','Enseignants/Etudiants','texte',321),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.13','Formation de cycle court','Mixtes','oui_non',322),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.14','Autres','Enseignants/Etudiants','texte',323),
('E','CORPS ENSEIGNANT ET RECHERCHE','E.2','Personnel administratif et de soutien','E.4.14','Autres','Mixtes','oui_non',324),
('F','INFRASTRUCTURES',NULL,NULL,'F.1','Campus et locaux','Réponse','texte',325),
('F','INFRASTRUCTURES',NULL,NULL,'F.1.1','L\'UPD, dispose-t-elle de son propre campus ?','Réponses','texte',326),
('F','INFRASTRUCTURES',NULL,NULL,'F.1.1','L\'UPD, dispose-t-elle de son propre campus ?','Echange','texte',327),
('F','INFRASTRUCTURES',NULL,NULL,'F.1.2','Nombre de bâtiments occupés','Réponses','texte',328),
('F','INFRASTRUCTURES',NULL,NULL,'F.1.2','Nombre de bâtiments occupés','Echange','texte',329),
('F','INFRASTRUCTURES',NULL,NULL,'F.2','Infrastructures disponibles','Réponses','texte',330),
('F','INFRASTRUCTURES',NULL,NULL,'F.2','Infrastructures disponibles','Echange','texte',331),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.1','Bibliothèques universitaires','Nombre','nombre',332),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.1','Bibliothèques universitaires','Etat','texte',333),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.1','Bibliothèques universitaires','Equipement','texte',334),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.2','Salles de cours','Nombre','nombre',335),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.2','Salles de cours','Etat','texte',336),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.2','Salles de cours','Equipement','texte',337),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.3','Amphithéâtres','Nombre','nombre',338),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.3','Amphithéâtres','Etat','texte',339),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.3','Amphithéâtres','Equipement','texte',340),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.4','Laboratoires scientifiques','Nombre','nombre',341),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.4','Laboratoires scientifiques','Etat','texte',342),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.4','Laboratoires scientifiques','Equipement','texte',343),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.5','Espaces de recherche/bureaux enseignants','Nombre','nombre',344),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.5','Espaces de recherche/bureaux enseignants','Etat','texte',345),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.5','Espaces de recherche/bureaux enseignants','Equipement','texte',346),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.6','Résidences universitaires','Nombre','nombre',347),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.6','Résidences universitaires','Etat','texte',348),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.6','Résidences universitaires','Equipement','texte',349),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.7','Services de restauration','Nombre','nombre',350);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('F','INFRASTRUCTURES',NULL,NULL,'F.2.7','Services de restauration','Etat','texte',351),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.7','Services de restauration','Equipement','texte',352),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.8','Espaces sportifs et culturels','Nombre','nombre',353),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.8','Espaces sportifs et culturels','Etat','texte',354),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.8','Espaces sportifs et culturels','Equipement','texte',355),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.9','Salles de laboratoire informatique','Nombre','nombre',356),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.9','Salles de laboratoire informatique','Etat','texte',357),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.9','Salles de laboratoire informatique','Equipement','texte',358),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.10','Salles d\'infirmerie/de service médical','Nombre','nombre',359),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.10','Salles d\'infirmerie/de service médical','Etat','texte',360),
('F','INFRASTRUCTURES',NULL,NULL,'F.2.10','Salles d\'infirmerie/de service médical','Equipement','texte',361),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1','Equipements informatiques','Réponse','texte',362),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.1','Ordinateurs de bureau (administration)','Nombre','nombre',363),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.1','Ordinateurs de bureau (administration)','Etat','texte',364),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.1','Ordinateurs de bureau (administration)','Vis/Rapp/Réu/Audits','texte',365),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.2','Ordinateurs pour étudiants (salles de laboratoire informatique)','Nombre','nombre',366),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.2','Ordinateurs pour étudiants (salles de laboratoire informatique)','Etat','texte',367),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.2','Ordinateurs pour étudiants (salles de laboratoire informatique)','Vis/Rapp/Réu/Audits','texte',368),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.3','Ordinateurs portables (enseignants)','Nombre','nombre',369),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.3','Ordinateurs portables (enseignants)','Etat','texte',370),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.3','Ordinateurs portables (enseignants)','Vis/Rapp/Réu/Audits','texte',371),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.4','Serveurs/équipements réseau','Nombre','nombre',372),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.4','Serveurs/équipements réseau','Etat','texte',373),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.4','Serveurs/équipements réseau','Vis/Rapp/Réu/Audits','texte',374),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.5','Vidéoprojecteurs/TBI','Nombre','nombre',375),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.5','Vidéoprojecteurs/TBI','Etat','texte',376),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.5','Vidéoprojecteurs/TBI','Vis/Rapp/Réu/Audits','texte',377),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.6','Photocopieurs/imprimantes','Nombre','nombre',378),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.6','Photocopieurs/imprimantes','Etat','texte',379),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.1.6','Photocopieurs/imprimantes','Vis/Rapp/Réu/Audits','texte',380),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2','Connectivité et sécurité informatique','Nombre','nombre',381),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2','Connectivité et sécurité informatique','Etat','texte',382),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2','Connectivité et sécurité informatique','Vis/Rapp/Réu/Audits','texte',383),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.1','La connexion internet est-elle disponible ?','Réponses','texte',384),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.1','La connexion internet est-elle disponible ?','Rapports périodiques','texte',385),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.2','Débit (Mbps)','Réponses','texte',386),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.2','Débit (Mbps)','Rapports périodiques','texte',387),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.3','Quel est le type de connexion en utilisation ?','Réponses','texte',388),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.3','Quel est le type de connexion en utilisation ?','Rapports périodiques','texte',389),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.4','Y a-t-il un réseau interne (intranet) ?','Réponses','texte',390),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.4','Y a-t-il un réseau interne (intranet) ?','Rapports périodiques','texte',391),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.5','Y a-t-il une salle au serveur/data center ?','Réponses','texte',392),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.5','Y a-t-il une salle au serveur/data center ?','Rapports périodiques','texte',393),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.6','Y a-t-il du pare-feu (firewall) ?','Réponses','texte',394),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.6','Y a-t-il du pare-feu (firewall) ?','Rapports périodiques','texte',395),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.7','Y a-t-il des antivirus ?','Réponses','texte',396),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.7','Y a-t-il des antivirus ?','Rapports périodiques','texte',397),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.8','Existe-t-il une politique de sauvegarde des données ?','Réponses','texte',398),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.8','Existe-t-il une politique de sauvegarde des données ?','Rapports périodiques','texte',399),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.9','Y a-t-il un plan de reprise d’activités (PRA) ?','Réponses','texte',400);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.2.9','Y a-t-il un plan de reprise d’activités (PRA) ?','Rapports périodiques','texte',401),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3','Systèmes d\'information','Réponses','texte',402),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3','Systèmes d\'information','Rapports périodiques','texte',403),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3.1','L’UPD dispose-t-elle d’un Système d’Information (SI) intégré ?','Réponses','texte',404),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3.1','L’UPD dispose-t-elle d’un Système d’Information (SI) intégré ?','Audits','texte',405),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3.2','L’UPD dispose-t-elle d\'un service informatique dédié ?','Réponses','texte',406),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3.2','L’UPD dispose-t-elle d\'un service informatique dédié ?','Audits','texte',407),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3.3','L’UPD dispose-t-elle d’un SIGE ?','Réponses','texte',408),
('G','EQUIPEMENTS TECHNIQUES ET NUMERIQUES',NULL,NULL,'G.3.3','L’UPD dispose-t-elle d’un SIGE ?','Audits','texte',409),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1','Statut du personnel','Réponse','texte',410),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1.1','Statut du personnel','Réponses','texte',411),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1.1','Statut du personnel','Mens/Trim/Sem/Annuelle','texte',412),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1.2','Quelle est la situation du paiement des salaires','Réponses','texte',413),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1.2','Quelle est la situation du paiement des salaires','Mens/Trim/Sem/Annuelle','texte',414),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1.3','Quel est le délai moyen de paiement des salaires (mois de retard)','Réponses','texte',415),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.1.3','Quel est le délai moyen de paiement des salaires (mois de retard)','Mens/Trim/Sem/Annuelle','texte',416),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2','Documents stratégiques','Réponses','texte',417),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2','Documents stratégiques','Mens/Trim/Sem/Annuelle','texte',418),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.1','Existe-t-il un organigramme approuvé ?','Réponses','texte',419),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.1','Existe-t-il un organigramme approuvé ?','Trim/Annuelle','texte',420),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.2','Existe-t-il un plan stratégique de développement ?','Réponses','texte',421),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.2','Existe-t-il un plan stratégique de développement ?','Trim/Annuelle','texte',422),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.3','Existe-t-il un projet d’établissement ?','Réponses','texte',423),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.3','Existe-t-il un projet d’établissement ?','Trim/Annuelle','texte',424),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.4','Existe-t-il un plan de formation continue du personnel ?','Réponses','texte',425),
('H','RESSOURCES HUMAINES ET CONDITIONS DE TRAVAIL',NULL,NULL,'H.2.4','Existe-t-il un plan de formation continue du personnel ?','Trim/Annuelle','texte',426),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1','Budgets','Réponse','texte',427),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.1','Quel est le budget total alloué par le MENFP','Montant en gourdes (année N)','nombre',428),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.1','Quel est le budget total alloué par le MENFP','Tab/Log/Fiches de collecte','texte',429),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.2','Quel est le budget de fonctionnement','Montant en gourdes (année N)','nombre',430),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.2','Quel est le budget de fonctionnement','Tab/Log/Fiches de collecte','texte',431),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.3','Quel est le budget d’investissement','Montant en gourdes (année N)','nombre',432),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.3','Quel est le budget d’investissement','Tab/Log/Fiches de collecte','texte',433),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.4','Quel est le budget de recherche','Montant en gourdes (année N)','nombre',434),
('I','DONNEES FINANCIERES',NULL,NULL,'I.1.4','Quel est le budget de recherche','Tab/Log/Fiches de collecte','texte',435),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2','Sources de financement','Montant en gourdes (année N)','nombre',436),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2','Sources de financement','Tab/Log/Fiches de collecte','texte',437),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.1','Budget national (MENFP)','Montant en gourdes','nombre',438),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.1','Budget national (MENFP)','Pourcentage','nombre',439),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.1','Budget national (MENFP)','Log/Fich/Autres','texte',440),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.2','Coopération internationale','Montant en gourdes','nombre',441),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.2','Coopération internationale','Pourcentage','nombre',442),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.2','Coopération internationale','Log/Fich/Autres','texte',443),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.3','Partenaires techniques et financiers (PTF)','Montant en gourdes','nombre',444),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.3','Partenaires techniques et financiers (PTF)','Pourcentage','nombre',445),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.3','Partenaires techniques et financiers (PTF)','Log/Fich/Autres','texte',446),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.4','Fonds propres','Montant en gourdes','nombre',447),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.4','Fonds propres','Pourcentage','nombre',448),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.4','Fonds propres','Log/Fich/Autres','texte',449),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.5','Droits d\'inscription','Montant en gourdes','nombre',450);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.5','Droits d\'inscription','Pourcentage','nombre',451),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.5','Droits d\'inscription','Log/Fich/Autres','texte',452),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.6','Autres','Montant en gourdes','nombre',453),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.6','Autres','Pourcentage','nombre',454),
('I','DONNEES FINANCIERES',NULL,NULL,'I.2.6','Autres','Log/Fich/Autres','texte',455),
('I','DONNEES FINANCIERES',NULL,NULL,'I.3','Système financier','Montant en gourdes','nombre',456),
('I','DONNEES FINANCIERES',NULL,NULL,'I.3','Système financier','Pourcentage','nombre',457),
('I','DONNEES FINANCIERES',NULL,NULL,'I.3','Système financier','Log/Fich/Autres','texte',458),
('I','DONNEES FINANCIERES','I.3','Système financier','I.3.1','L\'UPD dispose-t-elle d’un système de comptabilité informatisé ?','Réponses','texte',459),
('I','DONNEES FINANCIERES','I.3','Système financier','I.3.2','L’UPD dispose-t-elle d’un service financier dédié ?','Réponses','texte',460),
('J','COOPERATION ET PARTENARIATS',NULL,NULL,'J.1','Partenarias académiques et scientifiques','Réponse','texte',461),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.1',NULL,'Type de coopération','texte',462),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.1',NULL,'Période','texte',463),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.2',NULL,'Type de coopération','texte',464),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.2',NULL,'Période','texte',465),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.3',NULL,'Type de coopération','texte',466),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.3',NULL,'Période','texte',467),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.4',NULL,'Type de coopération','texte',468),
('J','COOPERATION ET PARTENARIATS','J.1','Partenarias académiques et scientifiques','J.1.4',NULL,'Période','texte',469),
('J','COOPERATION ET PARTENARIATS',NULL,NULL,'J.2','Collaboration avec le MENFP','Type de coopération','texte',470),
('J','COOPERATION ET PARTENARIATS',NULL,NULL,'J.2','Collaboration avec le MENFP','Période','texte',471),
('J','COOPERATION ET PARTENARIATS','J.2','Collaboration avec le MENFP','J.2.1','Participation à des comités techniques','Réponse','texte',472),
('J','COOPERATION ET PARTENARIATS','J.2','Collaboration avec le MENFP','J.2.2','Contribution à la refonte curriculaire','Réponse','texte',473),
('J','COOPERATION ET PARTENARIATS','J.2','Collaboration avec le MENFP','J.2.3','Accueil des stagiaires du MENFP','Réponse','texte',474),
('J','COOPERATION ET PARTENARIATS','J.2','Collaboration avec le MENFP','J.2.4','Projets conjoints avec le MENFP','Réponse','texte',475),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.1','Effectif total d’étudiants','Participation','texte',476),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.2','Nombre de publications par enseignant-chercheur','Participation','texte',477),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.3','Taux de féminisation (étudiantes)','Participation','texte',478),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.4','Taux d’encadrement pédagogique (enseignants/étudiants)','Participation','texte',479),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.5','Taux de réussite aux examens','Participation','texte',480),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.6','Taux de diplômation','Participation','texte',481),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.7','Taux d’insertion professionnelle des diplômés','Participation','texte',482),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.8','Taux d’exécution budgétaire','Participation','texte',483),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.9','Taux de féminisation (enseignantes-chercheuses)','Participation','texte',484),
('K','INDICATEURS DE PERFORMANCE CONSOLIDES (KPI)',NULL,NULL,'K.10','Taux de couverture des besoins en équipements','Participation','texte',485),
('L','DIFFICULTES ET DEFIS',NULL,NULL,'L.1','Académique','Principales difficultés rencontrées','texte',486),
('L','DIFFICULTES ET DEFIS',NULL,NULL,'L.2','Administratif','Principales difficultés rencontrées','texte',487),
('L','DIFFICULTES ET DEFIS',NULL,NULL,'L.3','Financier','Principales difficultés rencontrées','texte',488),
('L','DIFFICULTES ET DEFIS',NULL,NULL,'L.4','Infrastructure','Principales difficultés rencontrées','texte',489),
('L','DIFFICULTES ET DEFIS',NULL,NULL,'L.5','Technique/Numérique','Principales difficultés rencontrées','texte',490),
('L','DIFFICULTES ET DEFIS',NULL,NULL,'L.6','Ressources humaines','Principales difficultés rencontrées','texte',491),
('M','PERSPECTIVES ET RECOMMANDATIONS',NULL,NULL,'M.1','Perspectives pour l\'année N+1','Réponse','texte',492),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.1','Perspectives pour l\'année N+1','M.1.1',NULL,'Objectifs visés','texte',493),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.1','Perspectives pour l\'année N+1','M.1.2',NULL,'Objectifs visés','texte',494),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.1','Perspectives pour l\'année N+1','M.1.3',NULL,'Objectifs visés','texte',495),
('M','PERSPECTIVES ET RECOMMANDATIONS',NULL,NULL,'M.2','Besoins de l\'UPD','Objectifs visés','texte',496),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.1','Renforcement des capacités','Types','texte',497),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.1','Renforcement des capacités','Priorité','texte',498),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.1','Renforcement des capacités','Estimation budgétaire en gourdes','texte',499),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.2','Equipements informatiques','Types','texte',500);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.2','Equipements informatiques','Priorité','texte',501),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.2','Equipements informatiques','Estimation budgétaire en gourdes','texte',502),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.3','Infrastructures','Types','texte',503),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.3','Infrastructures','Priorité','texte',504),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.3','Infrastructures','Estimation budgétaire en gourdes','texte',505),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.4','Laboratoires/équipements scientifiques','Types','texte',506),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.4','Laboratoires/équipements scientifiques','Priorité','texte',507),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.4','Laboratoires/équipements scientifiques','Estimation budgétaire en gourdes','texte',508),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.5','Système d’information/SIGE','Types','texte',509),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.5','Système d’information/SIGE','Priorité','texte',510),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.5','Système d’information/SIGE','Estimation budgétaire en gourdes','texte',511),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.6','Personnel (recrutement)','Types','texte',512),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.6','Personnel (recrutement)','Priorité','texte',513),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.6','Personnel (recrutement)','Estimation budgétaire en gourdes','texte',514),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.7','Autres, préciser','Types','texte',515),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.7','Autres, préciser','Priorité','texte',516),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.2','Besoins de l\'UPD','M.2.7','Autres, préciser','Estimation budgétaire en gourdes','texte',517),
('M','PERSPECTIVES ET RECOMMANDATIONS',NULL,NULL,'M.3','Recommandations pour la tutelle MENFP','Types','texte_long',518),
('M','PERSPECTIVES ET RECOMMANDATIONS',NULL,NULL,'M.3','Recommandations pour la tutelle MENFP','Priorité','texte_long',519),
('M','PERSPECTIVES ET RECOMMANDATIONS',NULL,NULL,'M.3','Recommandations pour la tutelle MENFP','Estimation budgétaire en gourdes','texte_long',520),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.3','Recommandations pour la tutelle MENFP','M.3.1',NULL,'Objectifs visés','texte',521),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.3','Recommandations pour la tutelle MENFP','M.3.2',NULL,'Objectifs visés','texte',522),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.3','Recommandations pour la tutelle MENFP','M.3.3',NULL,'Objectifs visés','texte',523),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.3','Recommandations pour la tutelle MENFP','M.3.4',NULL,'Objectifs visés','texte',524),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.3','Recommandations pour la tutelle MENFP','M.3.5',NULL,'Objectifs visés','texte',525),
('M','PERSPECTIVES ET RECOMMANDATIONS','M.3','Recommandations pour la tutelle MENFP','M.3.6',NULL,'Objectifs visés','texte',526),
('N','SUIVI-EVALUATION',NULL,NULL,'N.1','Comment assurez-vous le suivi des activités académiques et administratives ?','Réponses','texte',527),
('N','SUIVI-EVALUATION',NULL,NULL,'N.2','Fréquences des collectes des données','Réponses','texte',528),
('N','SUIVI-EVALUATION',NULL,NULL,'N.3','Outils utilisés pour le suivi-évaluation','Réponses','texte',529),
('N','SUIVI-EVALUATION',NULL,NULL,'N.4','Existe-t-il un système d’information pour le suivi-évaluation ?','Réponses','texte',530),
('N','SUIVI-EVALUATION',NULL,NULL,'N.5','Nom du responsable du suivi-évaluation à l’UPD','Réponses','texte',531),
('N','SUIVI-EVALUATION',NULL,NULL,'N.6','Date de la dernière mise à jour des données','Réponses','texte',532),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.1','Projets proposés pour le PIP (Année N+1)','Réponse','texte',533),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.1',NULL,'Domaine d\'intervention','texte',534),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.1',NULL,'Localisation','texte',535),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.1',NULL,'Durée','texte',536),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.1',NULL,'Montant en gourdes','nombre',537),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.1',NULL,'Résultats attendus','texte',538),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.1',NULL,'Priorités','texte',539),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.2',NULL,'Domaine d\'intervention','texte',540),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.2',NULL,'Localisation','texte',541),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.2',NULL,'Durée','texte',542),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.2',NULL,'Montant en gourdes','nombre',543),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.2',NULL,'Résultats attendus','texte',544),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.2',NULL,'Priorités','texte',545),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.3',NULL,'Domaine d\'intervention','texte',546),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.3',NULL,'Localisation','texte',547),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.3',NULL,'Durée','texte',548),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.3',NULL,'Montant en gourdes','nombre',549),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.3',NULL,'Résultats attendus','texte',550);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.3',NULL,'Priorités','texte',551),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.4',NULL,'Domaine d\'intervention','texte',552),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.4',NULL,'Localisation','texte',553),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.4',NULL,'Durée','texte',554),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.4',NULL,'Montant en gourdes','nombre',555),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.4',NULL,'Résultats attendus','texte',556),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.4',NULL,'Priorités','texte',557),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.5',NULL,'Domaine d\'intervention','texte',558),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.5',NULL,'Localisation','texte',559),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.5',NULL,'Durée','texte',560),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.5',NULL,'Montant en gourdes','nombre',561),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.5',NULL,'Résultats attendus','texte',562),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.5',NULL,'Priorités','texte',563),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.6',NULL,'Domaine d\'intervention','texte',564),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.6',NULL,'Localisation','texte',565),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.6',NULL,'Durée','texte',566),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.6',NULL,'Montant en gourdes','nombre',567),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.6',NULL,'Résultats attendus','texte',568),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.1','Projets proposés pour le PIP (Année N+1)','O.1.6',NULL,'Priorités','texte',569),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.2','Sources de financement envisagées','Domaine d\'intervention','texte',570),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.2','Sources de financement envisagées','Localisation','texte',571),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.2','Sources de financement envisagées','Durée','texte',572),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.2','Sources de financement envisagées','Montant en gourdes','nombre',573),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.2','Sources de financement envisagées','Résultats attendus','texte',574),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)',NULL,NULL,'O.2','Sources de financement envisagées','Priorités','texte',575),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.1',NULL,'Budget national en gourdes','texte',576),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.1',NULL,'PTF en gourdes','texte',577),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.1',NULL,'Autres en gourdes','texte',578),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.2',NULL,'Budget national en gourdes','texte',579),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.2',NULL,'PTF en gourdes','texte',580),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.2',NULL,'Autres en gourdes','texte',581),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.3',NULL,'Budget national en gourdes','texte',582),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.3',NULL,'PTF en gourdes','texte',583),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.3',NULL,'Autres en gourdes','texte',584),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.4',NULL,'Budget national en gourdes','texte',585),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.4',NULL,'PTF en gourdes','texte',586),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.4',NULL,'Autres en gourdes','texte',587),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.5',NULL,'Budget national en gourdes','texte',588),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.5',NULL,'PTF en gourdes','texte',589),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.5',NULL,'Autres en gourdes','texte',590),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.6',NULL,'Budget national en gourdes','texte',591),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.6',NULL,'PTF en gourdes','texte',592),
('O','PROPOSITION POUR LE PROJET D\'INVESTISSEMENT (PIP)','O.2','Sources de financement envisagées','O.2.6',NULL,'Autres en gourdes','texte',593),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.1','Organigramme de l’UPD','Statut','texte',594),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.1','Organigramme de l’UPD','Observations','texte_long',595),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.2','Plan stratégique de développement','Statut','texte',596),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.2','Plan stratégique de développement','Observations','texte_long',597),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.3','Projet d’établissement','Statut','texte',598),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.3','Projet d’établissement','Observations','texte_long',599),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.4','Plan de travail annuel (N-1)','Statut','texte',600);

INSERT INTO upd_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.4','Plan de travail annuel (N-1)','Observations','texte_long',601),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.5','Plan de travail annuel (N)','Statut','texte',602),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.5','Plan de travail annuel (N)','Observations','texte_long',603),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.6','Rapport d’activités annuel (N-1)','Statut','texte',604),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.6','Rapport d’activités annuel (N-1)','Observations','texte_long',605),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.7','Rapport financier annuel (N-1)','Statut','texte',606),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.7','Rapport financier annuel (N-1)','Observations','texte_long',607),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.8','Liste des filières et programmes','Statut','texte',608),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.8','Liste des filières et programmes','Observations','texte_long',609),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.9','Liste des enseignants-chercheurs','Statut','texte',610),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.9','Liste des enseignants-chercheurs','Observations','texte_long',611),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.10','Tableau de bord des indicateurs','Statut','texte',612),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.10','Tableau de bord des indicateurs','Observations','texte_long',613),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.11','Liste des conventions de partenariat','Statut','texte',614),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.11','Liste des conventions de partenariat','Observations','texte_long',615),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.12','Autres documents, préciser','Statut','texte',616),
('P','DOCUMENTS ANNEXES A FOURNIR',NULL,NULL,'P.12','Autres documents, préciser','Observations','texte_long',617),
('Q','VALIDATION',NULL,NULL,'Q.1','Je certifie que les informations fournies sont exactes, complètes et conformes à la réalité','Réponses','texte',618),
('Q','VALIDATION',NULL,NULL,'Q.2','J’accepte que les données fournies soient utilisées par le MENFP pour la planification, le suivi-évaluation et l’élaboration du PIP','Réponses','texte',619),
('Q','VALIDATION',NULL,NULL,'Q.3','Nom du (de la) Recteur (trice)','Réponses','texte',620),
('Q','VALIDATION',NULL,NULL,'Q.4','Fonction du responsable de la validation des données','Réponses','texte',621),
('Q','VALIDATION',NULL,NULL,'Q.5','Date de la validation des données','Réponses','texte',622),
('Q','VALIDATION',NULL,NULL,'Q.6','Signature du responsable de la validation des données','Réponses','texte',623),
('Q','VALIDATION',NULL,NULL,'Q.7','Cachet de l\'Institution','Réponses','texte',624);

-- ---------------------------------------------------------------------------
-- Catalogue des questions DDE (88 questions réelles, sections A à F)
-- ---------------------------------------------------------------------------
INSERT INTO dde_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.0','Département géographique de la Direction Départementale','Réponses','texte',0),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.1','Nom de la Direction Départementale','Réponses','texte',1),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.2','Code de la Direction Départementale','Réponses','texte',2),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.3','Téléphone de la Direction Départementale','Réponses','texte',3),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.4','Adresse électronique de la Direction Départementale','Réponses','texte',4),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.5','Chef-lieu du Département','Réponses','texte',5),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.6','Nom du (de la) Directeur (rice) Départemental (e)','Réponses','texte',6),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.7','Téléphone du (de la) Directeur (rice) Départemental (e)','Réponses','texte',7),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.8','Adresse électronique du (de la) Directeur (rice) Départemental (e)','Réponses','texte',8),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.9','Nom du planificateur/référent','Réponses','texte',9),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE',NULL,NULL,'A.9','Date du remplissage','Réponses','texte',10),
('A','IDENTIFICATION DE LA DIRECTION DEPARTEMENTALE','A.9','Date du remplissage','A.10','Période couverte par les données','Réponses','texte',11),
('B','DONNEES STATISTIQUES DU DEPARTEMENT',NULL,NULL,'B.1','Répartition des écoles dans le département','Réponses','texte',12),
('B','DONNEES STATISTIQUES DU DEPARTEMENT',NULL,NULL,'B.2','Répartition des élèves dans le département','Réponses','texte',13),
('B','DONNEES STATISTIQUES DU DEPARTEMENT',NULL,NULL,'B.3','Répartition des enseignants dans le département','Réponses','texte',14),
('B','DONNEES STATISTIQUES DU DEPARTEMENT',NULL,NULL,'B.4','Taux brut de scolarisation dans le département','Réponses','texte',15),
('B','DONNEES STATISTIQUES DU DEPARTEMENT',NULL,NULL,'B.5','Taux d\'achevement du fondamental','Réponses','texte',16),
('B','DONNEES STATISTIQUES DU DEPARTEMENT',NULL,NULL,'B.6','Taux de transition fondamental-secondaire','Réponses','texte',17),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.1','Ecoles construites','Quantité','texte',18),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.1','Ecoles construites','Localisation','texte',19),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.1','Ecoles construites','Observations','texte_long',20),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.2','Ecoles réhabilitées','Quantité','texte',21),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.2','Ecoles réhabilitées','Localisation','texte',22),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.2','Ecoles réhabilitées','Observations','texte_long',23),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.3','Salles de classe construites','Quantité','texte',24),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.3','Salles de classe construites','Localisation','texte',25),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.3','Salles de classe construites','Observations','texte_long',26),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.4','Enseignants formés','Quantité','texte',27),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.4','Enseignants formés','Localisation','texte',28),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.4','Enseignants formés','Observations','texte_long',29),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.5','Eleves bénéficiaires de bourses','Quantité','texte',30),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.5','Eleves bénéficiaires de bourses','Localisation','texte',31),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.5','Eleves bénéficiaires de bourses','Observations','texte_long',32),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.6','Cantines scolaires opérationnelles','Quantité','texte',33),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.6','Cantines scolaires opérationnelles','Localisation','texte',34),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.6','Cantines scolaires opérationnelles','Observations','texte_long',35),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.7','Kits scolaires distribués','Quantité','texte',36),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.7','Kits scolaires distribués','Localisation','texte',37),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.7','Kits scolaires distribués','Observations','texte_long',38),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.8','Inspections pédagogiques réalisées','Quantité','texte',39),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.8','Inspections pédagogiques réalisées','Localisation','texte',40),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.8','Inspections pédagogiques réalisées','Observations','texte_long',41),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.9','Autres, préciser','Quantité','texte',42),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.9','Autres, préciser','Localisation','texte',43),
('C','REALISATIONS DE LA DIRECTION DEPARTEMENTALE EN ANNEE N-1',NULL,NULL,'C.9','Autres, préciser','Observations','texte_long',44),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.1',NULL,'Activités envisagtées','texte',45),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.1',NULL,'Localisation','texte',46),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.1',NULL,'Budget estimté en gourdes','texte',47),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.1',NULL,'Partenaires','texte',48),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.2',NULL,'Activités envisagtées','texte',49),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.2',NULL,'Localisation','texte',50);

INSERT INTO dde_questions_catalogue (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage) VALUES
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.2',NULL,'Budget estimté en gourdes','texte',51),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.2',NULL,'Partenaires','texte',52),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.3',NULL,'Activités envisagtées','texte',53),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.3',NULL,'Localisation','texte',54),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.3',NULL,'Budget estimté en gourdes','texte',55),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.3',NULL,'Partenaires','texte',56),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.4',NULL,'Activités envisagtées','texte',57),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.4',NULL,'Localisation','texte',58),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.4',NULL,'Budget estimté en gourdes','texte',59),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.4',NULL,'Partenaires','texte',60),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.5',NULL,'Activités envisagtées','texte',61),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.5',NULL,'Localisation','texte',62),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.5',NULL,'Budget estimté en gourdes','texte',63),
('D','PROGRAMMATION DE LA DIRECTION DEPARTEMENTALE EN ANNEE N+1',NULL,NULL,'D.5',NULL,'Partenaires','texte',64),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.1',NULL,'Impact sur les activités','texte',65),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.1',NULL,'Solutions proposées','texte',66),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.2',NULL,'Impact sur les activités','texte',67),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.2',NULL,'Solutions proposées','texte',68),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.3',NULL,'Impact sur les activités','texte',69),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.3',NULL,'Solutions proposées','texte',70),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.3',NULL,'Impact sur les activités','texte',71),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.3',NULL,'Solutions proposées','texte',72),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.4',NULL,'Impact sur les activités','texte',73),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.4',NULL,'Solutions proposées','texte',74),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.5',NULL,'Impact sur les activités','texte',75),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.5',NULL,'Solutions proposées','texte',76),
('E','DIFFICULTES ET BESOINS','E.5',NULL,'E.6','Besoins identifiés','Impact sur les activités','texte',77),
('E','DIFFICULTES ET BESOINS','E.5',NULL,'E.6','Besoins identifiés','Solutions proposées','texte',78),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.7',NULL,'Impact sur les activités','texte',79),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.7',NULL,'Solutions proposées','texte',80),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.8',NULL,'Impact sur les activités','texte',81),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.8',NULL,'Solutions proposées','texte',82),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.9',NULL,'Impact sur les activités','texte',83),
('E','DIFFICULTES ET BESOINS',NULL,NULL,'E.9',NULL,'Solutions proposées','texte',84),
('F','VALIDATION',NULL,NULL,'F.1','Je certifie que les informations fournies sont exactes et completes','Réponses','texte',85),
('F','VALIDATION',NULL,NULL,'F.2','Nom du (de la) Directeur (rice) Départemental (e)','Réponses','texte',86),
('F','VALIDATION',NULL,NULL,'F.3','Signature du (de la ) Directeur (rice) Départemental (e)','Réponses','texte',87),
('F','VALIDATION',NULL,NULL,'F.4','Date de la validation des données','Réponses','texte',88);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================
