<?php
/**
 * A line whose words rise from their baseline when it scrolls into view.
 * Wrap it in an element with class "split"; pages.js adds "is-in".
 *
 * @var string $text
 * @var int    $offset  stagger start, to chain several lines
 */

$words = preg_split('/\s+/u', trim((string) ($text ?? ''))) ?: [];
$last = count($words) - 1;
$offset = (int) ($offset ?? 0);
?>
<?php foreach ($words as $i => $word): ?><span class="w"><span style="--i: <?= (int) ($i + $offset) ?>"><?= e($word) ?></span></span><?= $i < $last ? ' ' : '' ?><?php endforeach; ?>