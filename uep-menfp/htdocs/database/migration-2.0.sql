-- ============================================================================
-- UEP / MENFP — Migration d'une base 1.x vers la version 2.0
-- ----------------------------------------------------------------------------
-- À exécuter depuis phpMyAdmin, sur une base DÉJÀ EN SERVICE, pour conserver
-- les données existantes. Sur une base neuve, importez database/schema.sql.
--
-- Sauvegardez la base avant d'exécuter ce script (onglet « Exporter »).
-- Chaque instruction est indépendante : si l'une échoue parce que la
-- modification est déjà en place, passez simplement à la suivante.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Nouveau rôle « lecteur » (consultation seule)
-- ----------------------------------------------------------------------------
INSERT INTO roles (nom_role, libelle, description)
SELECT 'lecteur', 'Lecteur', 'Consultation seule : aucun droit de saisie, de validation ni d''administration'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nom_role = 'lecteur');

-- ----------------------------------------------------------------------------
-- 2. Le catalogue accepte les intitulés de ligne vides
--    (six questions du questionnaire n'en ont pas)
-- ----------------------------------------------------------------------------
ALTER TABLE upd_questions_catalogue MODIFY ligne_libelle VARCHAR(500) NULL;
ALTER TABLE dde_questions_catalogue MODIFY ligne_libelle VARCHAR(500) NULL;

-- ----------------------------------------------------------------------------
-- 3. Département de la DDE
--    La colonne « departement » des DDE était alimentée depuis la question A.3,
--    qui est le NUMÉRO DE TÉLÉPHONE de la direction : les listes et le tableau
--    de bord affichaient donc des numéros de téléphone comme départements.
--    On ajoute la question manquante et on efface les valeurs erronées.
-- ----------------------------------------------------------------------------
INSERT INTO dde_questions_catalogue
    (section_code, section_libelle, groupe_code, groupe_libelle, ligne_code, ligne_libelle, colonne_libelle, type_reponse, ordre_affichage, actif)
SELECT 'A', section_libelle, NULL, NULL, 'A.0',
       'Département géographique de la Direction Départementale', 'Réponses', 'texte', 0, 1
FROM dde_questions_catalogue
WHERE section_code = 'A'
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM dde_questions_catalogue WHERE ligne_code = 'A.0') AS deja)
LIMIT 1;

-- Efface les départements recopiés depuis un numéro de téléphone.
UPDATE institutions_dde SET departement = NULL
WHERE departement REGEXP '[0-9]{4}';

-- ----------------------------------------------------------------------------
-- 4. Index de recherche sur les listes
-- ----------------------------------------------------------------------------
ALTER TABLE requisitions        ADD INDEX idx_requisitions_objet (objet);
ALTER TABLE institutions_upd    ADD INDEX idx_institutions_upd_nom (nom_upd);
ALTER TABLE institutions_dde    ADD INDEX idx_institutions_dde_nom (nom_dde);

-- ----------------------------------------------------------------------------
-- 5. Purge de l'historique anti-bruteforce (débloque les comptes verrouillés)
-- ----------------------------------------------------------------------------
DELETE FROM tentatives_connexion;

-- ----------------------------------------------------------------------------
-- 6. Vues SQL devenues inutiles (facultatif)
--    L'application n'utilise plus vue_requisitions_recap, vue_completion_upd
--    ni vue_completion_dde : elles interrogent directement les tables. Ces vues
--    n'existent que sur les installations où l'hébergeur autorisait CREATE VIEW.
--    Décommentez ces trois lignes si vous souhaitez faire le ménage ; si votre
--    hébergeur refuse DROP VIEW, laissez-les en commentaire, ces vues sont
--    inoffensives.
-- ----------------------------------------------------------------------------
-- DROP VIEW IF EXISTS vue_requisitions_recap;
-- DROP VIEW IF EXISTS vue_completion_upd;
-- DROP VIEW IF EXISTS vue_completion_dde;
