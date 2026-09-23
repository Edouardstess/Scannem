<?php
/** @var array<string, mixed>|null $auth */

use App\Core\View;
use App\Models\Role;

View::extend('layouts.admin');
View::startSection('content');
?>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Mon compte</h2></header>

    <dl class="definition">
        <div><dt>Nom</dt><dd><?= e((string) ($auth['name'] ?? '')) ?></dd></div>
        <div><dt>E-mail</dt><dd><?= e((string) ($auth['email'] ?? '')) ?></dd></div>
        <div><dt>Rôle</dt><dd><?= e(Role::label((string) ($auth['role'] ?? ''))) ?></dd></div>
        <div><dt>Dernière connexion</dt><dd><?= e(format_datetime($auth['last_login_at'] ?? null)) ?></dd></div>
    </dl>

    <details class="permissions">
        <summary>Permissions de ce rôle</summary>
        <ul class="permissions__list">
            <?php foreach (Role::permissionsFor((string) ($auth['role'] ?? '')) as $permission): ?>
                <li class="mono"><?= e($permission) ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
</section>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Changer de mot de passe</h2></header>

    <form class="form" method="post" action="<?= e(url('/admin/profile/password')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" required
                   autocomplete="current-password">
            <?php if ($message = error_for('current_password')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="password">Nouveau mot de passe</label>
            <input type="password" id="password" name="password" required minlength="10"
                   autocomplete="new-password">
            <p class="field__hint">10 caractères minimum. Une phrase de passe vaut mieux qu'un mot compliqué.</p>
            <?php if ($message = error_for('password')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required
                   autocomplete="new-password">
        </div>

        <button class="button" type="submit">Modifier le mot de passe</button>
        <p class="form__note">Vous serez déconnecté et devrez vous reconnecter.</p>
    </form>
</section>

<?php View::endSection(); ?>
