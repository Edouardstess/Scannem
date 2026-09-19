<?php
/** Messages à usage unique. Affiché par les layouts : jamais par les vues. */
$messages = Flash::consommer();
?>
<?php if ($messages !== []): ?>
    <div class="flash-zone" role="status" aria-live="polite">
        <?php foreach ($messages as $message): ?>
            <div class="flash flash-<?= e($message['type']) ?>">
                <i class="bi <?= e(Flash::icone($message['type'])) ?>" aria-hidden="true"></i>
                <span><?= e($message['message']) ?></span>
                <button type="button" class="flash-fermer" data-fermer-flash aria-label="Fermer ce message">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
