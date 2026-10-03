/* ═══════════════════════════════════════════════════════════
   TEMPLATE « SITE IMMERSIF » — content.js
   ► L'UNIQUE FICHIER À RÉÉCRIRE pour produire un nouveau site.
   Renommer en content.js dans le projet cible. La partie
   « injection » en bas de fichier est le moteur de remplissage :
   la copier TELLE QUELLE, ne réécrire que window.SITE_CONTENT.

   Schéma narratif (rôle de conversion de chaque bloc) :
   1. ACCROCHE       — hook : promesse + identité en 3 secondes
   2. POSITIONNEMENT — positioning : ce que je fais, pour qui, où
   3. DÉMARCHE       — manifesto : pourquoi moi (différenciation)
   4. PREUVE         — proof : réalisations (masonry) OU features (bento)
   5. DEVISE         — motto : 3 mots-clés géants + légendes
   6-7. PROCESSUS    — universes : « X en 3 étapes » + visuels posés
   8. PREUVE SOCIALE — testimonial : un client parle
   9. OBJECTIONS     — objections : « Pas de… Juste… »
   10. CONVERSION    — contact : e-mail + réassurance
   ═══════════════════════════════════════════════════════════ */

window.SITE_CONTENT = {

  brand: {
    name: 'L’ENFANT VISUAL',
    title: 'L’ENFANT VISUAL — Photographe mariage, portrait et événement',
    description: 'L’ENFANT VISUAL, photographe : mariages, portraits, familles, événements et entreprises. Vos images livrées dans une galerie privée.',
    kicker: 'L’ENFANT VISUAL — PHOTOGRAPHE',
    copyright: '© 2026 — L’ENFANT VISUAL',
    signature: 'PHOTOGRAPHIÉ AVEC SOIN',
    socials: []
  },

  nav: { proof: 'TRAVAUX', universes: 'ÉTAPES', cta: 'RÉSERVER' },

  hook: {
    line1: 'Vos plus beaux moments,',
    line2a: 'gardés',
    line2b: 'pour toujours.',
    image: 'images/hero.jpg',
    imageAlt: 'Une photographie de L’ENFANT VISUAL en plein écran',
    floaters: [
      'images/fl-01.jpg', 'images/fl-02.jpg', 'images/fl-03.jpg', 'images/fl-04.jpg', 'images/fl-05.jpg',
      'images/fl-06.jpg', 'images/fl-07.jpg', 'images/fl-08.jpg', 'images/fl-09.jpg', 'images/fl-10.jpg'
    ]
  },

  positioning: 'Mariage, portrait, famille et événement.',

  manifesto: {
    text: 'Je photographie ce qui ne se rejoue pas : un regard, un rire, une main qui tremble avant le oui. Pas de poses figées, seulement des [[instants vrais]], que vous garderez toute votre vie.'
  },

  proof: {
    layout: 'masonry',
    kicker: 'TRAVAUX CHOISIS',
    title: 'Huit histoires en images',
    sub: 'Mariages, portraits, familles, entreprises : chaque séance a sa propre lumière.',
    meta: 'HUIT SÉANCES — 2024 → 2026',
    projects: [
      { img: 'images/pr-1.jpg', title: 'Mariage au jardin', meta: 'MARIAGE — 2025' },
      { img: 'images/pr-2.jpg', title: 'Portrait en lumière douce', meta: 'PORTRAIT — 2025' },
      { img: 'images/pr-3.jpg', title: 'Dimanche en famille', meta: 'FAMILLE — 2024' },
      { img: 'images/pr-4.jpg', title: 'Premiers jours', meta: 'NAISSANCE — 2026' },
      { img: 'images/pr-5.jpg', title: 'Lancement de marque', meta: 'ENTREPRISE — 2025' },
      { img: 'images/pr-6.jpg', title: 'Soirée de gala', meta: 'ÉVÉNEMENT — 2024' },
      { img: 'images/pr-7.jpg', title: 'Séance en studio', meta: 'STUDIO — 2026' },
      { img: 'images/pr-8.jpg', title: 'La cérémonie', meta: 'MARIAGE — 2026' }
    ]
  },

  motto: {
    kicker: 'CE QUI GUIDE CHAQUE SÉANCE',
    words: [
      { word: 'Lumière', hint: 'Chercher la plus belle, à chaque heure du jour.' },
      { word: 'Émotion', hint: 'Saisir ce qui se passe vraiment, sans le mettre en scène.' },
      { word: 'Mémoire', hint: 'Des images faites pour traverser les années.' }
    ]
  },

  universes: {
    introA: 'Une',
    introB: 'séance,',
    introC: '3 étapes.',
    cta: 'Réserver →',
    image: 'images/process.jpg',
    items: [
      { name: 'On se parle', meta: 'ÉTAPE — 01', desc: 'Un échange pour comprendre votre projet, le lieu, l’ambiance et ce que vous attendez des images.' },
      { name: 'Le jour J', meta: 'ÉTAPE — 02', desc: 'Je photographie sans vous diriger, et je reste disponible pour les portraits que vous souhaitez.' },
      { name: 'Votre galerie', meta: 'ÉTAPE — 03', desc: 'Vos photos retouchées arrivent dans une galerie privée, à partager et à télécharger en haute définition.' }
    ]
  },

  testimonial: {
    kicker: 'MARIAGE — 180 INVITÉS',
    figure: '3',
    unit: 'sem.',
    quote: 'Nous avons reçu notre galerie trois semaines après le mariage. Toute la famille a pu voir et télécharger les photos, même ceux qui vivent loin.',
    author: 'SARAH ET MARC — MARIÉS EN 2025'
  },

  objections: {
    items: ['Pas de poses figées.', 'Pas de surprise sur le prix.', 'Pas de photos perdues.'],
    finale: 'Juste vos',
    pill: 'souvenirs.'
  },

  contact: {
    kicker: 'UN PROJET EN TÊTE ?',
    email: 'contact@lenfantvisual.com',
    reassurance: 'RÉPONSE SOUS 48 H — DEVIS GRATUIT, SANS ENGAGEMENT'
  },

  trail: [
    'images/tr-01.jpg', 'images/tr-02.jpg', 'images/tr-03.jpg', 'images/tr-04.jpg', 'images/tr-05.jpg',
    'images/tr-06.jpg', 'images/tr-07.jpg', 'images/tr-08.jpg', 'images/tr-09.jpg', 'images/tr-10.jpg',
    'images/tr-11.jpg', 'images/tr-12.jpg', 'images/tr-13.jpg', 'images/tr-14.jpg', 'images/tr-15.jpg',
    'images/tr-16.jpg', 'images/tr-17.jpg', 'images/tr-18.jpg', 'images/tr-19.jpg', 'images/tr-20.jpg'
  ]
};

/* ═══════════════════════════════════════════════════════════
   INJECTION — NE PAS MODIFIER (remplit le DOM avant app.js)
   ═══════════════════════════════════════════════════════════ */
(() => {
  const C = window.SITE_CONTENT;
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => [...document.querySelectorAll(s)];
  const set = (sel, txt) => { const el = $(sel); if (el) el.textContent = txt; };

  document.title = C.brand.title;
  const md = document.querySelector('meta[name="description"]');
  if (md) md.setAttribute('content', C.brand.description);

  // chrome
  set('.loader-wordmark', C.brand.name);
  set('.dock-wordmark', C.brand.name);
  set('.dock-link[href="#travaux"]', C.nav.proof);
  set('.dock-link[href="#explorer"]', C.nav.universes);
  set('.dock-cta', C.nav.cta);

  // 1 · accroche
  set('#heroKicker', C.brand.kicker);
  set('#heroLine1', C.hook.line1);
  const hls = $$('#heroLine2 .hl');
  if (hls.length === 2) { hls[0].textContent = C.hook.line2a; hls[1].textContent = C.hook.line2b; }
  const g1 = $('#grow1 img');
  if (g1) { g1.src = C.hook.image; g1.alt = C.hook.imageAlt; }
  $$('.floaters .fl img').forEach((img, i) => { if (C.hook.floaters[i]) img.src = C.hook.floaters[i]; });

  // 2 · positionnement (un span par mot)
  const intro = $('#spotIntro');
  if (intro) intro.innerHTML = C.positioning.split(' ').map((w) => `<span>${w}</span>`).join(' ');

  // 3 · démarche
  const fill = $('#fillText');
  if (fill) {
    fill.innerHTML = C.manifesto.text.replace(
      /\[\[(.+?)\]\]/,
      '<span class="boxed" id="boxedPhrase">$1<svg class="box-svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path id="boxPath" d="M50,6 C88,4 98,22 97,50 C96,82 76,96 49,95 C16,94 3,76 4,48 C5,18 20,7 50,6 Z"/></svg></span>'
    );
  }

  // 4 · preuve : masonry (8 photos) ou bento (4 features big/tall/tall/big)
  const head = $$('.coll-head > *');
  if (head.length === 4) {
    head[0].textContent = C.proof.kicker;
    head[1].textContent = C.proof.title;
    head[2].textContent = C.proof.sub;
    head[3].textContent = C.proof.meta;
  }
  const grid = $('#collGrid');
  if (grid && C.proof.layout === 'bento') {
    grid.className = 'bento-grid';
    grid.innerHTML = C.proof.features.map((f) =>
      `<figure class="card${f.size ? ' b-' + f.size : ''}"><div class="card-img"><img src="${f.illu}" alt="${f.title}"></div><figcaption>${f.title}<span class="mono">${f.meta}</span></figcaption></figure>`
    ).join('');
  } else if (grid) {
    grid.className = 'coll-grid';
    const SPEEDS = [-0.05, 0.06, -0.028, 0.085];
    grid.innerHTML = SPEEDS.map((s, ci) =>
      `<div class="col" data-pspeed="${s}">` +
      C.proof.projects.slice(ci * 2, ci * 2 + 2).map((p) =>
        `<figure class="card"><div class="card-img"><img src="${p.img}" alt="${p.title} — ${p.meta}"></div><figcaption>${p.title}<span class="mono">${p.meta}</span></figcaption></figure>`
      ).join('') + '</div>'
    ).join('');
  }

  // 5 · devise (train de mots-clés)
  set('#mottoKicker', C.motto.kicker);
  const mtrack = $('#mottoTrack');
  if (mtrack) mtrack.innerHTML = C.motto.words.map((w) => `<span class="mw">${w.word}</span>`).join('');

  // 6-7 · processus immersif (visuels posés un à un)
  set('#nw1', C.universes.introA);
  set('#nw2', C.universes.introB);
  set('#nw3', C.universes.introC);
  const g2 = $('#grow2 img');
  if (g2) g2.src = C.universes.image || (C.universes.items[0] || {}).img || g2.src;
  const psteps = $('#psteps');
  if (psteps) {
    psteps.innerHTML = C.universes.items.map((u) =>
      `<div class="pstep"><span class="pstep-meta mono ash">${u.meta}</span><h3>${u.name}</h3><p>${u.desc || ''}</p></div>`
    ).join('');
  }
  const sCta = $('#stepsCtaLink');
  if (sCta) sCta.childNodes[0].textContent = C.universes.cta;

  // 8 · preuve sociale — le chiffre qui frappe
  set('#figKicker', C.testimonial.kicker || '');
  const figM = String(C.testimonial.figure || '').trim().match(/^([^\d.,+-]*[+\u2212-]?)\s*(-?[\d.,]+)/);
  set('#figPre', figM ? figM[1] : '');
  set('#figVal', figM ? figM[2] : '');
  set('#figUnit', C.testimonial.unit || '');
  set('#quoteText', C.testimonial.quote);
  set('#quoteAuthor', C.testimonial.author);

  // 9 · objections
  C.objections.items.forEach((t, i) => set('#fs' + (i + 1), t));
  const fs4 = $('#fs4');
  if (fs4) {
    fs4.innerHTML = `${C.objections.finale} <span class="pill" id="pillPhrase">${C.objections.pill}<svg class="pill-svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path id="pillPath" d="M50,6 C88,4 98,22 97,50 C96,82 76,96 49,95 C16,94 3,76 4,48 C5,18 20,7 50,6 Z"/></svg></span>`;
  }
  $$('#trail img').forEach((img, i) => { img.src = C.trail[i % C.trail.length]; });

  // 10 · conversion
  set('.footer-kicker', C.contact.kicker);
  const mail = $('.footer-mail');
  if (mail) { mail.href = 'mailto:' + C.contact.email; mail.querySelector('.footer-mail-text').textContent = C.contact.email; }
  set('.footer-reassurance', C.contact.reassurance);
  const fname = $('#footerName');
  if (fname) { fname.textContent = C.brand.name; fname.setAttribute('aria-label', C.brand.name); }
  const bottom = $$('.footer-bottom > p');
  if (bottom.length === 3) {
    bottom[0].textContent = C.brand.copyright;
    bottom[1].innerHTML = C.brand.socials.map((s) => `<a href="${s.url}" target="_blank" rel="noopener">${s.label}</a>`).join('&nbsp;&nbsp;&nbsp;');
    bottom[2].textContent = C.brand.signature;
  }
})();
