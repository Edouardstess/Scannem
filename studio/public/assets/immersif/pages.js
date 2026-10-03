/**
 * L'ENFANT VISUAL — mouvement des pages intérieures.
 *
 * Amélioration progressive : navigation, formulaires et contenus marchent
 * sans ce fichier. Rien ici ne touche à la sécurité ; le serveur décide de
 * ce qu'un visiteur peut voir.
 */
(function () {
  'use strict';

  window.__pagesReady = true;

  var root = document.documentElement;
  var body = document.body;
  var motion = root.classList.contains('js');
  var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ── Révélations au défilement ─────────────────────────────────────── */

  var revealables = Array.prototype.slice.call(
    document.querySelectorAll('[data-reveal], .split, .clip[data-clip], .footer-name')
  );

  function show(el) { el.classList.add('is-in'); }

  if (!motion || !('IntersectionObserver' in window)) {
    revealables.forEach(show);
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          show(entry.target.__reveal || entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    revealables.forEach(function (el) {
      // Une image encore « rognée » (clip-path) ne croise jamais l'écran
      // aux yeux de l'observateur : on observe son parent à sa place.
      var watched = el.matches('.clip[data-clip]') ? el.parentElement : el;
      watched.__reveal = el;
      io.observe(watched);
    });

    // L'en-tête de page apparaît dès que le rideau se lève.
    window.setTimeout(function () {
      document.querySelectorAll('.ph .split, .ph [data-reveal], .err [data-reveal], .err .split').forEach(show);
    }, 260);
  }

  /* ── Parallaxe et bandeau défilant ─────────────────────────────────── */

  var speeds = Array.prototype.slice.call(document.querySelectorAll('[data-speed]'));
  var marquees = Array.prototype.slice.call(document.querySelectorAll('[data-marquee]'));
  var ticking = false;

  function frame() {
    ticking = false;
    var vh = window.innerHeight;
    var narrow = window.innerWidth < 640;

    speeds.forEach(function (el) {
      var box = el.parentElement.getBoundingClientRect();

      if (box.bottom < -200 || box.top > vh + 200) {
        return;
      }

      var speed = parseFloat(el.getAttribute('data-speed')) || 0;
      var offset = (box.top + box.height / 2 - vh / 2) * speed * (narrow ? 0.5 : 1);
      el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
    });

    marquees.forEach(function (el) {
      var box = el.getBoundingClientRect();
      var progress = (vh - box.top) / (vh + box.height);
      var travel = Math.max(0, el.scrollWidth - el.parentElement.clientWidth);
      el.style.transform = 'translate3d(' + (-Math.min(1, Math.max(0, progress)) * travel).toFixed(1) + 'px,0,0)';
    });
  }

  function requestFrame() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(frame);
    }
  }

  if (motion && (speeds.length || marquees.length)) {
    window.addEventListener('scroll', requestFrame, { passive: true });
    window.addEventListener('resize', requestFrame);
    frame();
  }

  /* ── Image qui suit le curseur sur la liste des prestations ────────── */

  var follow = document.querySelector('[data-follow]');

  if (follow && finePointer && motion) {
    var followImg = follow.querySelector('img');
    var target = { x: 0, y: 0 };
    var pos = { x: 0, y: 0 };
    var running = false;

    var loop = function () {
      pos.x += (target.x - pos.x) * 0.16;
      pos.y += (target.y - pos.y) * 0.16;
      follow.style.transform = 'translate3d(' + (pos.x - follow.offsetWidth / 2) + 'px,' + (pos.y - follow.offsetHeight / 2) + 'px,0) rotate(' + ((target.x - pos.x) * 0.03).toFixed(2) + 'deg)';

      if (running) {
        window.requestAnimationFrame(loop);
      }
    };

    document.querySelectorAll('[data-follow-src]').forEach(function (row) {
      row.addEventListener('mouseenter', function (event) {
        followImg.src = row.getAttribute('data-follow-src');
        if (!running) {
          pos.x = target.x = event.clientX;
          pos.y = target.y = event.clientY;
          running = true;
          window.requestAnimationFrame(loop);
        }
        follow.classList.add('is-on');
      });
      row.addEventListener('mousemove', function (event) {
        target.x = event.clientX;
        target.y = event.clientY;
      });
      row.addEventListener('mouseleave', function () {
        follow.classList.remove('is-on');
        running = false;
      });
    });
  }

  /* ── Menu plein écran (écrans étroits) ─────────────────────────────── */

  var menuButton = document.querySelector('[data-menu-toggle]');
  var menu = document.getElementById('menu');

  if (menuButton && menu) {
    var label = menuButton.querySelector('[data-menu-label]');

    var setMenu = function (open) {
      body.classList.toggle('menu-open', open);
      body.classList.toggle('is-locked', open);
      menu.hidden = false;
      menu.setAttribute('aria-hidden', open ? 'false' : 'true');
      menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');

      if (label) {
        label.textContent = open ? 'Fermer' : 'Menu';
      }

      if (open) {
        var first = menu.querySelector('a');
        if (first) { window.setTimeout(function () { first.focus(); }, 120); }
      }
    };

    menu.setAttribute('aria-hidden', 'true');
    menuButton.addEventListener('click', function () {
      setMenu(!body.classList.contains('menu-open'));
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && body.classList.contains('menu-open')) {
        setMenu(false);
        menuButton.focus();
      }
    });
    menu.addEventListener('click', function (event) {
      if (event.target.closest('a') && event.target.closest('a').hash) {
        setMenu(false);
      }
    });
  }

  /* ── Rideau de sortie vers une autre page du site ──────────────────── */

  if (motion) {
    document.addEventListener('click', function (event) {
      var link = event.target.closest('a[href]');

      if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
          || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')
          || link.hasAttribute('data-lightbox')) {
        return;
      }

      var url = new URL(link.href, window.location.href);

      if (url.origin !== window.location.origin || url.protocol.indexOf('http') !== 0
          || (url.pathname === window.location.pathname && url.hash !== '')) {
        return;
      }

      event.preventDefault();
      body.classList.add('is-leaving');
      window.setTimeout(function () { window.location.href = url.href; }, 520);
    });

    // Retour arrière depuis le cache : la page revient sans rideau baissé.
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        body.classList.remove('is-leaving', 'menu-open', 'is-locked');
      }
    });
  }

  /* ── Messages flash ────────────────────────────────────────────────── */

  document.addEventListener('click', function (event) {
    var dismiss = event.target.closest('[data-dismiss]');
    if (dismiss && dismiss.closest('.flash')) {
      dismiss.closest('.flash').remove();
    }
  });

  /* ── Carte chargée à la demande (map_click_to_load) ────────────────── */

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-map-load]');
    if (!button) { return; }

    var facade = button.closest('[data-map-facade]');
    var src = facade ? facade.getAttribute('data-src') || '' : '';

    // Le serveur ne rend que l'intégration Google ; tout le reste est refusé.
    if (src.indexOf('https://www.google.com/maps') !== 0) { return; }

    var frame = document.createElement('iframe');
    frame.src = src;
    frame.title = facade.getAttribute('data-title') || 'Carte';
    frame.setAttribute('allowfullscreen', '');
    frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
    facade.replaceWith(frame);
  });

  /* ── Visionneuse du portfolio ──────────────────────────────────────── */

  var shots = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));

  // Les colonnes décalées mélangent l'ordre du DOM : on suit data-index.
  shots.sort(function (a, b) {
    return (parseInt(a.getAttribute('data-index'), 10) || 0) - (parseInt(b.getAttribute('data-index'), 10) || 0);
  });

  if (shots.length) {
    var box = null;
    var boxImg;
    var boxCap;
    var boxCount;
    var current = 0;
    var lastFocus = null;

    var build = function () {
      box = document.createElement('div');
      box.className = 'lb';
      box.hidden = true;
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-label', 'Photo en grand');
      box.innerHTML =
        '<div class="lb-bar"><span class="mono" data-count></span>' +
        '<button type="button" data-close>Fermer ✕</button></div>' +
        '<div class="lb-stage"><button type="button" class="lb-prev" data-prev aria-label="Photo précédente">←</button>' +
        '<img alt=""><button type="button" class="lb-next" data-next aria-label="Photo suivante">→</button></div>' +
        '<p class="lb-cap mono"></p>';
      body.appendChild(box);
      boxImg = box.querySelector('img');
      boxCap = box.querySelector('.lb-cap');
      boxCount = box.querySelector('[data-count]');
      box.querySelector('[data-close]').addEventListener('click', close);
      box.querySelector('[data-prev]').addEventListener('click', function () { go(current - 1); });
      box.querySelector('[data-next]').addEventListener('click', function () { go(current + 1); });
      box.addEventListener('click', function (event) {
        if (event.target.classList.contains('lb-stage')) { close(); }
      });

      var startX = null;
      box.addEventListener('touchstart', function (event) { startX = event.touches[0].clientX; }, { passive: true });
      box.addEventListener('touchend', function (event) {
        if (startX === null) { return; }
        var dx = event.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 50) { go(current + (dx < 0 ? 1 : -1)); }
        startX = null;
      });
    };

    var go = function (index) {
      current = (index + shots.length) % shots.length;
      var link = shots[current];
      boxImg.src = link.getAttribute('href');
      boxImg.alt = link.getAttribute('data-caption') || '';
      boxCap.textContent = link.getAttribute('data-caption') || '';
      boxCount.textContent = String(current + 1).padStart(2, '0') + ' / ' + String(shots.length).padStart(2, '0');
    };

    var open = function (index) {
      if (!box) { build(); }
      lastFocus = document.activeElement;
      go(index);
      box.hidden = false;
      body.classList.add('is-locked');
      box.querySelector('[data-close]').focus();
    };

    var close = function () {
      box.hidden = true;
      body.classList.remove('is-locked');
      if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    };

    shots.forEach(function (link, index) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        open(index);
      });
    });

    document.addEventListener('keydown', function (event) {
      if (!box || box.hidden) { return; }
      if (event.key === 'Escape') { close(); }
      else if (event.key === 'ArrowLeft') { go(current - 1); }
      else if (event.key === 'ArrowRight') { go(current + 1); }
    });
  }
}());
