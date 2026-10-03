<?php
/**
 * Words circled by hand, as on the homepage. The stroke draws itself when
 * its parent is revealed.
 *
 * @var string $text
 */
?>
<span class="oval"><?= e((string) ($text ?? '')) ?><svg class="oval-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path pathLength="1" d="M50,6 C88,4 98,22 97,50 C96,82 76,96 49,95 C16,94 3,76 4,48 C5,18 20,7 50,6 Z"/></svg></span>