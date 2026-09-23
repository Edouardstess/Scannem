<?php
/** @var array<string, mixed> $message */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/messages')) ?>">← Tous les messages</a>
</div>

<section class="panel">
    <dl class="definition">
        <div><dt>De</dt><dd><?= e((string) $message['name']) ?></dd></div>
        <div><dt>E-mail</dt><dd>
            <a href="mailto:<?= e((string) $message['email']) ?>"><?= e((string) $message['email']) ?></a>
        </dd></div>
        <div><dt>Téléphone</dt><dd><?= e((string) ($message['phone'] ?? '')) ?: '—' ?></dd></div>
        <div><dt>Sujet</dt><dd><?= e((string) ($message['subject'] ?? '')) ?: '—' ?></dd></div>
        <div><dt>Reçu le</dt><dd><?= e(format_datetime((string) $message['created_at'])) ?></dd></div>
    </dl>

    <div class="note-block">
        <p><?= nl2br(e((string) $message['message'])) ?></p>
    </div>

    <div class="panel__footer">
        <a class="button" href="mailto:<?= e((string) $message['email']) ?>?subject=<?= e(rawurlencode('Re: ' . (string) ($message['subject'] ?? ''))) ?>">
            Répondre par e-mail
        </a>

        <form method="post" action="<?= e(url('/admin/messages/' . (int) $message['id'] . '/status')) ?>"
              class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="archived">
            <button class="button button--ghost" type="submit">Archiver</button>
        </form>

        <form method="post" action="<?= e(url('/admin/messages/' . (int) $message['id'])) ?>"
              data-confirm="Supprimer définitivement ce message ?">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button class="button button--danger-ghost" type="submit">Supprimer</button>
        </form>
    </div>
</section>

<?php View::endSection(); ?>
