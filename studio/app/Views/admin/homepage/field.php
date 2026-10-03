<?php
/**
 * One text field of Admin → Page d'accueil.
 *
 * @var string $key
 * @var string $label
 * @var string $value
 * @var string $hint
 * @var int    $max
 * @var bool   $long
 */
$id = 'hp_' . $key;
?>
<div class="field">
    <label for="<?= e($id) ?>"><?= e($label) ?></label>
    <?php if (!empty($long)): ?>
        <textarea id="<?= e($id) ?>" name="<?= e($key) ?>" rows="3" maxlength="<?= (int) $max ?>"><?= e($value) ?></textarea>
    <?php else: ?>
        <input type="text" id="<?= e($id) ?>" name="<?= e($key) ?>" maxlength="<?= (int) $max ?>" value="<?= e($value) ?>">
    <?php endif; ?>
    <?php if (($hint ?? '') !== ''): ?>
        <p class="field__hint"><?= e($hint) ?></p>
    <?php endif; ?>
    <?php if ($message = error_for($key)): ?>
        <p class="field__error"><?= e($message) ?></p>
    <?php endif; ?>
</div>
