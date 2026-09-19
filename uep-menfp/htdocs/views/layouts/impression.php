<?php
/** Layout minimal pour les documents imprimables (bons de réquisition). */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titrePage ?? APP_NOM) ?></title>
    <link rel="icon" type="image/png" href="<?= URL_BASE ?>/assets/img/favicon.png">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/style.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="impression-body">
<?= $contenu ?? '' ?>
<script src="<?= URL_BASE ?>/assets/js/app.js?v=<?= e(APP_VERSION) ?>" defer></script>
</body>
</html>
