<?php
/**
 * Page d'erreur unique (400, 403, 404, 405, 419, 500, 503).
 * @var int $code
 * @var string $titre
 * @var string $detail
 */
$connecte = session_status() === PHP_SESSION_ACTIVE && class_exists('Session') && Session::estConnecte();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= (int)$code ?> — <?= e($titre) ?> | <?= e(APP_NOM) ?></title>
    <link rel="icon" type="image/png" href="<?= URL_BASE ?>/assets/img/favicon.png">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/style.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="erreur-body">
    <main class="erreur-carte">
        <img src="<?= URL_BASE ?>/assets/img/logo-uep.png" alt="" class="erreur-logo">
        <p class="erreur-code"><?= (int)$code ?></p>
        <h1 class="erreur-titre"><?= e($titre) ?></h1>
        <p class="erreur-detail"><?= e($detail) ?></p>
        <div class="erreur-actions">
            <a class="btn btn-primary" href="<?= URL_BASE ?>/<?= $connecte ? 'dashboard' : '' ?>">
                <i class="bi bi-house" aria-hidden="true"></i>
                <?= $connecte ? 'Tableau de bord' : 'Page d\'accueil' ?>
            </a>
            <?php if (!$connecte): ?>
                <a class="btn btn-outline" href="<?= URL_BASE ?>/login">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Se connecter
                </a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
