<?php
/** @var array<int, array{type: string, message: string}> $flashes */

if (($flashes ?? []) === []) {
    return;
}
?>
<div class="flashes" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <?php $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
        <div class="flash flash--<?= e($type) ?>">
            <span><?= e($flash['message']) ?></span>
            <button type="button" class="flash__close" data-dismiss aria-label="Fermer">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
