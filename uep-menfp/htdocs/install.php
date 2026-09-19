<?php
/**
 * ============================================================================
 * install.php — Assistant d'installation de l'application UEP / MENFP.
 *
 * Conçu pour les hébergements mutualisés (ByetHost, InfinityFree…) où aucune
 * ligne de commande n'est disponible : il vérifie l'environnement, teste la
 * connexion MySQL, écrit config/config.local.php, importe le schéma et crée le
 * premier administrateur.
 *
 * SUPPRIMEZ CE FICHIER UNE FOIS L'INSTALLATION TERMINÉE (un bouton le propose
 * à la dernière étape).
 * ============================================================================
 */
declare(strict_types=1);

require __DIR__ . '/config/config.php';

spl_autoload_register(static function (string $classe): void {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $classe)) {
        return;
    }
    foreach (['core', 'controllers', 'middlewares'] as $dossier) {
        $fichier = RACINE_APP . '/' . $dossier . '/' . $classe . '.php';
        if (is_file($fichier)) {
            require_once $fichier;
            return;
        }
    }
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

Session::demarrer();

// ---------------------------------------------------------------------------
// Verrou : une fois l'application installée et un administrateur créé,
// l'assistant refuse de repartir. Il ne doit jamais permettre de réécrire la
// configuration d'une installation en service.
// ---------------------------------------------------------------------------
function uep_admin_existe(): bool
{
    if (!uep_est_installee()) {
        return false;
    }

    try {
        $stmt = Database::pdo()->query(
            "SELECT COUNT(*) FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE r.nom_role = 'administrateur' AND u.actif = 1"
        );
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// La session qui vient de terminer l'installation garde accès à l'écran final,
// où se trouve le bouton de suppression de l'assistant.
$verrouille = uep_admin_existe() && Session::get('install_email') === null;

// ---------------------------------------------------------------------------
// Étapes
// ---------------------------------------------------------------------------
$etapes = [
    1 => 'Vérification du serveur',
    2 => 'Connexion à la base',
    3 => 'Création des tables',
    4 => 'Compte administrateur',
    5 => 'Terminé',
];

$etape = (int)(filter_input(INPUT_GET, 'etape', FILTER_VALIDATE_INT) ?: 1);
$etape = max(1, min(5, $etape));
$erreurs = [];
$messages = [];

/** Contrôles techniques du serveur. */
function uep_controles(): array
{
    $dossierConfig = dirname(FICHIER_CONFIG_LOCALE);

    return [
        [
            'libelle' => 'PHP 8.1 ou supérieur',
            'valeur'  => PHP_VERSION,
            'ok'      => PHP_VERSION_ID >= 80100,
            'aide'    => 'Choisissez PHP 8.1+ dans le panneau de votre hébergeur.',
        ],
        [
            'libelle' => 'Extension PDO MySQL',
            'valeur'  => extension_loaded('pdo_mysql') ? 'activée' : 'absente',
            'ok'      => extension_loaded('pdo_mysql'),
            'aide'    => 'Indispensable pour dialoguer avec MySQL.',
        ],
        [
            'libelle' => 'Extension mbstring',
            'valeur'  => extension_loaded('mbstring') ? 'activée' : 'absente',
            'ok'      => extension_loaded('mbstring'),
            'aide'    => 'Nécessaire au traitement correct des accents.',
        ],
        [
            'libelle' => 'Extension OpenSSL',
            'valeur'  => extension_loaded('openssl') ? 'activée' : 'absente',
            'ok'      => extension_loaded('openssl'),
            'aide'    => 'Utilisée pour produire des jetons aléatoires sûrs.',
        ],
        [
            'libelle' => 'Dossier config/ accessible en écriture',
            'valeur'  => is_writable($dossierConfig) ? 'oui' : 'non',
            'ok'      => is_writable($dossierConfig),
            'aide'    => 'Donnez les droits d\'écriture au dossier config/, ou créez '
                       . 'config/config.local.php à la main depuis config.local.example.php.',
        ],
        [
            'libelle' => 'Dossier storage/logs accessible en écriture',
            'valeur'  => is_writable(RACINE_LOGS) ? 'oui' : 'non',
            'ok'      => is_writable(RACINE_LOGS),
            'aide'    => 'Sans ce dossier, les erreurs ne seront pas journalisées (non bloquant).',
            'facultatif' => true,
        ],
        [
            'libelle' => 'Réécriture d\'URL (mod_rewrite)',
            'valeur'  => function_exists('apache_get_modules')
                ? (in_array('mod_rewrite', apache_get_modules(), true) ? 'activée' : 'absente')
                : 'indéterminée',
            'ok'      => !function_exists('apache_get_modules') || in_array('mod_rewrite', apache_get_modules(), true),
            'aide'    => 'Sans réécriture, les adresses comme /dashboard ne fonctionneront pas.',
            'facultatif' => true,
        ],
    ];
}

/** Écrit config/config.local.php. */
function uep_ecrire_config(array $valeurs): bool
{
    $lignes = ["<?php", "/**", " * Configuration locale — générée par install.php le " . date('d/m/Y à H:i') . ".", " *", " * NE JAMAIS publier ce fichier : il contient le mot de passe MySQL.", " */", "return ["];

    foreach ($valeurs as $cle => $valeur) {
        $lignes[] = is_int($valeur)
            ? sprintf("    %-18s => %d,", var_export($cle, true), $valeur)
            : sprintf("    %-18s => %s,", var_export($cle, true), var_export((string)$valeur, true));
    }

    $lignes[] = "];";
    $lignes[] = "";

    return file_put_contents(FICHIER_CONFIG_LOCALE, implode("\n", $lignes), LOCK_EX) !== false;
}

/** Ouvre une connexion PDO avec des identifiants donnés. */
function uep_tester_connexion(string $hote, int $port, string $base, string $user, string $pass): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $hote, $port, $base),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

/**
 * Exécute un fichier SQL instruction par instruction.
 * Le découpage ignore les points-virgules situés dans les chaînes littérales.
 *
 * @return int Nombre d'instructions exécutées.
 */
function uep_importer_sql(PDO $pdo, string $fichier): int
{
    $sql = file_get_contents($fichier);
    if ($sql === false) {
        throw new RuntimeException('Fichier SQL illisible : ' . basename($fichier));
    }

    $instructions = [];
    $courante = '';
    $delimiteur = null;   // guillemet ouvrant en cours, ou null hors chaîne
    $longueur = strlen($sql);

    for ($i = 0; $i < $longueur; $i++) {
        $caractere = $sql[$i];

        if ($delimiteur !== null) {
            $courante .= $caractere;
            if ($caractere === '\\' && $i + 1 < $longueur) {
                $courante .= $sql[++$i];
            } elseif ($caractere === $delimiteur) {
                $delimiteur = null;
            }
            continue;
        }

        // Commentaire « -- » jusqu'à la fin de la ligne.
        if ($caractere === '-' && substr($sql, $i, 3) === '-- ') {
            $fin = strpos($sql, "\n", $i);
            $i = $fin === false ? $longueur : $fin;
            continue;
        }

        if ($caractere === "'" || $caractere === '"' || $caractere === '`') {
            $delimiteur = $caractere;
            $courante .= $caractere;
            continue;
        }

        if ($caractere === ';') {
            if (trim($courante) !== '') {
                $instructions[] = trim($courante);
            }
            $courante = '';
            continue;
        }

        $courante .= $caractere;
    }

    if (trim($courante) !== '') {
        $instructions[] = trim($courante);
    }

    $executees = 0;
    foreach ($instructions as $instruction) {
        $pdo->exec($instruction);
        $executees++;
    }

    return $executees;
}

// ---------------------------------------------------------------------------
// Traitement des formulaires
// ---------------------------------------------------------------------------
if (!$verrouille && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifier($_POST['csrf_token'] ?? null)) {
        $erreurs[] = 'Session expirée. Rechargez la page et recommencez.';
        Csrf::regenerer();
    } else {
        $action = (string)($_POST['action'] ?? '');

        // --- Étape 2 : identifiants MySQL -----------------------------------
        if ($action === 'base') {
            $hote = trim((string)($_POST['hote'] ?? ''));
            $port = (int)($_POST['port'] ?? 3306);
            $base = trim((string)($_POST['base'] ?? ''));
            $user = trim((string)($_POST['utilisateur'] ?? ''));
            $pass = (string)($_POST['mot_de_passe'] ?? '');

            if ($hote === '' || $base === '' || $user === '') {
                $erreurs[] = 'Serveur, nom de la base et utilisateur sont obligatoires.';
            } else {
                try {
                    uep_tester_connexion($hote, $port, $base, $user, $pass);

                    $ecrit = uep_ecrire_config([
                        'UEP_APP_NAME'   => 'UEP - MENFP',
                        'UEP_APP_ENV'    => 'production',
                        'UEP_APP_URL'    => '',
                        'UEP_TIMEZONE'   => 'America/Port-au-Prince',
                        'UEP_DB_HOST'    => $hote,
                        'UEP_DB_PORT'    => $port,
                        'UEP_DB_NAME'    => $base,
                        'UEP_DB_USER'    => $user,
                        'UEP_DB_PASS'    => $pass,
                        'UEP_APP_SECRET' => bin2hex(random_bytes(24)),
                    ]);

                    if (!$ecrit) {
                        $erreurs[] = 'Impossible d\'écrire config/config.local.php. '
                            . 'Créez-le à la main depuis config/config.local.example.php.';
                    } else {
                        Session::set('install_bdd', ['hote' => $hote, 'port' => $port, 'base' => $base, 'utilisateur' => $user, 'mot_de_passe' => $pass]);
                        header('Location: install.php?etape=3', true, 303);
                        exit;
                    }
                } catch (PDOException $e) {
                    $erreurs[] = 'Connexion refusée : ' . $e->getMessage();
                }
            }
        }

        // --- Étape 3 : import du schéma -------------------------------------
        if ($action === 'schema') {
            $bdd = Session::get('install_bdd');
            if (!is_array($bdd)) {
                $erreurs[] = 'Identifiants perdus. Revenez à l\'étape précédente.';
            } else {
                try {
                    $pdo = uep_tester_connexion($bdd['hote'], (int)$bdd['port'], $bdd['base'], $bdd['utilisateur'], $bdd['mot_de_passe']);
                    $nombre = uep_importer_sql($pdo, RACINE_APP . '/database/schema.sql');
                    $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
                    $questions = (int)$pdo->query('SELECT COUNT(*) FROM upd_questions_catalogue')->fetchColumn()
                               + (int)$pdo->query('SELECT COUNT(*) FROM dde_questions_catalogue')->fetchColumn();

                    Session::set('install_resume', ['instructions' => $nombre, 'tables' => $tables, 'questions' => $questions]);
                    header('Location: install.php?etape=4', true, 303);
                    exit;
                } catch (Throwable $e) {
                    $erreurs[] = 'Import interrompu : ' . $e->getMessage();
                }
            }
        }

        // --- Étape 4 : premier administrateur -------------------------------
        if ($action === 'admin') {
            $nom = trim((string)($_POST['nom_complet'] ?? ''));
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $mdp = (string)($_POST['mot_de_passe'] ?? '');
            $confirmation = (string)($_POST['confirmation'] ?? '');

            $v = new Validator(['nom_complet' => $nom, 'email' => $email]);
            $v->obligatoire('nom_complet', 'Le nom complet est obligatoire.')
              ->obligatoire('email', 'L\'adresse électronique est obligatoire.')
              ->email('email', 'Format d\'adresse électronique invalide.')
              ->motDePasse('mot_de_passe', $mdp);

            if ($mdp !== $confirmation) {
                $v->ajouterErreur('confirmation', 'Les deux mots de passe ne correspondent pas.');
            }

            if ($v->echec()) {
                $erreurs = array_values($v->erreurs());
            } else {
                try {
                    $bdd = Session::get('install_bdd');
                    $pdo = is_array($bdd)
                        ? uep_tester_connexion($bdd['hote'], (int)$bdd['port'], $bdd['base'], $bdd['utilisateur'], $bdd['mot_de_passe'])
                        : Database::pdo();

                    $roleId = $pdo->query("SELECT id FROM roles WHERE nom_role = 'administrateur' LIMIT 1")->fetchColumn();
                    if ($roleId === false) {
                        throw new RuntimeException('Le rôle « administrateur » est absent : réimportez le schéma.');
                    }

                    $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = :email LIMIT 1');
                    $stmt->execute([':email' => $email]);

                    if ($stmt->fetchColumn()) {
                        $erreurs[] = 'Un compte utilise déjà cette adresse électronique.';
                    } else {
                        $pdo->prepare(
                            'INSERT INTO utilisateurs
                                (nom_complet, email, mot_de_passe_hash, role_id, institution_type, actif, doit_changer_mdp)
                             VALUES (:nom, :email, :hash, :role, \'MENFP\', 1, 0)'
                        )->execute([
                            ':nom'   => mb_substr($nom, 0, 150),
                            ':email' => mb_substr($email, 0, 150),
                            ':hash'  => password_hash($mdp, PASSWORD_DEFAULT),
                            ':role'  => (int)$roleId,
                        ]);

                        Session::supprimer('install_bdd');
                        Session::set('install_email', $email);
                        header('Location: install.php?etape=5', true, 303);
                        exit;
                    }
                } catch (Throwable $e) {
                    $erreurs[] = 'Création du compte impossible : ' . $e->getMessage();
                }
            }
        }

        // --- Étape 5 : suppression de l'assistant ---------------------------
        if ($action === 'supprimer') {
            if (@unlink(__FILE__)) {
                Session::detruire();
                header('Location: ' . URL_BASE . '/login', true, 303);
                exit;
            }
            $erreurs[] = 'Suppression automatique impossible. Supprimez install.php '
                . 'avec le gestionnaire de fichiers de votre hébergeur.';
        }
    }
}

$controles = uep_controles();
$bloquants = array_filter($controles, static fn (array $c): bool => !$c['ok'] && empty($c['facultatif']));
$resume = Session::get('install_resume');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Installation — <?= e(APP_NOM) ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .install-page { max-width: 780px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
        .install-entete { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .install-entete img { width: 64px; height: auto; }
        .install-etapes { display: flex; gap: .4rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .install-etape { flex: 1 1 110px; padding: .55rem .6rem; border-radius: 8px; background: #fff;
                         border: 1px solid var(--bordure); font-size: .74rem; text-align: center; color: var(--texte-doux); }
        .install-etape.is-active { background: var(--bleu); border-color: var(--bleu); color: #fff; font-weight: 600; }
        .install-etape.is-faite { background: var(--vert-pale); border-color: #A7D9BC; color: var(--vert); }
        .controle { display: flex; align-items: flex-start; gap: .75rem; padding: .7rem 0; border-bottom: 1px solid var(--bordure); }
        .controle:last-child { border-bottom: 0; }
        .controle .bi { font-size: 1.1rem; }
        .controle-ok .bi { color: var(--vert); }
        .controle-ko .bi { color: var(--rouge); }
        .controle-avert .bi { color: var(--ambre); }
        .controle-valeur { margin-left: auto; font-size: .8rem; color: var(--texte-doux); white-space: nowrap; }
        .controle small { display: block; color: var(--texte-doux); font-size: .76rem; }
    </style>
</head>
<body>
<div class="install-page">
    <header class="install-entete">
        <img src="assets/img/logo-uep.png" alt="">
        <div>
            <p class="dossier-kicker">République d'Haïti · MENFP</p>
            <h1 style="margin:.15rem 0 0;font-size:1.4rem;">Installation de la plateforme UEP</h1>
            <p style="margin:.15rem 0 0;font-size:.82rem;color:var(--texte-doux);">Version <?= e(APP_VERSION) ?></p>
        </div>
    </header>

    <?php if ($verrouille): ?>
        <div class="alerte alerte-avertissement">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            <span>
                <strong>L'application est déjà installée.</strong><br>
                Par sécurité, l'assistant est désactivé. <strong>Supprimez le fichier install.php</strong>
                depuis le gestionnaire de fichiers de votre hébergeur, puis connectez-vous.
            </span>
        </div>
        <p><a class="btn btn-primary" href="<?= e(URL_BASE) ?>/login"><i class="bi bi-box-arrow-in-right"></i> Aller à la connexion</a></p>
    <?php else: ?>

        <div class="install-etapes">
            <?php foreach ($etapes as $numero => $libelle): ?>
                <div class="install-etape<?= $numero === $etape ? ' is-active' : ($numero < $etape ? ' is-faite' : '') ?>">
                    <?= $numero ?>. <?= e($libelle) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($erreurs as $erreur): ?>
            <div class="alerte alerte-erreur"><i class="bi bi-exclamation-octagon-fill"></i><span><?= e($erreur) ?></span></div>
        <?php endforeach; ?>

        <?php if ($etape === 1): ?>
            <section class="panel">
                <header class="panel-header"><h2 class="panel-title">1. Vérification du serveur</h2></header>
                <div class="panel-body">
                    <?php foreach ($controles as $controle): ?>
                        <div class="controle <?= $controle['ok'] ? 'controle-ok' : (empty($controle['facultatif']) ? 'controle-ko' : 'controle-avert') ?>">
                            <i class="bi <?= $controle['ok'] ? 'bi-check-circle-fill' : (empty($controle['facultatif']) ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill') ?>"></i>
                            <div>
                                <strong><?= e($controle['libelle']) ?></strong>
                                <?php if (!$controle['ok']): ?><small><?= e($controle['aide']) ?></small><?php endif; ?>
                            </div>
                            <span class="controle-valeur"><?= e($controle['valeur']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <footer class="etape-actions">
                    <?php if ($bloquants === []): ?>
                        <a class="btn btn-primary" href="install.php?etape=2">Continuer <i class="bi bi-arrow-right"></i></a>
                    <?php else: ?>
                        <span class="texte-discret">Corrigez les points en rouge, puis rechargez cette page.</span>
                        <a class="btn btn-outline" href="install.php?etape=1"><i class="bi bi-arrow-clockwise"></i> Revérifier</a>
                    <?php endif; ?>
                </footer>
            </section>

        <?php elseif ($etape === 2): ?>
            <form method="POST" action="install.php?etape=2">
                <?= Csrf::champ() ?>
                <input type="hidden" name="action" value="base">
                <section class="panel">
                    <header class="panel-header"><h2 class="panel-title">2. Connexion à la base MySQL</h2></header>
                    <div class="panel-body">
                        <p class="texte-discret" style="margin-bottom:1.25rem;">
                            Reprenez exactement les valeurs affichées dans le panneau de votre hébergeur
                            (rubrique « MySQL Databases » sur ByetHost). La base doit déjà exister : cet
                            assistant crée les tables, pas la base.
                        </p>
                        <div class="grille-champs">
                            <div class="champ">
                                <label for="hote">Serveur MySQL <span class="obligatoire">*</span></label>
                                <input type="text" id="hote" name="hote" class="form-control" required
                                       value="<?= e($_POST['hote'] ?? DB_HOTE) ?>" placeholder="sql123.byethost.com">
                            </div>
                            <div class="champ">
                                <label for="port">Port</label>
                                <input type="number" id="port" name="port" class="form-control" value="<?= e((string)($_POST['port'] ?? 3306)) ?>">
                            </div>
                            <div class="champ">
                                <label for="base">Nom de la base <span class="obligatoire">*</span></label>
                                <input type="text" id="base" name="base" class="form-control" required
                                       value="<?= e($_POST['base'] ?? DB_NOM) ?>" placeholder="b7_00000000_uep">
                            </div>
                            <div class="champ">
                                <label for="utilisateur">Utilisateur MySQL <span class="obligatoire">*</span></label>
                                <input type="text" id="utilisateur" name="utilisateur" class="form-control" required
                                       value="<?= e($_POST['utilisateur'] ?? DB_USER) ?>" placeholder="b7_00000000">
                            </div>
                            <div class="champ champ-large">
                                <label for="mot_de_passe">Mot de passe MySQL</label>
                                <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <footer class="etape-actions">
                        <a class="btn btn-outline" href="install.php?etape=1"><i class="bi bi-arrow-left"></i> Retour</a>
                        <button type="submit" class="btn btn-primary">Tester et enregistrer <i class="bi bi-arrow-right"></i></button>
                    </footer>
                </section>
            </form>

        <?php elseif ($etape === 3): ?>
            <form method="POST" action="install.php?etape=3">
                <?= Csrf::champ() ?>
                <input type="hidden" name="action" value="schema">
                <section class="panel">
                    <header class="panel-header"><h2 class="panel-title">3. Création des tables</h2></header>
                    <div class="panel-body">
                        <div class="alerte alerte-succes">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Connexion réussie et fichier <code>config/config.local.php</code> écrit.</span>
                        </div>
                        <div class="alerte alerte-avertissement">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>
                                L'import <strong>recrée toutes les tables</strong> de l'application : les données
                                déjà présentes dans cette base seront perdues. Sur une base en service, quittez cet
                                assistant et exécutez <code>database/migration-2.0.sql</code> depuis phpMyAdmin.
                            </span>
                        </div>
                        <p class="texte-discret">
                            Le schéma installe 14 tables, 3 vues, ainsi que les 625 questions du questionnaire UPD
                            et les 89 questions du questionnaire DDE. L'opération dure quelques secondes.
                        </p>
                    </div>
                    <footer class="etape-actions">
                        <a class="btn btn-outline" href="install.php?etape=2"><i class="bi bi-arrow-left"></i> Retour</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-database-add"></i> Créer les tables</button>
                    </footer>
                </section>
            </form>

        <?php elseif ($etape === 4): ?>
            <form method="POST" action="install.php?etape=4">
                <?= Csrf::champ() ?>
                <input type="hidden" name="action" value="admin">
                <section class="panel">
                    <header class="panel-header"><h2 class="panel-title">4. Compte administrateur</h2></header>
                    <div class="panel-body">
                        <?php if (is_array($resume)): ?>
                            <div class="alerte alerte-succes">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>
                                    Base installée : <?= (int)$resume['tables'] ?> tables et vues,
                                    <?= (int)$resume['questions'] ?> questions chargées.
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="grille-champs">
                            <div class="champ">
                                <label for="nom_complet">Nom complet <span class="obligatoire">*</span></label>
                                <input type="text" id="nom_complet" name="nom_complet" class="form-control" required
                                       value="<?= e($_POST['nom_complet'] ?? '') ?>">
                            </div>
                            <div class="champ">
                                <label for="email">Adresse électronique <span class="obligatoire">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" required
                                       value="<?= e($_POST['email'] ?? '') ?>" placeholder="prenom.nom@menfp.gouv.ht">
                            </div>
                            <div class="champ">
                                <label for="mdp">Mot de passe <span class="obligatoire">*</span></label>
                                <input type="password" id="mdp" name="mot_de_passe" class="form-control"
                                       required minlength="<?= LONGUEUR_MDP_MIN ?>" autocomplete="new-password">
                                <p class="champ-aide">Au moins <?= LONGUEUR_MDP_MIN ?> caractères, dont une minuscule, une majuscule et un chiffre.</p>
                            </div>
                            <div class="champ">
                                <label for="confirmation">Confirmation <span class="obligatoire">*</span></label>
                                <input type="password" id="confirmation" name="confirmation" class="form-control"
                                       required autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                    <footer class="etape-actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i> Créer le compte</button>
                    </footer>
                </section>
            </form>

        <?php else: ?>
            <section class="panel">
                <header class="panel-header"><h2 class="panel-title">5. Installation terminée</h2></header>
                <div class="panel-body">
                    <div class="alerte alerte-succes">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>
                            La plateforme est prête. Connectez-vous avec
                            <strong><?= e((string)Session::get('install_email', '')) ?></strong>.
                        </span>
                    </div>
                    <div class="alerte alerte-erreur">
                        <i class="bi bi-shield-exclamation"></i>
                        <span>
                            <strong>Dernière étape obligatoire : supprimez install.php.</strong>
                            Laissé en place, ce fichier expose la configuration de votre serveur.
                        </span>
                    </div>
                </div>
                <footer class="etape-actions">
                    <form method="POST" action="install.php?etape=5">
                        <?= Csrf::champ() ?>
                        <input type="hidden" name="action" value="supprimer">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Supprimer install.php et se connecter</button>
                    </form>
                    <a class="btn btn-outline" href="<?= e(URL_BASE) ?>/login">Aller à la connexion</a>
                </footer>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
