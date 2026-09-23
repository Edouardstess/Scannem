<?php
/**
 * Inline-SVG time series chart.
 *
 * Rendered server-side rather than by a charting library: the site loads no
 * third-party JavaScript, and the Content-Security-Policy forbids inline
 * scripts, so the hover layer is attached by admin.js from the data attribute
 * below instead of from a script tag here.
 *
 * Two series maximum by design. A third measure gets its own chart rather than
 * a second y-axis.
 *
 * @var string                                                                  $id
 * @var array<int, string>                                                      $labels
 * @var array<int, array{label: string, values: array<int, int>, variant: string}> $series
 */

$width = 840;
$height = 240;
$padLeft = 44;
$padRight = 16;
$padTop = 12;
$padBottom = 28;

$plotWidth = $width - $padLeft - $padRight;
$plotHeight = $height - $padTop - $padBottom;

$count = count($labels);
$max = 0;

foreach ($series as $entry) {
    foreach ($entry['values'] as $value) {
        $max = max($max, (int) $value);
    }
}

// A flat-zero chart still needs a sensible axis, and rounding the top to a
// "nice" number keeps the gridline labels readable.
$niceMax = static function (int $value): int {
    if ($value <= 4) {
        return 4;
    }

    $magnitude = 10 ** max(0, (int) floor(log10($value)));

    foreach ([1, 2, 2.5, 5, 10] as $step) {
        $candidate = (int) ceil($value / ($step * $magnitude)) * ($step * $magnitude);

        if ($candidate >= $value && $candidate / $magnitude <= 10) {
            return (int) $candidate;
        }
    }

    return (int) ($magnitude * 10);
};

$top = $niceMax($max);

$x = static function (int $index) use ($count, $padLeft, $plotWidth): float {
    if ($count <= 1) {
        return $padLeft + ($plotWidth / 2);
    }

    return $padLeft + ($index * ($plotWidth / ($count - 1)));
};

$y = static function (float $value) use ($top, $padTop, $plotHeight): float {
    $ratio = $top === 0 ? 0.0 : $value / $top;

    return $padTop + $plotHeight - ($ratio * $plotHeight);
};

$gridValues = [0, (int) round($top / 2), $top];

// Labels are dates; showing 30 of them collides, so a handful are kept.
$tickEvery = max(1, (int) ceil($count / 6));

$shortLabel = static function (string $label): string {
    $timestamp = strtotime($label);

    if ($timestamp === false) {
        return $label;
    }

    return date(strlen($label) === 7 ? 'm/Y' : 'd/m', $timestamp);
};

$chartData = [
    'labels' => array_map(static fn (string $label): string => $shortLabel($label), $labels),
    'raw'    => $labels,
    'series' => array_map(
        static fn (array $entry): array => [
            'label'   => $entry['label'],
            'values'  => array_map('intval', $entry['values']),
            'variant' => $entry['variant'],
        ],
        $series
    ),
    'geometry' => [
        'padLeft' => $padLeft, 'padRight' => $padRight,
        'padTop'  => $padTop, 'padBottom' => $padBottom,
        'width'   => $width, 'height' => $height, 'max' => $top,
    ],
];
?>
<figure class="chart" data-chart-id="<?= e($id) ?>">
    <?php if (count($series) > 1): ?>
        <figcaption class="chart__legend">
            <?php foreach ($series as $entry): ?>
                <span class="chart__legend-item">
                    <span class="chart__swatch chart__swatch--<?= e($entry['variant']) ?>" aria-hidden="true"></span>
                    <?= e($entry['label']) ?>
                </span>
            <?php endforeach; ?>
        </figcaption>
    <?php endif; ?>

    <div class="chart__canvas">
        <svg class="chart__svg"
             viewBox="0 0 <?= (int) $width ?> <?= (int) $height ?>"
             role="img"
             aria-label="<?= e(implode(' et ', array_column($series, 'label'))) ?> par jour"
             data-chart='<?= e(json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>

            <g class="chart__grid">
                <?php foreach ($gridValues as $value): ?>
                    <line x1="<?= (int) $padLeft ?>" x2="<?= (int) ($width - $padRight) ?>"
                          y1="<?= round($y((float) $value), 2) ?>" y2="<?= round($y((float) $value), 2) ?>"></line>
                    <text class="chart__axis-label" x="<?= (int) ($padLeft - 8) ?>"
                          y="<?= round($y((float) $value) + 4, 2) ?>" text-anchor="end"><?= (int) $value ?></text>
                <?php endforeach; ?>
            </g>

            <g class="chart__ticks">
                <?php foreach ($labels as $index => $label): ?>
                    <?php if ($index % $tickEvery !== 0 && $index !== $count - 1) {
                        continue;
                    } ?>
                    <text class="chart__axis-label" x="<?= round($x($index), 2) ?>"
                          y="<?= (int) ($height - 8) ?>" text-anchor="middle"><?= e($shortLabel((string) $label)) ?></text>
                <?php endforeach; ?>
            </g>

            <?php foreach ($series as $entry): ?>
                <?php
                $points = [];

                foreach ($entry['values'] as $index => $value) {
                    $points[] = round($x((int) $index), 2) . ',' . round($y((float) $value), 2);
                }
                ?>
                <polyline class="chart__line chart__line--<?= e($entry['variant']) ?>"
                          points="<?= e(implode(' ', $points)) ?>"
                          vector-effect="non-scaling-stroke"></polyline>
            <?php endforeach; ?>

            <g class="chart__hover" data-chart-hover hidden>
                <line class="chart__crosshair" y1="<?= (int) $padTop ?>" y2="<?= (int) ($padTop + $plotHeight) ?>"></line>
                <?php foreach ($series as $entry): ?>
                    <circle class="chart__dot chart__dot--<?= e($entry['variant']) ?>" r="5"></circle>
                <?php endforeach; ?>
            </g>

            <rect class="chart__surface" data-chart-surface
                  x="<?= (int) $padLeft ?>" y="<?= (int) $padTop ?>"
                  width="<?= (int) $plotWidth ?>" height="<?= (int) $plotHeight ?>" fill="transparent"></rect>
        </svg>

        <div class="chart__tooltip" data-chart-tooltip hidden role="status" aria-live="polite"></div>
    </div>

    <?php /* The table is the accessible equivalent, and the fallback when colour alone fails. */ ?>
    <details class="chart__table">
        <summary>Voir les données</summary>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <?php foreach ($series as $entry): ?>
                            <th scope="col"><?= e($entry['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labels as $index => $label): ?>
                        <tr>
                            <th scope="row"><?= e((string) $label) ?></th>
                            <?php foreach ($series as $entry): ?>
                                <td><?= (int) ($entry['values'][$index] ?? 0) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
</figure>
