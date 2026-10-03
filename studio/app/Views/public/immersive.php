<?php
/**
 * Immersive homepage — template « Site Immersif » (.claude/skills/site-immersif-skill).
 *
 * The markup is the template's index.html, unchanged in structure, ids and
 * classes: public/assets/immersif/app.js animates it by those hooks. What the
 * template's content.js injected in the browser is rendered here by PHP from
 * the database instead (same resulting DOM), so the page is readable by
 * search engines and needs no inline script. app.js still reads
 * window.SITE_CONTENT, which boot.js fills from the JSON block below.
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $content    ImmersiveHomeService::content()
 * @var string               $theme      light | dark
 * @var string               $accent     #rrggbb
 * @var string               $accentInk  #rrggbb
 * @var string               $ctaUrl     where « Réserver » leads
 * @var string               $currentPath
 */

use App\Core\View;

$c = $content;
$brand = $c['brand'];
$canonical = canonical_url(ltrim($currentPath ?? '/', '/'));
$ogImage = (string) $c['hook']['image'];

// The template's drawn oval, shared by the manifesto, the steps CTA and the pill.
$oval = 'M50,6 C88,4 98,22 97,50 C96,82 76,96 49,95 C16,94 3,76 4,48 C5,18 20,7 50,6 Z';

// Manifesto: the [[…]] words get the hand-drawn oval (template injection rule).
$manifestoParts = preg_split('/\[\[(.+?)\]\]/u', (string) $c['manifesto']['text'], 2, PREG_SPLIT_DELIM_CAPTURE) ?: [''];

// Testimonial figure split like the template: prefix (+, −, ×) and value.
preg_match('/^([^\d.,+-]*[+\x{2212}-]?)\s*(-?[\d.,]+)/u', trim((string) $c['testimonial']['figure']), $fig);

// Floater positions and sizes, verbatim from the template.
$floaterSlots = [
    ['-4%', '13%', '196px', '0.55'], ['21%', '3%', '142px', '0.90'], ['40%', '-8%', '232px', '0.40'],
    ['63%', '7%', '126px', '0.75'], ['85%', '1%', '208px', '1.00'], ['-2%', '45%', '116px', '0.50'],
    ['94%', '37%', '172px', '0.70'], ['13%', '71%', '248px', '0.35'], ['44%', '81%', '134px', '0.85'],
    ['73%', '64%', '188px', '0.60'],
];
$trailWidths = [150, 190, 120, 200, 96, 170, 34, 140, 200, 110, 28, 160, 130, 180, 40, 150, 120, 200, 100, 170];
$speeds = ['-0.05', '0.06', '-0.028', '0.085'];
$projects = $c['proof']['projects'];
$isAnchor = str_starts_with($ctaUrl, '#');
?>
<!DOCTYPE html>
<html lang="fr"<?= $theme === 'dark' ? ' data-theme="dark"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e((string) $brand['title']) ?></title>
  <meta name="description" content="<?= e(str_excerpt((string) $brand['description'], 160)) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e((string) $brand['name']) ?>">
  <meta property="og:title" content="<?= e((string) $brand['title']) ?>">
  <meta property="og:description" content="<?= e(str_excerpt((string) $brand['description'], 160)) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('immersif/styles.css')) ?>">
  <style>
    /* Accent chosen in Admin → Page d'accueil (validated #rrggbb). */
    :root { --lime: <?= e($accent) ?>; --lime-ink: <?= e($accentInk) ?>; }
    /* Reduced-motion hero: the photographer's own image, not the placeholder. */
    body.static .spot::before { background-image: url("<?= e((string) $c['hook']['image']) ?>"); }
  </style>
  <?= View::include('partials.schema', ['settings' => $settings, 'currentPath' => $currentPath ?? '/']) ?>
</head>
<body>

  <!-- ══════════ PRELOADER ══════════ -->
  <div class="loader" id="loader" aria-hidden="true">
    <div class="loader-inner">
      <p class="loader-wordmark"><?= e((string) $brand['name']) ?></p>
      <span class="loader-rule"><i id="loaderBar"></i></span>
    </div>
  </div>

  <!-- ══════════ CONTENU ══════════ -->
  <main class="smooth" id="smooth">

    <!-- ─── SCÈNE 1 · PASTEBOARD → ZOOM TEXTE → SÉRIE EN LUMIÈRE ─── -->
    <section class="scene s-hero" id="hero" data-pin="5">
      <div class="pin">

        <div class="floaters" aria-hidden="true">
          <?php foreach ($floaterSlots as $i => [$x, $y, $w, $d]): ?>
            <figure class="fl" style="--x:<?= e($x) ?>; --y:<?= e($y) ?>; --w:<?= e($w) ?>" data-d="<?= e($d) ?>"><img src="<?= e((string) ($c['hook']['floaters'][$i] ?? '')) ?>" alt=""></figure>
          <?php endforeach; ?>
        </div>

        <div class="hero-copy">
          <p class="hero-kicker mono" id="heroKicker"><?= e((string) $brand['kicker']) ?></p>
          <h1 class="hero-title">
            <span class="hero-line1" id="heroLine1"><?= e((string) $c['hook']['line1']) ?></span>
            <span class="hero-line2" id="heroLine2">
              <span class="hl"><?= e((string) $c['hook']['line2a']) ?></span>
              <span class="grow" id="grow1"><img src="<?= e((string) $c['hook']['image']) ?>" alt="<?= e((string) $c['hook']['imageAlt']) ?>"></span>
              <span class="hl"><?= e((string) $c['hook']['line2b']) ?></span>
            </span>
          </h1>
        </div>

        <!-- calque plein écran : titre blanc sur l'image -->
        <div class="spot" id="spot" aria-hidden="true">
          <h2 class="spot-intro" id="spotIntro"><?php foreach (explode(' ', (string) $c['positioning']) as $wi => $word): ?><?= $wi > 0 ? ' ' : '' ?><span><?= e($word) ?></span><?php endforeach; ?></h2>
        </div>

      </div>
    </section>

    <!-- ─── SCÈNE 2 · MANIFESTE (remplissage caractère par caractère) ─── -->
    <section class="scene s-fill" id="manifeste" data-pin="3.5">
      <div class="pin">
        <p class="fill-text" id="fillText"><?= e($manifestoParts[0]) ?><?php if (isset($manifestoParts[1])): ?><span class="boxed" id="boxedPhrase"><?= e($manifestoParts[1]) ?><svg class="box-svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path id="boxPath" d="<?= e($oval) ?>"/></svg></span><?= e($manifestoParts[2] ?? '') ?><?php endif; ?></p>
      </div>
    </section>

    <!-- ─── SCÈNE 3 · SÉLECTION (panneau blanc, masonry parallaxe) ─── -->
    <section class="collection" id="travaux">
      <header class="coll-head">
        <p class="mono ash"><?= e((string) $c['proof']['kicker']) ?></p>
        <h2 class="coll-title" id="collTitle"><?= e((string) $c['proof']['title']) ?></h2>
        <p class="coll-sub"><?= e((string) $c['proof']['sub']) ?></p>
        <p class="mono ash"><?= e((string) $c['proof']['meta']) ?></p>
      </header>

      <div id="collGrid" class="coll-grid"><?php foreach ($speeds as $ci => $speed): ?><div class="col" data-pspeed="<?= e($speed) ?>"><?php foreach (array_slice($projects, $ci * 2, 2) as $p): ?><figure class="card"><div class="card-img"><img src="<?= e((string) $p['img']) ?>" alt="<?= e($p['title'] . ' — ' . $p['meta']) ?>" loading="lazy"></div><figcaption><?= e((string) $p['title']) ?><span class="mono"><?= e((string) $p['meta']) ?></span></figcaption></figure><?php endforeach; ?></div><?php endforeach; ?></div>
    </section>

    <!-- ─── 5 · DEVISE — train de mots-clés géants sur bande accent ─── -->
    <div class="band" id="temps">
      <div class="diag to-lime" aria-hidden="true"></div>
      <section class="scene s-motto" data-pin="4">
        <div class="pin motto-pin">
          <p class="motto-kicker mono" id="mottoKicker"><?= e((string) $c['motto']['kicker']) ?></p>
          <div class="motto-track" id="mottoTrack"><?php foreach ($c['motto']['words'] as $word): ?><span class="mw"><?= e((string) $word['word']) ?></span><?php endforeach; ?></div>
          <p class="motto-hint mono" id="mottoHint"></p>
        </div>
      </section>
      <div class="diag from-lime" aria-hidden="true"></div>
    </div>

    <!-- ─── SCÈNE 5 · « UNE SÉANCE, 3 ÉTAPES » → LE PROCESSUS IMMERSIF ─── -->
    <section class="scene s-night" id="explorer" data-pin="9">
      <div class="pin">

        <div class="night-line" id="nightLine">
          <span class="hl nw" id="nw1"><?= e((string) $c['universes']['introA']) ?></span>
          <span class="hl nw" id="nw2"><?= e((string) $c['universes']['introB']) ?></span>
          <span class="grow" id="grow2"><img src="<?= e((string) $c['universes']['image']) ?>" alt=""></span>
          <span class="hl nw" id="nw3"><?= e((string) $c['universes']['introC']) ?></span>
        </div>

        <!-- compteur monumental : le numéro d'étape défile comme un odomètre -->
        <div class="bignum" id="bigNum" aria-hidden="true"><span class="bignum-fixed">0</span><span class="bignum-roll"><span id="bigRoll"></span></span></div>

        <!-- contenu de l'étape, à gauche -->
        <div class="psteps" id="psteps"><?php foreach ($c['universes']['items'] as $step): ?><div class="pstep"><span class="pstep-meta mono ash"><?= e((string) $step['meta']) ?></span><h3><?= e((string) $step['name']) ?></h3><p><?= e((string) $step['desc']) ?></p></div><?php endforeach; ?></div>

        <p class="steps-cta" id="stepsCta">
          <a href="<?= e($ctaUrl) ?>"<?= $isAnchor ? ' data-nav' : '' ?> id="stepsCtaLink"><?= e((string) $c['universes']['cta']) ?><svg class="cta-oval" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path id="ctaOval" d="<?= e($oval) ?>"/></svg></a>
        </p>

        <!-- rideau de lames : essuie le plein écran SANS jamais dézoomer -->
        <div class="wipe" id="nightWipe" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>

      </div>
    </section>

    <!-- ─── 8 · PREUVE SOCIALE — citation en rideau, guillemets géants ─── -->
    <section class="scene s-quote" id="temoignage" data-pin="3">
      <div class="pin">
        <div class="q-stack">
          <span class="q-glyph" aria-hidden="true">“</span>
          <blockquote class="q-block">
            <p class="q-text" id="quoteText"><?= e((string) $c['testimonial']['quote']) ?></p>
          </blockquote>
          <div class="q-sign">
            <span class="q-rule" aria-hidden="true"></span>
            <div class="q-sign-row" id="quoteAuthorWrap">
              <p class="q-name" id="quoteAuthor"><?= e((string) $c['testimonial']['author']) ?></p>
              <p class="q-ctx mono ash" id="figKicker"><?= e((string) $c['testimonial']['kicker']) ?></p>
              <p class="q-fig" id="bigFigure"><span class="fig-pre" id="figPre"><?= e((string) ($fig[1] ?? '')) ?></span><span class="fig-val" id="figVal"><?= e((string) ($fig[2] ?? '')) ?></span><span class="fig-unit" id="figUnit"><?= e((string) $c['testimonial']['unit']) ?></span></p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── SCÈNE 9 · OBJECTIONS — PHRASES + TRAÎNÉE D'IMAGES SOUS LA SOURIS ─── -->
    <section class="scene s-final" id="final" data-pin="2.6">
      <div class="pin">
        <p class="final-text">
          <span class="fs" id="fs1"><?= e((string) $c['objections']['items'][0]) ?></span>
          <span class="fs" id="fs2"><?= e((string) $c['objections']['items'][1]) ?></span>
          <span class="fs" id="fs3"><?= e((string) $c['objections']['items'][2]) ?></span><br>
          <span class="fs" id="fs4"><?= e((string) $c['objections']['finale']) ?> <span class="pill" id="pillPhrase"><?= e((string) $c['objections']['pill']) ?><svg class="pill-svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path id="pillPath" d="<?= e($oval) ?>"/></svg></span></span>
        </p>

        <!-- réserve d'images pour la traînée sous la souris -->
        <div class="trail" id="trail" aria-hidden="true">
          <?php foreach ($trailWidths as $i => $tw): ?>
            <img src="<?= e((string) ($c['trail'][$i] ?? '')) ?>" style="--tw:<?= (int) $tw ?>px" alt="" loading="lazy">
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ─── FOOTER ─── -->
    <footer class="footer" id="contact">
      <p class="footer-kicker mono ash reveal"><?= e((string) $c['contact']['kicker']) ?></p>
      <?php if ((string) $c['contact']['email'] !== ''): ?>
        <a class="footer-mail reveal" href="mailto:<?= e((string) $c['contact']['email']) ?>"><span class="footer-mail-text"><?= e((string) $c['contact']['email']) ?></span></a>
      <?php else: ?>
        <a class="footer-mail reveal" href="<?= e(url('/contact')) ?>"><span class="footer-mail-text">Écrivez-moi</span></a>
      <?php endif; ?>
      <p class="footer-reassurance mono ash reveal"><?= e((string) $c['contact']['reassurance']) ?></p>
      <h2 class="footer-name" id="footerName" aria-label="<?= e((string) $brand['name']) ?>"><?= e((string) $brand['name']) ?></h2>
      <div class="footer-bottom">
        <p class="mono ash"><?= e((string) $brand['copyright']) ?></p>
        <?php
        // The rest of the site lives on its own pages; socials open outside.
        $links = [['PORTFOLIO', url('/portfolio'), false], ['PRESTATIONS', url('/services'), false], ['CONTACT', url('/contact'), false]];
        if (!empty($settings['client_area_enabled'])) {
            $links[] = ['ESPACE CLIENT', url('/espace-client'), false];
        }
        foreach ($brand['socials'] as $social) {
            $links[] = [(string) $social['label'], (string) $social['url'], true];
        }
        ?>
        <p class="mono"><?php foreach ($links as $li => [$label, $href, $external]): ?><?= $li > 0 ? '&nbsp;&nbsp;&nbsp;' : '' ?><a href="<?= e($href) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></p>
        <p class="mono ash"><?= e((string) $brand['signature']) ?></p>
      </div>
    </footer>

  </main>

  <!-- ══════════ CHROME FIXE — barre flottante en haut ══════════ -->

  <header class="dock" id="dock">
    <a class="dock-wordmark" href="#hero" data-nav><?= e((string) $brand['name']) ?></a>
    <nav class="dock-nav" aria-label="Navigation principale">
      <a class="dock-link mono" href="#travaux" data-nav><?= e((string) $c['nav']['proof']) ?></a>
      <a class="dock-link mono" href="#explorer" data-nav data-landing="0.8"><?= e((string) $c['nav']['universes']) ?></a>
    </nav>
    <a class="dock-cta mono" href="<?= e($ctaUrl) ?>"<?= $isAnchor ? ' data-nav' : '' ?>><?= e((string) $c['nav']['cta']) ?></a>
  </header>

  <script type="application/json" id="site-content"><?= ejs($content) ?></script>
  <script src="<?= e(asset('immersif/boot.js')) ?>"></script>
  <script src="<?= e(asset('immersif/app.js')) ?>"></script>
</body>
</html>
