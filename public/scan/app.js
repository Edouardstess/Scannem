/*
 * Scanner du vigile.
 *
 * Trois regles qui gouvernent tout ce fichier :
 *
 * 1. Le serveur fait autorite. Le telephone ne decide jamais seul qu'une carte
 *    est valide ; hors-ligne, il rend un verdict provisoire et met l'operation
 *    en file.
 * 2. Aucun secret de signature n'arrive ici. Le pack hors-ligne ne contient que
 *    des empreintes. Un telephone perdu ne permet pas de fabriquer des cartes.
 * 3. La file n'est jamais vidée avant que le serveur ait confirme. Une entree
 *    perdue, c'est une personne comptee comme entree sans l'etre, ou l'inverse.
 */

(function () {
  'use strict';

  var CLE_JETON = 'scannem.token';
  var CLE_LABEL = 'scannem.label';
  var CLE_FILE = 'scannem.queue';
  var CLE_PACK = 'scannem.pack';
  var CLE_LOCAUX = 'scannem.used';

  var $ = function (id) { return document.getElementById(id); };

  /*
   * Prefixe d'installation.
   *
   * Le scanner vit toujours sous <prefixe>/scan/ : ce qui precede dans l'URL est
   * le prefixe — vide quand Scannem occupe la racine du site, '/scannem' quand il
   * est dans un sous-dossier. Sans ca, un appel ecrit en dur vers /api/redeem.php
   * sortirait du dossier de l'application et tomberait sur la page 404 du
   * serveur, que le scanner prendrait pour une panne reseau.
   */
  var BASE = location.pathname.replace(/\/scan(\/[^\/]*)?$/, '');

  var etat = {
    jeton: null,
    label: '',
    file: [],
    pack: null,
    utiliseesLocalement: {},
    enPause: false,
    flux: null,
    detecteur: null,
    dernierCode: '',
    dernierMoment: 0,
    // Synchronisation en cours. Plusieurs evenements peuvent la declencher au
    // meme instant ('online', retour d'arriere-plan, bouton manuel) ; sans ce
    // verrou, deux envois partent avec la meme file et le second se fait
    // refuser, ce qui fabrique de faux litiges. Or les litiges sont le seul
    // signal de fraude reelle : les polluer les rend inutilisables.
    syncEnCours: null
  };

  // ------------------------------------------------------------- Stockage

  function lire(cle, defaut) {
    try {
      var brut = localStorage.getItem(cle);
      return brut === null ? defaut : JSON.parse(brut);
    } catch (e) {
      return defaut;
    }
  }

  function ecrire(cle, valeur) {
    try {
      localStorage.setItem(cle, JSON.stringify(valeur));
      return true;
    } catch (e) {
      // Quota depasse ou mode prive. On previent plutot que d'echouer en silence :
      // une file qui ne s'enregistre pas, ce sont des entrees perdues.
      console.warn('Ecriture locale impossible', e);
      return false;
    }
  }

  function chargerEtat() {
    etat.jeton = lire(CLE_JETON, null);
    etat.label = lire(CLE_LABEL, '');
    etat.file = lire(CLE_FILE, []) || [];
    etat.pack = lire(CLE_PACK, null);
    etat.utiliseesLocalement = lire(CLE_LOCAUX, {}) || {};
  }

  // ----------------------------------------------------------------- API

  function appel(route, options) {
    options = options || {};

    var entetes = { 'Content-Type': 'application/json' };
    if (etat.jeton) {
      entetes['Authorization'] = 'Bearer ' + etat.jeton;
    }

    var controleur = new AbortController();
    // Delai court : a la porte, mieux vaut basculer vite en hors-ligne que de
    // faire patienter la file d'attente devant un ecran qui tourne.
    var minuteur = setTimeout(function () { controleur.abort(); }, options.timeout || 6000);

    return fetch(BASE + route, {
      method: options.method || 'GET',
      headers: entetes,
      body: options.body ? JSON.stringify(options.body) : undefined,
      signal: controleur.signal,
      cache: 'no-store'
    }).then(function (r) {
      clearTimeout(minuteur);
      return r.json().then(function (data) {
        return { status: r.status, ok: r.ok, data: data };
      }).catch(function () {
        return { status: r.status, ok: false, data: { message: 'Reponse illisible du serveur.' } };
      });
    }).catch(function (e) {
      clearTimeout(minuteur);
      throw e;
    });
  }

  // ---------------------------------------------------------- Enrolement

  function enroler() {
    var code = $('code').value.trim().toUpperCase();
    var msg = $('enroll-msg');

    if (!code) {
      msg.className = 'msg err';
      msg.textContent = 'Saisis le code fourni par l organisateur.';
      return;
    }

    msg.className = 'msg';
    msg.textContent = 'Enrolement en cours...';

    appel('/api/enroll.php', { method: 'POST', body: { code: code } }).then(function (r) {
      if (!r.ok || !r.data.ok) {
        msg.className = 'msg err';
        msg.textContent = r.data.message || 'Enrolement refuse.';
        return;
      }

      etat.jeton = r.data.token;
      etat.label = r.data.label || 'Appareil';
      ecrire(CLE_JETON, etat.jeton);
      ecrire(CLE_LABEL, etat.label);

      demarrer();
    }).catch(function () {
      msg.className = 'msg err';
      msg.textContent = 'Serveur injoignable. Verifie la connexion, puis reessaie.';
    });
  }

  // ------------------------------------------------------------- Verdict

  var LIBELLES = {
    admitted: 'ENTREE AUTORISEE',
    already_used: 'DEJA UTILISEE',
    forged: 'CARTE NON VALIDE',
    revoked: 'CARTE ANNULEE',
    unknown: 'CARTE INCONNUE',
    offline_pending: 'ADMIS SOUS RESERVE',
    rate_limited: 'TROP DE SCANS',
    server_busy: 'SERVEUR OCCUPE',
    server_error: 'ERREUR SERVEUR'
  };

  var ICONES = {
    admitted: '✓',
    already_used: '✕',
    forged: '✕',
    revoked: '✕',
    unknown: '?',
    offline_pending: '⚠',
    rate_limited: '⏱',
    server_busy: '↻',
    server_error: '✕'
  };

  // Incidents techniques : la carte n'a PAS ete consommee, rescanner est sans
  // danger. Ils ne doivent donc jamais s'afficher en rouge, sinon un vigile
  // presse refuse quelqu'un de parfaitement legitime.
  var INCIDENTS = ['server_busy', 'rate_limited'];

  function couleur(resultat) {
    if (resultat === 'admitted') return 'green';
    if (resultat === 'offline_pending' || INCIDENTS.indexOf(resultat) !== -1) return 'amber';
    return 'red';
  }

  function vibrer(resultat) {
    if (!navigator.vibrate) return;
    // Motifs distincts : le vigile apprend a reconnaitre le refus sans regarder.
    navigator.vibrate(resultat === 'admitted' ? 60 : [90, 70, 90, 70, 160]);
  }

  function afficherVerdict(resultat, detail) {
    var v = $('verdict');

    v.className = 'verdict ' + couleur(resultat);
    $('verdict-icon').textContent = ICONES[resultat] || '?';
    $('verdict-label').textContent = LIBELLES[resultat] || resultat;
    $('verdict-detail').innerHTML = detail || '';

    v.classList.remove('hidden');
    etat.enPause = true;
    vibrer(resultat);

    // Une admission s'enchaine vite : on relance la camera tout seul. Un refus
    // reste affiche, parce qu'il demande une discussion avec la personne.
    if (resultat === 'admitted') {
      setTimeout(function () {
        if (etat.enPause && $('verdict-label').textContent === LIBELLES.admitted) {
          reprendre();
        }
      }, 1400);
    }
  }

  function reprendre() {
    $('verdict').classList.add('hidden');
    etat.enPause = false;
    etat.dernierCode = '';
  }

  // ------------------------------------------------------- Traitement scan

  function normaliser(brut) {
    var v = String(brut || '').trim();

    if (v.indexOf('/') !== -1) {
      var queue = v.substring(v.lastIndexOf('/') + 1);
      if (queue) v = queue;
    }
    if (v.indexOf('?') !== -1) v = v.split('?')[0];

    v = v.toUpperCase().replace(/[^0-9A-Z.]/g, '');

    return v.replace(/I/g, '1').replace(/L/g, '1').replace(/O/g, '0').replace(/U/g, 'V');
  }

  function empreinteLocale(payload) {
    // Le pack stocke SHA-256('scannem-fp:' + uid) tronque. On extrait l'uid du
    // payload et on recalcule. crypto.subtle est asynchrone, d'ou la promesse.
    var morceaux = payload.split('.');
    if (morceaux.length !== 3) return Promise.resolve(null);

    var uid = morceaux[1];
    var octets = new TextEncoder().encode('scannem-fp:' + uid);

    if (!crypto.subtle) return Promise.resolve(null);

    return crypto.subtle.digest('SHA-256', octets).then(function (buf) {
      var hex = Array.prototype.map.call(new Uint8Array(buf), function (b) {
        return ('0' + b.toString(16)).slice(-2);
      }).join('');
      return hex.substring(0, 16);
    }).catch(function () { return null; });
  }

  function traiter(brut) {
    var payload = normaliser(brut);

    if (!payload || payload.length < 10) return;

    // Anti-rebond : la camera relit le meme code 30 fois par seconde.
    var maintenant = Date.now();
    if (payload === etat.dernierCode && maintenant - etat.dernierMoment < 2500) return;

    etat.dernierCode = payload;
    etat.dernierMoment = maintenant;

    if (navigator.onLine) {
      enLigne(payload);
    } else {
      horsLigne(payload);
    }
  }

  function enLigne(payload) {
    appel('/api/redeem.php', {
      method: 'POST',
      body: { payload: payload, client_at: new Date().toISOString() }
    }).then(function (r) {
      if (r.status === 401) {
        // Appareil desactive par l'organisateur, ou jeton efface cote serveur.
        alert('Cet appareil n est plus autorise. Contacte l organisateur.');
        desenroler();
        return;
      }

      if (!r.data || !r.data.result) {
        // Reponse inattendue : on bascule en hors-ligne plutot que de bloquer
        // la file d'attente a l'entree.
        horsLigne(payload);
        return;
      }

      // Incident technique : la carte n'a pas ete consommee. On libere
      // immediatement l'anti-rebond, sinon le vigile qui represente la meme
      // carte dans la seconde se heurterait a un ecran muet pendant 2,5 s.
      if (INCIDENTS.indexOf(r.data.result) !== -1) {
        etat.dernierCode = '';
        afficherVerdict(
          r.data.result,
          echapper(r.data.message || '') + '<br><br><strong>La carte n a pas ete utilisee.</strong>'
        );
        return;
      }

      var detail = '';

      if (r.data.result === 'already_used' && r.data.first_scan) {
        var t = r.data.first_scan.at || '';
        detail = 'Premier passage : <strong>' + echapper(heureCourte(t)) + '</strong>'
               + '<br>Porte : <strong>' + echapper(r.data.first_scan.device) + '</strong>'
               + '<br><br>Cette carte a deja servi. C est probablement une copie.';
      } else if (r.data.result === 'forged') {
        detail = 'Ce code n a pas ete emis par ce systeme.<br>Carte fabriquee, ou QR trop abime.';
      } else if (r.data.result === 'revoked') {
        detail = 'Carte annulee par l organisateur.';
      } else if (r.data.result === 'unknown') {
        detail = 'Signature correcte, mais la carte est absente de la base.<br>Signale-le a l organisateur.';
      } else if (r.data.result === 'admitted' && r.data.holder) {
        detail = 'Titulaire : <strong>' + echapper(r.data.holder) + '</strong>';
      }

      afficherVerdict(r.data.result, detail);
      majReseau(true);
    }).catch(function () {
      // Timeout ou coupure au moment precis du scan.
      majReseau(false);
      horsLigne(payload);
    });
  }

  function horsLigne(payload) {
    // Doublon deja vu par CE telephone pendant la coupure.
    if (etat.utiliseesLocalement[payload]) {
      afficherVerdict('already_used',
        'Deja scannee sur cet appareil a <strong>'
        + echapper(heureCourte(etat.utiliseesLocalement[payload]))
        + '</strong>.<br><br>Hors-ligne : seuls les doublons vus sur ce telephone sont detectes.');
      return;
    }

    var suite = function (connue) {
      // connue === false : l'empreinte est absente d'un pack pourtant charge.
      // Le code n'a jamais existe, ou il a ete annule depuis. On refuse.
      if (connue === false) {
        afficherVerdict('forged',
          'Ce code ne figure pas dans la liste des cartes valides.<br>'
          + '<span style="opacity:.85">Verification hors-ligne, pack du '
          + echapper(heureCourte(etat.pack.generated_at)) + '</span>');
        return;
      }

      var horodatage = new Date().toISOString();

      etat.file.push({ payload: payload, client_at: horodatage });
      etat.utiliseesLocalement[payload] = horodatage;

      var okFile = ecrire(CLE_FILE, etat.file);
      ecrire(CLE_LOCAUX, etat.utiliseesLocalement);

      majFile();

      var avertissement = okFile
        ? ''
        : '<br><br><strong>Attention : enregistrement local impossible.</strong> Note ce passage a la main.';

      // connue === null : aucun pack charge, on ne peut rien verifier du tout.
      var verifie = connue === true
        ? 'Code present dans le pack hors-ligne.'
        : 'Aucun pack charge : ce code n a pas pu etre verifie.';

      afficherVerdict('offline_pending',
        verifie + '<br>Sera confirme des le retour du reseau.' + avertissement);
    };

    if (!etat.pack || !etat.pack.fingerprints) {
      suite(null);
      return;
    }

    empreinteLocale(payload).then(function (fp) {
      if (fp === null) {
        suite(null);
        return;
      }
      suite(rechercheDichotomique(etat.pack.fingerprints, fp));
    });
  }

  function rechercheDichotomique(liste, cible) {
    var bas = 0, haut = liste.length - 1;

    while (bas <= haut) {
      var milieu = (bas + haut) >> 1;
      if (liste[milieu] === cible) return true;
      if (liste[milieu] < cible) bas = milieu + 1; else haut = milieu - 1;
    }

    return false;
  }

  // ------------------------------------------------------ Synchronisation

  function synchroniser(silencieux) {
    // Un envoi est deja parti : on s'y raccroche au lieu d'en lancer un second.
    if (etat.syncEnCours) {
      return etat.syncEnCours;
    }

    etat.syncEnCours = executerSync(silencieux).then(function (r) {
      etat.syncEnCours = null;
      return r;
    }, function (e) {
      etat.syncEnCours = null;
      throw e;
    });

    return etat.syncEnCours;
  }

  function executerSync(silencieux) {
    var msg = $('sync-msg');

    if (etat.file.length === 0) {
      if (!silencieux) { msg.className = 'msg'; msg.textContent = 'Rien a synchroniser.'; }
      return Promise.resolve();
    }

    if (!navigator.onLine) {
      if (!silencieux) { msg.className = 'msg err'; msg.textContent = 'Pas de reseau.'; }
      return Promise.resolve();
    }

    if (!silencieux) { msg.className = 'msg'; msg.textContent = 'Envoi de ' + etat.file.length + ' entree(s)...'; }

    // On envoie une copie : si la requete echoue, la file d'origine reste intacte.
    var lot = etat.file.slice(0, 2000);

    return appel('/api/sync.php', {
      method: 'POST',
      body: { queue: lot },
      timeout: 25000
    }).then(function (r) {
      if (!r.ok || !r.data.ok) {
        if (!silencieux) { msg.className = 'msg err'; msg.textContent = r.data.message || 'Synchronisation refusee.'; }
        return;
      }

      // Le serveur a traite le lot : on ne retire QUE ces entrees-la.
      etat.file = etat.file.slice(lot.length);
      ecrire(CLE_FILE, etat.file);
      majFile();

      var texte = r.data.applied + ' confirmee(s)';
      if (r.data.disputed > 0) texte += ', ' + r.data.disputed + ' en litige';
      if (r.data.rejected > 0) texte += ', ' + r.data.rejected + ' refusee(s)';

      msg.className = r.data.disputed > 0 ? 'msg err' : 'msg ok';
      msg.textContent = texte + '.';

      if (r.data.disputed > 0 && !silencieux) {
        alert(r.data.disputed + ' carte(s) avaient deja ete utilisees ailleurs pendant la coupure.\n\n'
            + 'Elles apparaissent dans les litiges cote administration.');
      }

      // Il reste des entrees : on enchaine sur le tour suivant. Appel direct a
      // executerSync et non a synchroniser : le verrou est encore pose par
      // l'appel en cours, et passer par lui ferait attendre la promesse
      // elle-meme, donc un blocage definitif.
      if (etat.file.length > 0) return executerSync(silencieux);
    }).catch(function () {
      if (!silencieux) { msg.className = 'msg err'; msg.textContent = 'Envoi impossible. La file est conservee.'; }
    });
  }

  function telechargerPack() {
    var msg = $('sync-msg');
    msg.className = 'msg';
    msg.textContent = 'Telechargement du pack...';

    appel('/api/pack.php', { timeout: 30000 }).then(function (r) {
      if (!r.ok || !r.data.ok) {
        msg.className = 'msg err';
        msg.textContent = r.data.message || 'Telechargement refuse.';
        return;
      }

      etat.pack = r.data;

      if (ecrire(CLE_PACK, etat.pack)) {
        msg.className = 'msg ok';
        msg.textContent = r.data.count + ' cartes dans le pack hors-ligne.';
      } else {
        msg.className = 'msg err';
        msg.textContent = 'Pack trop volumineux pour ce telephone. Reste en ligne.';
        etat.pack = null;
      }

      majMenu();
    }).catch(function () {
      msg.className = 'msg err';
      msg.textContent = 'Serveur injoignable.';
    });
  }

  // -------------------------------------------------------------- Camera

  function demarrerCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      $('hint').textContent = 'Camera indisponible. Utilise la saisie manuelle (menu).';
      return;
    }

    navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
    }).then(function (flux) {
      etat.flux = flux;

      var video = $('video');
      video.srcObject = flux;
      video.setAttribute('playsinline', true);
      video.play();

      if ('BarcodeDetector' in window) {
        // Decodeur natif : nettement plus rapide et plus tolerant que jsQR.
        BarcodeDetector.getSupportedFormats().then(function (formats) {
          if (formats.indexOf('qr_code') !== -1) {
            etat.detecteur = new BarcodeDetector({ formats: ['qr_code'] });
          }
          boucle();
        }).catch(boucle);
      } else {
        boucle();
      }
    }).catch(function (e) {
      var raison = e && e.name === 'NotAllowedError'
        ? 'Acces camera refuse. Autorise-le dans les reglages du navigateur.'
        : 'Camera inaccessible (' + (e && e.name ? e.name : 'erreur') + '). Le HTTPS est obligatoire hors localhost.';
      $('hint').textContent = raison + ' Saisie manuelle disponible dans le menu.';
    });
  }

  function boucle() {
    var video = $('video');
    var canvas = $('canvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });

    var tourner = function () {
      if (etat.enPause || video.readyState !== video.HAVE_ENOUGH_DATA) {
        requestAnimationFrame(tourner);
        return;
      }

      if (etat.detecteur) {
        etat.detecteur.detect(video).then(function (codes) {
          if (codes && codes.length > 0) traiter(codes[0].rawValue);
        }).catch(function () {
          // Le detecteur natif a lache : on bascule sur jsQR pour la suite.
          etat.detecteur = null;
        }).then(function () {
          requestAnimationFrame(tourner);
        });
        return;
      }

      // Repli jsQR. On reduit l'image : inutile d'analyser du 1280x720 a
      // chaque trame, et ca economise la batterie sur une longue soiree.
      var largeur = 480;
      var hauteur = Math.round(video.videoHeight * (largeur / video.videoWidth)) || 360;

      canvas.width = largeur;
      canvas.height = hauteur;
      ctx.drawImage(video, 0, 0, largeur, hauteur);

      try {
        var image = ctx.getImageData(0, 0, largeur, hauteur);
        var code = window.jsQR ? window.jsQR(image.data, largeur, hauteur, {
          inversionAttempts: 'dontInvert'
        }) : null;

        if (code && code.data) traiter(code.data);
      } catch (e) {
        // Trame illisible : sans importance, la suivante arrive.
      }

      requestAnimationFrame(tourner);
    };

    requestAnimationFrame(tourner);
  }

  // ------------------------------------------------------------ Interface

  function echapper(s) {
    return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function heureCourte(iso) {
    if (!iso) return '?';
    var d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  function majReseau(enLigne) {
    var n = $('net');
    var ok = enLigne === undefined ? navigator.onLine : enLigne;

    n.textContent = ok ? 'en ligne' : 'hors-ligne';
    n.className = ok ? 'net' : 'net off';

    var m = $('m-net');
    if (m) m.textContent = ok ? 'en ligne' : 'hors-ligne';
  }

  function majFile() {
    var badge = $('queue-badge');
    badge.textContent = etat.file.length;
    badge.classList.toggle('hidden', etat.file.length === 0);

    var m = $('m-queue');
    if (m) m.textContent = etat.file.length;
  }

  function majMenu() {
    $('m-label').textContent = etat.label || '—';
    $('m-pack').textContent = etat.pack
      ? etat.pack.count + ' cartes (' + heureCourte(etat.pack.generated_at) + ')'
      : 'aucun';
    majFile();
    majReseau();
  }

  function desenroler() {
    if (etat.file.length > 0) {
      if (!confirm(etat.file.length + ' entree(s) ne sont pas encore synchronisees.\n\n'
                 + 'Les perdre definitivement ?')) {
        return;
      }
    }

    [CLE_JETON, CLE_LABEL, CLE_FILE, CLE_PACK, CLE_LOCAUX].forEach(function (c) {
      try { localStorage.removeItem(c); } catch (e) { /* rien a faire */ }
    });

    location.reload();
  }

  /**
   * Pre-chauffage : verifie que le serveur repond, avant le premier invite.
   *
   * Sur un hebergement mutualise, la premiere requete apres une longue inactivite
   * est la plus lente (caches froids, connexion base a rouvrir). Autant la payer
   * pendant que le vigile installe son poste plutot que devant quelqu'un qui
   * attend. Et si le serveur est en panne, il le sait tout de suite.
   *
   * Appel volontairement leger : /api/health sans ?deep=1 ne touche pas la base.
   */
  function prechauffer() {
    if (!navigator.onLine) {
      majReseau(false);
      return;
    }

    appel('/api/health.php', { timeout: 8000 }).then(function (r) {
      var ok = r.ok && r.data && r.data.ok === true;
      majReseau(ok);

      if (!ok) {
        $('hint').textContent = 'Le serveur ne repond pas normalement. Previens l organisateur.';
      }
    }).catch(function () {
      // Injoignable : on le dit maintenant, pas au premier scan.
      majReseau(false);
      $('hint').textContent = 'Serveur injoignable. Le mode hors-ligne prendra le relais.';
    });
  }

  function demarrer() {
    $('enroll').classList.add('hidden');
    $('main').classList.remove('hidden');

    $('device-label').textContent = etat.label || 'Appareil';

    majMenu();
    majReseau();
    prechauffer();
    demarrerCamera();

    // Une file en attente au demarrage : le reseau est peut-etre revenu.
    if (etat.file.length > 0 && navigator.onLine) {
      synchroniser(true);
    }
  }

  // --------------------------------------------------------- Branchements

  function brancher() {
    $('enroll-go').addEventListener('click', enroler);
    $('code').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') enroler();
    });

    $('verdict-next').addEventListener('click', reprendre);
    $('verdict').addEventListener('click', function (e) {
      if (e.target === this) reprendre();
    });

    $('menu-btn').addEventListener('click', function () {
      majMenu();
      $('menu').classList.remove('hidden');
      etat.enPause = true;
    });

    $('menu-close').addEventListener('click', function () {
      $('menu').classList.add('hidden');
      if ($('verdict').classList.contains('hidden')) etat.enPause = false;
    });

    $('manual-go').addEventListener('click', function () {
      var v = $('manual').value;
      if (!v.trim()) return;

      $('manual').value = '';
      $('menu').classList.add('hidden');
      etat.enPause = false;
      etat.dernierCode = '';
      traiter(v);
    });

    $('manual').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') $('manual-go').click();
    });

    $('sync-btn').addEventListener('click', function () { synchroniser(false); });
    $('pack-btn').addEventListener('click', telechargerPack);
    $('reset-btn').addEventListener('click', desenroler);

    window.addEventListener('online', function () {
      majReseau(true);
      prechauffer();
      synchroniser(true);
    });

    window.addEventListener('offline', function () { majReseau(false); });

    // Retour d'arriere-plan : le telephone a pu retrouver du reseau en poche.
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && navigator.onLine && etat.file.length > 0) {
        synchroniser(true);
      }
    });

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register(BASE + '/scan/sw.js').catch(function () {
        // Sans service worker, l'app ne demarre pas hors-ligne mais fonctionne.
      });
    }
  }

  // ---------------------------------------------------------------- Boot

  chargerEtat();
  brancher();

  if (etat.jeton) {
    demarrer();
  } else {
    // Code passe en parametre depuis un lien /s/... : on pre-remplit.
    var params = new URLSearchParams(location.search);
    if (params.get('code')) $('code').value = params.get('code');
  }
})();
