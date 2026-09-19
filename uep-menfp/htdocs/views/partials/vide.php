<?php
/**
 * État vide d'une liste.
 * @var string $icone   Classe d'icône Bootstrap Icons
 * @var string $titre
 * @var string $message
 * @var string $action  HTML optionnel (bouton)
 */
?>
<div class="etat-vide">
    <i class="bi <?= e($icone ?? 'bi-inbox') ?>" aria-hidden="true"></i>
    <h3><?= e($titre ?? 'Aucune donnée') ?></h3>
    <p><?= e($message ?? '') ?></p>
    <?php if (!empty($action)): ?><div class="etat-vide-action"><?= $action ?></div><?php endif; ?>
</div>
