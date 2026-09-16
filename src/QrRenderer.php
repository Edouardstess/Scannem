<?php

declare(strict_types=1);

namespace Scannem;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use RuntimeException;

/**
 * Rendu des QR codes.
 *
 * Choix de correction d'erreur : niveau Q (25% de redondance). Une carte passe
 * des semaines dans un portefeuille, elle se plie et se raye ; avec le niveau L
 * par defaut, un quart de rayure suffit a la rendre illisible a la porte, et
 * c'est le vigile qui prend le refus en pleine figure.
 */
final class QrRenderer
{
    public function __construct(
        private readonly int $size = 320,
        private readonly int $margin = 16,
    ) {
    }

    private function qr(string $payload): QrCode
    {
        return new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: $this->size,
            margin: $this->margin,
        );
    }

    /**
     * L'export PNG est-il possible sur cet hebergement ?
     *
     * L'extension GD manque sur certains mutualises gratuits. Ce n'est pas
     * bloquant : la planche d'impression, qui est le livrable qui compte, est
     * rendue en SVG et n'en depend pas.
     */
    public static function pngDisponible(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    public function png(string $payload): string
    {
        if (!self::pngDisponible()) {
            throw new RuntimeException(
                "L'extension GD est absente de cet hebergement, l'export PNG est indisponible.\n"
                . "Utilise la planche d'impression, qui est en SVG et ne demande rien de plus."
            );
        }

        return (new PngWriter())->write($this->qr($payload))->getString();
    }

    public function svg(string $payload): string
    {
        return (new SvgWriter())->write($this->qr($payload))->getString();
    }

    public function dataUri(string $payload): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($payload));
    }

    /**
     * Ecrit un lot de PNG sur disque.
     *
     * @param list<array{uid:string, payload:string}> $cards
     * @return string le repertoire cree
     */
    public function writeBatch(array $cards, string $directory): string
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Impossible de creer $directory");
        }

        foreach ($cards as $card) {
            $file = $directory . '/' . $card['uid'] . '.png';

            if (file_put_contents($file, $this->png($card['payload'])) === false) {
                throw new RuntimeException("Ecriture impossible : $file");
            }
        }

        return $directory;
    }

    /**
     * Planche d'impression A4 : 10 cartes par page, format carte de visite.
     *
     * Rendue en HTML plutot qu'en PDF : l'imprimeur ouvre le fichier dans un
     * navigateur et imprime, sans dependance supplementaire ni police a embarquer.
     * Les QR sont en SVG pour rester nets a n'importe quelle resolution.
     *
     * @param list<array{uid:string, payload:string}> $cards
     */
    public function printSheet(array $cards, string $batchName, ?string $eventDate = null): string
    {
        $title = Http::escape($batchName);
        $date = $eventDate !== null ? Http::escape($eventDate) : '';

        $items = '';

        foreach ($cards as $card) {
            $svg = $this->svg($card['payload']);
            // Le code lisible sous le QR sert de secours quand la camera refuse
            // de cooperer : le vigile le saisit a la main.
            $human = Http::escape(substr($card['uid'], 0, 4) . ' ' . substr($card['uid'], 4, 4)
                . ' ' . substr($card['uid'], 8, 4) . ' ' . substr($card['uid'], 12, 4));

            $items .= <<<HTML
    <div class="card">
      <div class="card-head">
        <span class="card-title">$title</span>
        <span class="card-date">$date</span>
      </div>
      <div class="qr">$svg</div>
      <div class="code">$human</div>
    </div>

HTML;
        }

        $count = count($cards);

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Planche d'impression - $title ($count cartes)</title>
<style>
  :root { --ink: #111; --muted: #6b7280; --line: #d1d5db; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: "Helvetica Neue", Arial, sans-serif;
    color: var(--ink);
    background: #f3f4f6;
  }
  .toolbar {
    padding: 16px 24px;
    background: #fff;
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
  }
  .toolbar h1 { margin: 0 0 4px; font-size: 16px; }
  .toolbar p { margin: 0; font-size: 13px; color: var(--muted); }
  .sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 16px auto;
    padding: 10mm 8mm;
    background: #fff;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-auto-rows: 54mm;
    gap: 4mm;
    align-content: start;
  }
  .card {
    border: 1px dashed var(--line);
    border-radius: 3mm;
    padding: 3mm;
    display: grid;
    grid-template-columns: 1fr auto;
    grid-template-rows: auto 1fr;
    gap: 2mm;
    align-items: center;
    page-break-inside: avoid;
    break-inside: avoid;
  }
  .card-head { grid-column: 1; display: flex; flex-direction: column; gap: 1mm; }
  .card-title { font-size: 11pt; font-weight: 700; }
  .card-date { font-size: 8pt; color: var(--muted); }
  .qr { grid-column: 2; grid-row: 1 / span 2; width: 34mm; height: 34mm; }
  .qr svg { width: 100%; height: 100%; display: block; }
  .code {
    grid-column: 1;
    grid-row: 2;
    font-family: "SFMono-Regular", Consolas, monospace;
    font-size: 8pt;
    letter-spacing: 0.4px;
    color: var(--muted);
    align-self: end;
  }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; width: auto; min-height: auto; box-shadow: none; }
    .card { border-color: #e5e7eb; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <h1>$title &mdash; $count cartes</h1>
  <p>Imprime en A4, sans mise a l'echelle (100%). Verifie qu'un QR se scanne avant de lancer tout le tirage.</p>
</div>
<div class="sheet">
$items</div>
</body>
</html>
HTML;
    }
}
