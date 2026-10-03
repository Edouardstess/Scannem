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
    name: 'Nom de la marque',                       // wordmark (header, loader, footer géant)
    title: 'Marque — Métier, Ville',                // <title> SEO
    description: 'Description SEO en une phrase.',  // meta description
    kicker: 'MARQUE — MÉTIER, VILLE',               // ligne mono au-dessus du titre hero
    copyright: '© 2026 — VILLE, PAYS',
    signature: 'FAITE AVEC …',                      // clin d'œil bas de footer
    socials: [
      { label: 'INSTAGRAM ↗', url: 'https://www.instagram.com/…' },
      { label: 'LINKEDIN ↗', url: 'https://www.linkedin.com/…' }
    ]
  },

  /* 2 ancres + CTA du header — un mot chacun, CAPS */
  nav: { proof: 'TRAVAUX', universes: 'PROCESSUS', cta: 'RÉSERVER' },

  /* 1 · ACCROCHE — « line1 / line2a [image qui naît et devient
     plein écran] line2b ». Total line2a+line2b : court (nowrap). */
  hook: {
    line1: 'Une promesse forte,',
    line2a: 'pour de vrais',
    line2b: 'clients.',
    image: 'https://picsum.photos/id/1/1800/1200',  // 3:2 — le moment émotionnel
    imageAlt: 'Description accessible de l’image',
    floaters: [                                     // 10 visuels du pasteboard (mix portrait/paysage)
      'https://picsum.photos/id/0/360/480',
      'https://picsum.photos/id/2/280/360',
      'https://picsum.photos/id/3/340/240',
      'https://picsum.photos/id/5/300/400',
      'https://picsum.photos/id/6/280/220',
      'https://picsum.photos/id/20/360/260',
      'https://picsum.photos/id/42/320/420',
      'https://picsum.photos/id/8/280/360',
      'https://picsum.photos/id/60/300/400',
      'https://picsum.photos/id/201/380/270'
    ]
  },

  /* 2 · POSITIONNEMENT — ≤ 42 caractères (affiché nowrap, en blanc
     sur l'image plein écran) */
  positioning: 'Ce que je fais — pour qui, où.',

  /* 3 · DÉMARCHE — [[…]] = ce qu'entoure l'ovale dessiné :
     2 à 3 MOTS MAXIMUM, JAMAIS une phrase entière (l'ovale est un tracé
     à la main : au-delà de 3 mots il s'étire et cesse d'être lisible). */
  manifesto: {
    text: 'Deux ou trois phrases qui disent le pourquoi. Le mot qui compte est [[entouré à la main]] — c’est lui qu’on retient.'
  },

  /* 4 · PREUVE — layout: 'masonry' (8 photos de réalisations)
     ou 'bento' (4 features illustrées : big, tall, tall, big) */
  proof: {
    layout: 'masonry',
    kicker: 'TRAVAUX CHOISIS',
    title: 'Titre de la section',
    sub: 'Sous-titre d’une ligne qui contextualise.',
    meta: 'HUIT PROJETS — 2019 → 2026',
    /* — layout 'masonry' : exactement 8 items — */
    projects: [
      { img: 'https://picsum.photos/id/64/640/800', title: 'Projet 1', meta: 'TYPE — 2025' },
      { img: 'https://picsum.photos/id/1035/640/780', title: 'Projet 2', meta: 'TYPE — 2022' },
      { img: 'https://picsum.photos/id/21/640/460', title: 'Projet 3', meta: 'TYPE — 2025' },
      { img: 'https://picsum.photos/id/575/640/820', title: 'Projet 4', meta: 'TYPE — 2023' },
      { img: 'https://picsum.photos/id/1011/640/800', title: 'Projet 5', meta: 'TYPE — 2023' },
      { img: 'https://picsum.photos/id/902/640/500', title: 'Projet 6', meta: 'TYPE — 2023' },
      { img: 'https://picsum.photos/id/838/640/760', title: 'Projet 7', meta: 'TYPE — 2024' },
      { img: 'https://picsum.photos/id/653/640/540', title: 'Projet 8', meta: 'TYPE — 2023' }
    ],
    /* — layout 'bento' : exactement 4 items aux tailles CONTRASTÉES,
       dans cet ordre : big, tall, tall, big (grande / haute / haute /
       grande, en diagonale). 4 cases égales ne sont PAS un bento.
       Illustrations : big 900×600 · tall 600×820 — */
    features: [
      { size: 'big',  illu: 'illustrations/fe-1.svg', title: 'Pilier 1', meta: 'SPEC — DÉTAIL — DÉTAIL' },
      { size: 'tall', illu: 'illustrations/fe-2.svg', title: 'Pilier 2', meta: 'SPEC — DÉTAIL' },
      { size: 'tall', illu: 'illustrations/fe-3.svg', title: 'Pilier 3', meta: 'SPEC — DÉTAIL' },
      { size: 'big',  illu: 'illustrations/fe-4.svg', title: 'Pilier 4', meta: 'SPEC — DÉTAIL' }
    ]
  },

  /* 5 · DEVISE — 3 mots (train horizontal scrubé), hint d'une ligne */
  motto: {
    kicker: 'CE QUI GUIDE CHAQUE PROJET',
    words: [
      { word: 'Valeur1', hint: 'Une ligne qui incarne ce mot.' },
      { word: 'Valeur2', hint: 'Une ligne qui incarne ce mot.' },
      { word: 'Valeur3', hint: 'Une ligne qui incarne ce mot.' }
    ]
  },

  /* 6-7 · PROCESSUS — « introA introB [visuel] introC » puis les étapes.
     Scène 100 % TYPOGRAPHIQUE : compteur géant, aucune image.
     SEULE `image` est utilisée — c'est le visuel du zoom d'intro,
     1800×1200 (3:2), celui que le rideau de lames vient couper. */
  universes: {
    introA: 'Un',
    introB: 'projet,',
    introC: '3 étapes.',
    cta: 'Réserver →',                              // CTA final (ovale dessiné)
    image: 'https://picsum.photos/id/42/1800/1200',  // le zoom d'intro (le SEUL visuel de la scène)
    items: [
      { name: 'Étape un', meta: 'ÉTAPE — 01', desc: 'Une à deux phrases.' },
      { name: 'Étape deux', meta: 'ÉTAPE — 02', desc: 'Une à deux phrases.' },
      { name: 'Étape trois', meta: 'ÉTAPE — 03', desc: 'Une à deux phrases.' }
    ]
  },

  /* 8 · PREUVE SOCIALE — un avis client, court et crédible.
     quote  : UNE ou DEUX phrases, COMPLÈTES et AUTONOMES — elles doivent
              se lire seules, sans dépendre du chiffre. Sans guillemets :
              la scène les dessine. Ce que dirait vraiment un client, pas
              un slogan.
     figure : le résultat marquant, préfixe optionnel (+, −, ×) ; il monte
              de 0 au scroll. À INVENTER si l'utilisateur n'en donne pas —
              plausible pour le secteur — puis à faire valider.
     unit   : 4 caractères max (m², %, j, k€…).
     kicker : le contexte du projet, en CAPS.
     Sans `figure`, la citation reprend toute la place (repli automatique). */
  testimonial: {
    kicker: 'TYPE DE PROJET, VILLE',
    figure: '+10',
    unit: 'm²',
    quote: 'Une phrase que le client dirait vraiment, qui tient debout toute seule.',
    author: 'PRÉNOM NOM — FONCTION'
  },

  /* 9 · OBJECTIONS — 3 freins + la chute (pill = mot entouré) */
  objections: {
    items: ['Pas de …', 'Pas de …', 'Pas de …'],
    finale: 'Juste …,',
    pill: 'mot.'
  },

  /* 10 · CONVERSION */
  contact: {
    kicker: 'UN PROJET EN TÊTE ?',
    email: 'bonjour@exemple.fr',
    reassurance: 'RÉPONSE SOUS 24 H — DEVIS GRATUIT, SANS ENGAGEMENT'
  },

  /* traînée sous la souris (finale) — 20 visuels, petits formats mixtes */
  trail: [
    'https://picsum.photos/id/0/220/280', 'https://picsum.photos/id/1/240/300',
    'https://picsum.photos/id/2/200/260', 'https://picsum.photos/id/3/230/280',
    'https://picsum.photos/id/5/280/340', 'https://picsum.photos/id/6/220/270',
    'https://picsum.photos/id/8/210/260', 'https://picsum.photos/id/9/230/290',
    'https://picsum.photos/id/20/230/300', 'https://picsum.photos/id/26/240/200',
    'https://picsum.photos/id/42/230/180', 'https://picsum.photos/id/48/240/300',
    'https://picsum.photos/id/60/280/340', 'https://picsum.photos/id/101/230/280',
    'https://picsum.photos/id/119/220/270', 'https://picsum.photos/id/160/240/290',
    'https://picsum.photos/id/180/240/180', 'https://picsum.photos/id/201/240/310',
    'https://picsum.photos/id/370/230/290', 'https://picsum.photos/id/445/240/290'
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
