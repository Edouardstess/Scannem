<?php
/**
 * routes/web.php — Table de routage.
 *
 * {id} n'accepte que des chiffres : toute autre valeur donne un 404 propre.
 */
declare(strict_types=1);

/** @var Router $router */

// --- Pages publiques --------------------------------------------------------
$router->get('/',        [HomeController::class, 'index']);
$router->get('/accueil', [HomeController::class, 'index']);

// --- Authentification -------------------------------------------------------
$router->get('/login',   [AuthController::class, 'login']);
$router->post('/login',  [AuthController::class, 'authentifier']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/mot-de-passe/changer',  [PasswordController::class, 'changer']);
$router->post('/mot-de-passe/changer', [PasswordController::class, 'mettreAJour']);

// --- Tableau de bord --------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/profil',    [ProfilController::class, 'index']);

// --- Module UPD -------------------------------------------------------------
$router->get('/upd',                  [UpdController::class, 'liste']);
$router->post('/upd/nouveau',         [UpdController::class, 'nouveau']);
$router->get('/upd/{id}',             [UpdController::class, 'formulaire']);
$router->post('/upd/{id}/sauvegarder',[UpdController::class, 'sauvegarder']);
$router->post('/upd/{id}/statut',     [UpdController::class, 'changerStatut']);
$router->post('/upd/{id}/supprimer',  [UpdController::class, 'supprimer']);

// --- Module DDE -------------------------------------------------------------
$router->get('/dde',                  [DdeController::class, 'liste']);
$router->post('/dde/nouveau',         [DdeController::class, 'nouveau']);
$router->get('/dde/{id}',             [DdeController::class, 'formulaire']);
$router->post('/dde/{id}/sauvegarder',[DdeController::class, 'sauvegarder']);
$router->post('/dde/{id}/statut',     [DdeController::class, 'changerStatut']);
$router->post('/dde/{id}/supprimer',  [DdeController::class, 'supprimer']);

// --- Module Réquisitions ----------------------------------------------------
$router->get('/requisitions',                   [RequisitionController::class, 'liste']);
$router->get('/requisitions/nouveau',           [RequisitionController::class, 'nouveau']);
$router->post('/requisitions/creer',            [RequisitionController::class, 'creer']);
$router->get('/requisitions/{id}',              [RequisitionController::class, 'details']);
$router->get('/requisitions/{id}/imprimer',     [RequisitionController::class, 'imprimer']);
$router->get('/requisitions/{id}/modifier',     [RequisitionController::class, 'modifier']);
$router->post('/requisitions/{id}/mettre-a-jour',[RequisitionController::class, 'mettreAJour']);
$router->post('/requisitions/{id}/decision',    [RequisitionController::class, 'decision']);
$router->post('/requisitions/{id}/supprimer',   [RequisitionController::class, 'supprimer']);

// --- Administration ---------------------------------------------------------
$router->get('/utilisateurs',                    [UserController::class, 'liste']);
$router->get('/utilisateurs/nouveau',            [UserController::class, 'nouveau']);
$router->post('/utilisateurs/creer',             [UserController::class, 'creer']);
$router->get('/utilisateurs/{id}/modifier',      [UserController::class, 'modifier']);
$router->post('/utilisateurs/{id}/mettre-a-jour',[UserController::class, 'mettreAJour']);
$router->post('/utilisateurs/{id}/supprimer',    [UserController::class, 'supprimer']);
$router->get('/journal',                         [JournalController::class, 'index']);
