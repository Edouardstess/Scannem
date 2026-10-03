---
name: site-immersif-skill
description: Génère un site vitrine one-page ultra animé en répliquant EXACTEMENT le template « Site Immersif » (scroll scrubé, scènes épinglées, zoom-texte, rideau de lames, bande lime, traînée souris) à partir de 7 questions courtes posées en formulaire à cocher — secteur, nom, thème, accent, activité, e-mail, photos. Utiliser dès que l'utilisateur veut créer un site depuis ce template, un nouveau site vitrine animé, ou décliner Site Immersif pour une activité.
---

# Skill « Site Immersif » — réplique exacte, contenu adaptable

Ce skill produit un site **identique au pixel et à l'animation près** aux
démonstrateurs du template Site Immersif. La garantie de fidélité vient d'une
règle absolue : **les fichiers du moteur sont copiés depuis `assets/`,
jamais réécrits, jamais « réinterprétés »**. Seul `content.js` (textes +
visuels), un attribut de thème et deux variables d'accent changent.

Dossier du skill : `assets/` contient les fichiers canoniques :
`index.html`, `app.js`, `styles.css`, `content.example.js`, `serve.mjs`.

---

## Étape 0 — Les 7 questions, TOUTES en formulaire à cocher

**Aucune question ne se pose en texte libre dans le chat.** Tout passe par
`AskUserQuestion`, en cases à cocher. L'outil plafonne à **4 questions par
appel** et exige **2 à 4 options par question** : il faut donc **DEUX appels
successifs**, 4 puis 3. Attends les réponses du premier avant le second, et
ne commence rien avant d'avoir les sept.

Pour les champs par nature libres (nom, description, e-mail), on ne peut pas
deviner la réponse : proposer 2-4 options qui servent d'amorce, et compter
sur « Autre » — que l'outil ajoute toujours — pour la saisie réelle. Rédiger
ces options comme de vraies pistes plausibles, jamais comme des bouche-trous
(« Option A / Option B ») : bien écrites, l'utilisateur en coche souvent une.

**Appel 1 — les 4 structurantes**

1. **Secteur d'activité ?** — 4 familles couvrant large (métier visuel :
   photographe, architecte · produit/logiciel : startup, SaaS · commerce ou
   lieu : restaurant, hôtel · services & conseil), + Autre.
   C'est cette réponse qui tranche `proof.layout` : métier visuel →
   `masonry`, produit/service abstrait → `bento`.
2. **Nom de la marque ?** — sert de wordmark géant, de slug de dossier et de
   signature de footer. Si un e-mail ou un domaine a déjà été donné, en
   proposer le nom en première option ; sinon 2-3 pistes tirées du secteur.
3. **Thème : sombre ou clair ?** — 2 options, recommander « sombre » (c'est
   le rendu d'origine du template).
4. **Couleur d'accent ?** — Vert `#e3f794` (défaut du template, rien à
   changer) · Bleu/cyan `#a9e7f5` · Orange clair `#f8cf9f` · Personnalisée
   (le hex arrive par « Autre »).

**Appel 2 — les 3 restantes**

5. **Votre activité en quelques phrases** — ce que vous faites, pour qui, ce
   qui vous distingue, votre ville. C'est LE champ qui nourrit toute
   l'écriture : proposer 3 angles de positionnement plausibles pour le
   secteur coché en 1, la vraie réponse arrivant le plus souvent par
   « Autre ». Si l'utilisateur ne répond rien de substantiel, **ne pas
   bloquer** : déduire, écrire, et lister à la livraison tout ce qui a été
   inventé.
6. **Adresse e-mail de contact ?** — proposer `contact@<domaine>` et
   `bonjour@<domaine>` construits sur le nom donné en 2, + Autre.
7. **Avez-vous des photos à utiliser ?** — Aucune (je fournis les 40) ·
   Quelques-unes (je complète) · Oui, j'ai tout. Détailler dans la
   description de chaque option ce que ça implique concrètement.
   Les trois options se complètent TOUJOURS depuis les banques d'images
   libres (Unsplash, Pexels) — voir 4.2. Ne jamais proposer, ni dans
   l'intitulé ni dans la description, de fabriquer les visuels par
   génération d'images IA : ce n'est pas une source de ce skill, même si
   un outil de génération est connecté à la session.

**Ne jamais bloquer** : tout ce qui n'est pas demandé ici se DÉDUIT des
réponses — accroche, positionnement, devise, 3 étapes, objections, titres
de sections, avis client, réseaux sociaux, disponibilité. Ce sont des choix
d'écriture, pas des questions à poser en plus. Proposer, puis faire valider
à la livraison ce qui engage l'utilisateur (avis client, comptes sociaux,
disponibilité affichée). Adapter les 3 étapes au secteur (restaurant :
choisir / cuisiner / servir ; SaaS : essai / intégration / support ;
artisan : relevé / devis / pose).

Si la réponse 7 est « oui » ou « quelques-unes », créer `<slug>/images/` dès
l'étape 1, en donner le chemin absolu, rappeler les formats du tableau de
l'étape 4, et attendre le dépôt. S'il ne dépose rien ou seulement une
partie, compléter par la cascade de l'étape 4 — c'est prévu pour.

| Réponse | Champs de `content.js` |
|---|---|
| 1 · secteur | `brand.kicker`, `nav`, registre d'écriture, choix `proof.layout` |
| 2 · nom | `brand.name`, slug du dossier, `#footerName`, `brand.title` |
| 3 · thème | `<html data-theme="dark">` ou rien |
| 4 · accent | `--lime` / `--lime-ink` dans `:root` |
| 5 · activité | `brand.title/description`, `hook`, `positioning`, `manifesto`, `proof`, `motto`, `universes`, `testimonial`, `objections`, `brand.copyright` |
| 6 · e-mail | `contact.email` |
| 7 · photos | source des visuels (étape 4) |

À déduire et faire valider : `brand.socials`, `brand.signature`,
`contact.kicker`, `contact.reassurance`, et TOUT le bloc `testimonial`.

## Étape 1 — Copier les fichiers canoniques (VERBATIM)

Le dossier du skill est celui qui contient ce SKILL.md — note son chemin
(`SKILL_DIR`), quel que soit l'endroit où le skill est installé
(`.claude/skills/` du projet ou `~/.claude/skills/`) :

```bash
mkdir -p <slug>/ && cp "SKILL_DIR/assets/index.html" \
  "SKILL_DIR/assets/app.js" \
  "SKILL_DIR/assets/styles.css" <slug>/
cp "SKILL_DIR/assets/content.example.js" <slug>/content.js
mkdir -p <slug>/images/
```

`<slug>/images/` est le dossier de dépôt du client ET la destination de
TOUTES les images du site : rien n'est hotlinké, tout est téléchargé en
local et référencé en chemin relatif (`images/hero.jpg`) dans `content.js`.

**INTERDIT** : modifier `app.js` ou `index.html`. **`styles.css`** : DEUX
valeurs seulement peuvent changer, rien d'autre :
- l'accent, dans `:root` (étape 2) ;
- l'URL de `body.static .spot::before`, qui pointe sur un placeholder
  picsum dans le fichier canonique. C'est le visuel plein écran du hero en
  mode « reduced-motion » ; le remplacer par `url('images/hero.jpg')`.
  Sans ça une photo inconnue s'affiche en plein écran chez les visiteurs
  qui ont désactivé les animations — et le contrôle de livraison ne la voit
  pas, puisqu'il ne cherche les URLs distantes que dans `content.js`.

Toute autre envie de modification = erreur de méthode : le template couvre
déjà le besoin via `content.js`.

## Étape 2 — Thème et accent (3 lignes, zéro find/replace)

- **Dark** : dans `<slug>/index.html`, remplacer `<html lang="fr">` par
  `<html lang="fr" data-theme="dark">`. **Light** : ne rien faire.
  (Tout le reste — surfaces, hairlines, filigranes — suit automatiquement :
  la feuille de style est entièrement pilotée par tokens.)
- **Accent** : dans `:root` de `styles.css`, changer :
  - `--lime` → la couleur choisie ;
  - `--lime-ink` → `#1a1915` si l'accent est clair (les 3 presets le
    sont) ; `#ffffff` si l'utilisateur impose un accent foncé.
- Si des illustrations SVG sont produites (étape 4), y utiliser le même
  hex d'accent.

## Étape 3 — Réécrire `content.js` (la SEULE écriture créative)

Ouvre `<slug>/content.js` : la moitié haute (`window.SITE_CONTENT`) est
l'unique zone à réécrire — chaque champ y est commenté avec son rôle et
ses contraintes. La moitié basse (« INJECTION ») ne se modifie JAMAIS.

Règles de rédaction (issues du site d'origine — les respecter garantit le
rendu) :
- `hook.line1` + `line2a/line2b` : promesse en 2 lignes courtes ; la ligne 2
  reste sur une ligne (l'image naît entre `line2a` et `line2b`).
- `positioning` : **≤ 42 caractères** (affiché nowrap plein écran).
- `manifesto.text` : 2-3 phrases. Entre `[[…]]` : **2 à 3 mots MAXIMUM,
  jamais une phrase entière** — l'ovale est un tracé à la main ; au-delà de
  3 mots il s'étire sur deux lignes et cesse d'être lisible. Même règle
  pour `objections.pill`.
- `proof.layout` : `'masonry'` (métier visuel → 8 photos de réalisations)
  ou `'bento'` (produit/service abstrait → **exactement 4 features**,
  `size` dans cet ordre : `big`, `tall`, `tall`, `big` — deux grandes et
  deux hautes, en diagonale). Les tailles doivent CONTRASTER : quatre
  cases égales ne sont pas un bento, c'est un tableau. Toutes les tuiles
  couvrent 2 rangées, donc aucune n'est écrasée en bandeau. Quatre
  piliers valent mieux que six demi-piliers : si vous en avez six,
  fusionnez-les par deux.
- `motto.words` : 3 mots, un `hint` d'une ligne chacun.
- `universes` : intro en 3 fragments (« Un / projet, / 3 étapes. » style),
  puis 3 étapes avec `desc` 1-2 phrases — AUCUNE image par étape, le
  numéro géant tient le décor. `universes.image` = le visuel du zoom d'intro.
- `objections` : 3 × « Pas de … » + `finale` + `pill` (1-2 mots).
- `testimonial` : `figure` est LA vedette (résultat chiffré, préfixe
  optionnel `+`/`−`/`×`), `unit` 4 caractères max, `kicker` le contexte en
  CAPS. La `quote` explique le chiffre en une phrase, SANS guillemets (la
  scène les dessine). Sans `figure`, la citation reprend toute la place.
- Tous les CAPS mono (`kicker`, `meta`, `reassurance`,
  `author`) restent en MAJUSCULES.
- Aucune chaîne du fichier d'exemple ne doit survivre.

## Étape 4 — Les visuels

Formats par emplacement (NE PAS improviser d'autres ratios — les scènes
calculent leurs transformations dessus) :

| Slot | Qté | Format |
|---|---|---|
| `hook.image` | 1 | 1800×1200 (3:2) — photo de préférence, même pour un SaaS |
| `hook.floaters` | 10 | mix 2:3 / 3:2, 220-380 px de large |
| `proof.projects` (masonry) | 8 | 640 de large, hauteurs variées 460-820 |
| `proof.features` (bento) | 4 | `big` 900×600 · `tall` 600×820 |
| `universes.image` | 1 | 1800×1200 (3:2) — le zoom d'intro du processus |
| `trail` | 20 | petits mix 200-280 px |

Soit **40 visuels** en masonry, 36 en bento (dont 4 illustrations SVG).
La scène du processus n'a QU'UNE image, celle du zoom d'intro : les
étapes elles-mêmes sont 100 % typographiques. Toutes les sources se
combinent : on part des photos du client et on complète le manque.

### 4.1 — Les photos du client (toujours en premier)

Si l'utilisateur a annoncé des photos (question 7), inventorier
`<slug>/images/` : `ls` + dimensions réelles de chaque fichier. Puis les
affecter aux slots en respectant les ratios du tableau — une photo large
va en `wide`/`hook.image`, une verticale en `tall`/floater. Optimiser :
max 1600 px sur le grand côté, JPEG qualité ~78.

S'il en manque, dire précisément combien et pour quels slots, puis
compléter par 4.2 **sans redemander l'autorisation** : c'est la suite
normale du pipeline.

### 4.2 — Banques d'images libres (Unsplash / Pexels) — LA source

**Aucune image de ce skill n'est générée par IA.** Même si un MCP de
génération d'images est connecté et crédité, on ne s'en sert pas : la
photo de banque est du vrai réel photographié, c'est ce qui fait tenir un
site de ce niveau. Tout ce qui manque après les photos du client vient
d'Unsplash ou de Pexels, point.

Banques réelles et gratuites, bien meilleures que picsum pour du premium.
Méthode (validée) :

1. Ouvrir une page de recherche dans le navigateur intégré :
   `https://unsplash.com/s/photos/<requête-en-anglais>` ou
   `https://www.pexels.com/search/<requête-en-anglais>/`.
2. Extraire les URLs des `<img>` rendus — les pages sont en JS, `curl` ne
   suffit PAS :
   ```js
   [...document.querySelectorAll('img')].map(i => i.src)
     .filter(s => /images\.(unsplash|pexels)\.com/.test(s))
     .map(s => s.split('?')[0])
   ```
3. Redimensionner par paramètre d'URL :
   - Unsplash : `<url>?w=1600&q=80`
   - Pexels : `<url>?auto=compress&cs=tinysrgb&w=1600`
4. Vérifier chaque URL (HTTP 200 + `content-type: image/*`) puis la
   **télécharger** dans `<slug>/images/`. Aucune URL distante ne doit
   subsister dans `content.js` livré.
5. Curater : planche-contact avant d'affecter aux slots, pour garder une
   direction artistique homogène (même lumière, même registre). Une
   planche-contact se regarde : construire une page HTML en grille, la
   servir en HTTP (un `file://` ne s'ouvre qu'en capture figée) et la
   screenshoter. Les ratios réels se lisent au passage
   (`naturalWidth/naturalHeight` sur des vignettes chargées sans `h=`),
   ce qui évite de recadrer un paysage en portrait.
6. Recadrer AU FORMAT EXACT du slot par l'URL plutôt que de laisser le
   navigateur déformer : Unsplash accepte
   `?w=<L>&h=<H>&fit=crop&crop=entropy&q=78&fm=jpg` (`crop=faces` pour
   les portraits). Les hauteurs du tableau de l'étape 4 pilotent la
   hauteur des cartes du masonry : `.card-img img` est en `width:100%;
   height:auto`, donc c'est le ratio du fichier qui fait la mise en page.

**`source.unsplash.com` est arrêté (503) — ne jamais l'utiliser.**

### 4.3 — picsum, filet de sécurité uniquement

`https://picsum.photos/id/N/W/H` si tout le reste échoue. Vérifier chaque
id (le service répond en 302). Catalogue daté : acceptable en lifestyle, à
éviter pour du tech/premium — et à télécharger en local comme les autres.

### Illustrations bento

Layout bento uniquement : 4 SVG rangés dans `<slug>/illustrations/` —
deux au format `big` 900×600, deux au format `tall` 600×820, dans l'ordre
big / tall / tall / big. Garder 40 px de marge de sécurité sur les quatre
bords : les tuiles sont en `object-fit: cover` et rognent un peu. Le site est typographique et très aéré : les
illustrations doivent l'être aussi. **UNE seule idée par tuile, AUCUN
texte** (le titre et le meta sont déjà sous la tuile), **un seul élément
d'accent**, des filets à ~13 % d'encre, et beaucoup de vide. Fond = la
couleur `--white` du thème, pour fusionner avec la tuile.

Contre-exemple à ne pas reproduire : un faux tableau de bord (panneaux
empilés, séries de barres, micro-labels mono, jauges, chips). C'est trop
dense, ça se lit comme une capture d'écran et ça casse le registre du
site. Viser le pictogramme abstrait, pas la maquette d'interface :
un noyau relié à trois nœuds, un passage de texte dont une ligne est
citée, une matrice de cas dont quelques-uns sont marqués, une ligne de
temps avec un point. Jamais de dessin naïf type clipart non plus.

Et voir GRAND : le motif doit occuper la tuile (un cercle de 380 px de
diamètre, une trame qui va d'un bord à l'autre, un champ de cases qui
remplit le cadre). Un petit schéma perdu au centre d'une grande tuile
fait pauvre — c'est l'échelle qui rend le minimalisme élégant.

## Étape 5 — Servir

Copier `SKILL_DIR/assets/serve.mjs` vers `.claude/serve-<slug>.mjs` (dans le
projet), y remplacer le chemin `ROOT` (chemin absolu du dossier du site) et
le port (choisir un port libre 4385+), ajouter l'entrée
correspondante dans `.claude/launch.json` (modèle : les entrées
existantes), puis ouvrir la preview.

## Étape 6 — Vérification obligatoire (aucune livraison sans ça)

Le moteur expose `window.__ea.scrub(y)` (fige la page à la position `y`,
fiable pour les captures) et `window.__ea.resume()`. Récupérer les offsets
des sections (`docTop`), puis vérifier scène par scène, console ouverte
(zéro erreur exigée) :

- [ ] Loader : le nom + un trait qui se remplit (700 ms), puis le nom
      REJOINT sa place dans le dock pendant que le voile s'efface. Trois
      pièges déjà résolus, ne pas les réintroduire : (a) le dock ne descend
      PAS pendant le vol (classe `handoff` : il est déjà posé, simplement
      invisible), sinon le vol vise une cible mobile et saute à l'arrivée ;
      (b) l'extinction du nom et l'apparition du dock démarrent dans la MÊME
      frame, sinon il y a un instant sans nom à l'écran ; (c) elles se font
      en FONDU CROISÉ de même durée (.32 s), pas en bascule sèche.
- [ ] Hero : pasteboard 10 visuels révélés en cascade, kicker + 2 lignes,
      header flottant haut (wordmark, 2 ancres, CTA accent). Rien d'autre
      en surimpression : ni label de disponibilité, ni indicateur « scroll ».
- [ ] Traversée du nuage : les visuels NAISSENT petits et resserrés vers
      le centre, s'ouvrent vers leur place en grandissant, puis dérivent
      vers les bords sur toute la première moitié de la scène. Il doit en
      rester à l'écran quand l'image naît entre les mots — c'est le repère
      de fidélité. Une partie n'apparaît qu'en cours de route.
- [ ] Titre : la ligne 1 s'efface, PUIS la ligne 2 monte occuper le centre,
      seule, AVANT que le zoom ne démarre (paramètre `lift` de `makeGrow`,
      découplé de la croissance de l'image).
- [ ] Zoom : l'image naît entre les mots, les repousse, devient plein
      écran ; `positioning` apparaît mot à mot puis s'efface.
- [ ] Manifeste : encrage caractère par caractère ; l'ovale n'apparaît
      QUE quand le remplissage l'atteint, et il n'entoure que 2-3 mots ;
      le texte grisé est recouvert
      SUR PLACE par le panneau.
- [ ] Preuve : cartes/tuiles arrivent désordonnées puis se posent ;
      masonry = parallaxe par colonne ; bento = 4 tuiles de tailles
      CONTRASTÉES (grande / haute / haute / grande), toutes hautes,
      sans letterbox ni illustration écrasée en bandeau.
- [ ] Devise : train de mots, lettres qui se posent au centre, hints qui
      permutent, texte lisible sur l'accent.
- [ ] Processus : intro-zoom jusqu'au plein écran, puis le rideau de
      lames couvre de gauche à droite et se retire de droite à gauche.
      Les lames n'ont AUCUNE arête dessinée : un liseré ::before/
      ::after y trace des traits parasites en travers de l'écran
      pendant le relais entre deux étapes.
      Le zoom ne s'inverse JAMAIS (aucun dézoom). Derrière : AUCUNE image
      — le numéro d'étape en filigrane géant qui défile comme un odomètre,
      le texte de l'étape à gauche. Vérifier qu'à aucun moment deux
      étapes ne sont lisibles ensemble, ni aucune (relais asymétrique).
      Puis le CTA à l'ovale dessiné. Ni label ni jauge de progression.
- [ ] Témoignage : la citation doit se lire SEULE, sans le chiffre. Une
      ou deux phrases, ce qu'un client dirait vraiment — pas un slogan, pas
      une amorce qui dépend du chiffre (« Gagnés sans pousser un mur… » est
      un contre-exemple). Le chiffre est INVENTÉ si l'utilisateur n'en
      donne pas, plausible pour le secteur, puis validé à la livraison.
- [ ] Témoignage, mise en scène : guillemet, parole, filet, signature —
      dans cet ordre.
      Le guillemet monte, la citation apparaît d'un bloc (en grand, aligné
      à gauche), le filet se trace de gauche à droite, puis le nom, le
      contexte et enfin le chiffre qui monte de 0. Le chiffre est une
      pièce à conviction SOUS la signature, jamais le sujet : si l'œil va
      au chiffre avant la parole, c'est raté.
- [ ] Objections : 3 phrases qui s'encrent, pilule dessinée, traînée
      d'images sous la souris (tester avec des `MouseEvent('mousemove')`
      synthétiques).
- [ ] Footer : kicker, mail, réassurance, nom géant en rideau de lettres,
      liens sociaux externes réels.
- [ ] **Les scènes tournent aussi sur mobile.** `app.js` ne bascule en
      mode statique QUE sur `prefers-reduced-motion` (`const staticMode =
      reduced`). Ni le tactile ni la largeur ne le déclenchent : téléphone
      et tablette ont les mêmes scènes scrubées que le desktop. Vérifier
      `document.body.classList.contains('static') === false` à 375 px.
      Le mode reste décidé AU CHARGEMENT — redimensionner ne rebascule pas,
      et rafraîchir en mode inspecteur recharge donc dans le mode de la
      largeur courante : ce n'est pas un bug.
- [ ] **Tester dans un vrai iframe, pas dans l'émulateur du panneau.**
      L'émulation rapporte une largeur mise à l'échelle (453 px pour 375
      demandés) et fausse toutes les mesures. Servir une page qui contient
      `<iframe src="/" width="375" height="812">` depuis LE MÊME port que le
      site (sinon le cross-origin interdit de lire le document), et mesurer
      dedans. Passer 320 · 375 · 414 · 638 px.
- [ ] Mobile : aucun défilement horizontal
      (`document.documentElement.scrollWidth <= innerWidth`).
  - Navigation **pleine largeur** ancrée en haut, coins droits, qui glisse
    depuis le haut — pas la pilule flottante du desktop. Wordmark à gauche,
    CTA à droite, sur UNE ligne ; sous 480 px les ancres disparaissent.
  - Le header est **flottant**, pas collé : 10 px du haut et des côtés,
    coins arrondis — jamais bord à bord.
  - La grille de la sélection reste à **2 colonnes** même sous 480 px :
    8 projets en une colonne font cinq écrans de haut.
  - **Le nuage du hero garde ses positions `--x`/`--y`.** En mode animé le
    hero fait exactement 100 vh : les pourcentages posés en inline dans
    `index.html` retombent juste et les visuels s'éparpillent tout autour du
    titre, comme sur desktop. NE PAS les réimplanter en deux bandes — c'est
    l'erreur qui produit l'effet « deux bandeaux ». Seules corrections :
    · la TAILLE, `calc(var(--w) * 0.52)` — dérivée de `--w`, qui change
      d'une position à l'autre. Cette variété de largeurs est ce qui
      empêche le nuage de se lire comme une grille : ne jamais la
      remplacer par une largeur unique, même bien calibrée.
    · quatre décalages VERTICAUX seulement (`top`), pour les visuels que
      le titre vient percuter : au début de la scène le moteur resserre le
      nuage vers le centre (pull de 16 %) et le réduit à 0,74, ce qui sur
      écran étroit pose 1, 6 et 7 en plein sur le titre. On les sort de sa
      bande sans toucher leur flanc, pour garder l'éparpillement
      gauche/droite. Sous 480 px, 1 et 7 demandent un cran de plus.
    Contrôle : titre dégagé (mesuré au `Range` sur les nœuds texte, PAS sur
    la boîte du `<h1>` qui fait toute la largeur), et répartition **5 au-
    dessus / 5 en dessous** — une répartition 6/4 se voit.
  - **Trois mises en page desktop doivent être reprises**, et les règles
    `body.static` ne le font plus puisque le mode statique ne s'applique
    plus :
    · Processus — le texte de l'étape (`.psteps`, 42 vw) et le compteur
      géant (`.bignum`) sont CÔTE À CÔTE sur desktop. À 375 px ça donne deux
      colonnes de 150 px : les EMPILER, chiffre au-dessus, texte dessous,
      chacun sur toute la largeur.
    · CTA du processus — il est posé en `left: 50 %`, sa largeur utile n'est
      donc que la moitié droite de l'écran et le libellé passe à la ligne.
      Lui donner `width: max-content` + `white-space: nowrap`. NE PAS
      toucher à `left` ni à `transform` : `app.js` repose un
      `translate(-50%, …)` en inline qui écrase tout `transform` CSS.
    · Témoignage — `.q-sign-row` est une rangée flex de trois colonnes
      (nom / contexte / chiffre). À cette largeur le contexte se réduit à
      une colonne de mots coupés : passer en `flex-direction: column`, en
      gardant l'ordre parole → signature → chiffre.
  - Le kicker du hero revient à la ligne ; `positioning` n'est plus en
    `nowrap` ; la signature du témoignage passe à la ligne.

Pièges connus (déjà résolus dans le moteur — ne pas « corriger ») :
les longueurs des tracés SVG se recalculent au chargement des fontes
(`refreshDraws`) ; les hauteurs de scènes viennent de `data-pin` ; le
`--w` inline des `.fl` doit rester (seuls les `src` changent) ; les
fenêtres `cover` et `clear` du rideau de lames ne doivent jamais se
chevaucher pour une même lame (sinon l'origine de transformation bascule
en cours de course et la lame saute) ; le compteur d'étapes se translate
en `em`, donc la hauteur d'un `<i>` du rouleau doit rester `1em`.

## Étape 7 — Livraison

Avant de livrer, vérifier qu'AUCUNE URL distante ne subsiste dans
`content.js` (`grep -n "https\?://" content.js` ne doit ressortir que les
liens de `brand.socials`) : toutes les images sont dans `images/`.

Récapituler : URL locale, dossier créé, choix appliqués (thème, accent,
layout de preuve), et pour les visuels le détail de la provenance —
combien de photos du client, combien de générées, combien de placeholders
et de quelle banque. Rappeler que toute évolution de contenu se fait dans
`content.js` uniquement, et que de nouvelles photos se déposent dans
`images/`.
