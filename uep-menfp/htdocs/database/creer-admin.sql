-- ============================================================================
-- UEP / MENFP — Création du PREMIER compte administrateur
-- ----------------------------------------------------------------------------
-- À utiliser si l'assistant install.php n'est pas disponible.
--
-- MODE D'EMPLOI
--   1. Ouvrir phpMyAdmin et SÉLECTIONNER la base de l'application.
--   2. Onglet « SQL », coller ce script, exécuter.
--   3. Se connecter avec :
--          adresse      : admin@menfp.gouv.ht
--          mot de passe : UepAdmin2026!
--   4. L'application impose le changement du mot de passe dès la première
--      connexion (doit_changer_mdp = 1). CHANGEZ-LE IMMÉDIATEMENT : ce mot de
--      passe figure en clair dans ce fichier public.
--
-- Le mot de passe n'est jamais stocké en clair : la colonne contient un
-- hachage bcrypt produit par password_hash() de PHP.
-- ============================================================================

INSERT INTO utilisateurs
    (nom_complet, email, mot_de_passe_hash, role_id, institution_type, actif, doit_changer_mdp)
SELECT
    'Administrateur UEP',
    'admin@menfp.gouv.ht',
    '$2y$10$hDx1dJMoA.fOXMoLTP.M3ePNEMrgET5hPLT2XQRv2vdKFziqTnwKe',
    r.id,
    'MENFP',
    1,
    1
FROM roles r
WHERE r.nom_role = 'administrateur'
  AND NOT EXISTS (SELECT 1 FROM utilisateurs u WHERE u.email = 'admin@menfp.gouv.ht')
LIMIT 1;

-- Vérification : doit renvoyer une ligne.
SELECT id, nom_complet, email, actif, doit_changer_mdp
FROM utilisateurs
WHERE email = 'admin@menfp.gouv.ht';

-- ----------------------------------------------------------------------------
-- DÉBLOCAGE ANTI-BRUTEFORCE
-- Après plusieurs échecs, le compte ou l'adresse IP est bloqué temporairement
-- et le message « Trop de tentatives échouées » s'affiche. Pour lever le
-- blocage immédiatement, videz l'historique des tentatives :
-- ----------------------------------------------------------------------------
-- DELETE FROM tentatives_connexion;
