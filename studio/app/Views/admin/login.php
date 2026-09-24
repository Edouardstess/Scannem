<?php
/** @var array<string, mixed> $settings */

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — <?= e($studioName) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="admin admin--auth">

<main class="auth">
    <div class="auth__panel">
        <p class="auth__brand"><?= e($studioName) ?></p>
        <h1 class="auth__title">Espace photographe</h1>

        <?php foreach (($flashes ?? []) as $flash): ?>
            <p class="auth__flash auth__flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('/admin/login')) ?>" class="auth__form">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autofocus
                       autocomplete="username" value="<?= e(old('email')) ?>"
                       <?= error_for('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                <?php if ($message = error_for('email')): ?>
                    <p class="field__error" id="email-error" role="alert"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <?php if ($message = error_for('password')): ?>
                    <p class="field__error" role="alert"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <button class="button button--block" type="submit">Se connecter</button>
        </form>

        <details class="auth__help">
            <summary>Mot de passe oublié&nbsp;?</summary>
            <p>
                Avec votre logiciel FTP (FileZilla), créez dans le dossier
                <code>storage/private/</code> un fichier nommé <code>reset-password.txt</code> contenant&nbsp;:
            </p>
            <pre>email=votre@adresse.com
password=VotreNouveauMotDePasse</pre>
            <p>
                Rechargez ensuite cette page : le mot de passe est changé, le blocage levé,
                et le fichier supprimé automatiquement. 10 caractères minimum.
            </p>
        </details>

        <p class="auth__back"><a href="<?= e(url('/')) ?>">Retour au site</a></p>
    </div>
</main>

</body>
</html>
