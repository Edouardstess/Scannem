/**
 * Public site behaviour.
 *
 * Everything here is progressive enhancement: navigation, forms and content
 * all work with JavaScript disabled. Nothing security-relevant lives in this
 * file — the server decides what a visitor may see.
 */
(function () {
    'use strict';

    /* --- Mobile navigation ---------------------------------------------- */

    var navToggle = document.querySelector('[data-nav-toggle]');
    var nav = document.querySelector('[data-nav]');

    if (navToggle && nav) {
        var navLabel = navToggle.querySelector('[data-nav-label]');

        var setNavState = function (open) {
            nav.classList.toggle('is-open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (navLabel) {
                navLabel.textContent = open ? 'Fermer le menu' : 'Ouvrir le menu';
            }
        };

        navToggle.addEventListener('click', function () {
            setNavState(!nav.classList.contains('is-open'));
        });

        // Tapping a link closes the panel; leaving it open over the next page
        // would hide the content the visitor just asked for.
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setNavState(false);
            }
        });

        // Escape closes the menu and returns focus to the control that opened
        // it, which is what a keyboard user expects.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                setNavState(false);
                navToggle.focus();
            }
        });
    }

    /* --- Header shadow on scroll ---------------------------------------- */

    var header = document.querySelector('[data-header]');

    if (header) {
        var applyScrollState = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 12);
        };

        applyScrollState();
        window.addEventListener('scroll', applyScrollState, { passive: true });
    }

    /* --- Dismissible flash messages ------------------------------------- */

    document.addEventListener('click', function (event) {
        var dismiss = event.target.closest('[data-dismiss]');

        if (dismiss) {
            var flash = dismiss.closest('.flash');

            if (flash) {
                flash.remove();
            }
        }
    });

    /* --- Portfolio lightbox --------------------------------------------- */

    var lightboxLinks = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));

    if (lightboxLinks.length === 0) {
        return;
    }

    var overlay = null;
    var overlayImage = null;
    var overlayCaption = null;
    var overlayCounter = null;
    var currentIndex = 0;
    var lastFocused = null;

    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Photo en grand');

        overlay.innerHTML =
            '<div class="lightbox__bar">' +
                '<span class="lightbox__counter"></span>' +
                '<div class="lightbox__tools">' +
                    '<button type="button" class="lightbox__tool" data-close>' +
                        '<span class="sr-only">Fermer</span><span aria-hidden="true">&times;</span>' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<button type="button" class="lightbox__nav lightbox__nav--prev" data-prev>' +
                '<span class="sr-only">Photo précédente</span><span aria-hidden="true">&#8249;</span>' +
            '</button>' +
            '<div class="lightbox__stage"><img class="lightbox__image" alt=""></div>' +
            '<button type="button" class="lightbox__nav lightbox__nav--next" data-next>' +
                '<span class="sr-only">Photo suivante</span><span aria-hidden="true">&#8250;</span>' +
            '</button>' +
            '<p class="lightbox__caption"></p>';

        document.body.appendChild(overlay);

        overlayImage = overlay.querySelector('.lightbox__image');
        overlayCaption = overlay.querySelector('.lightbox__caption');
        overlayCounter = overlay.querySelector('.lightbox__counter');

        overlay.querySelector('[data-close]').addEventListener('click', close);
        overlay.querySelector('[data-prev]').addEventListener('click', function () { step(-1); });
        overlay.querySelector('[data-next]').addEventListener('click', function () { step(1); });

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay || event.target.classList.contains('lightbox__stage')) {
                close();
            }
        });
    }

    function show(index) {
        currentIndex = (index + lightboxLinks.length) % lightboxLinks.length;
        var link = lightboxLinks[currentIndex];

        overlayImage.src = link.getAttribute('href');
        overlayImage.alt = link.getAttribute('data-caption') || '';
        overlayCaption.textContent = link.getAttribute('data-caption') || '';
        overlayCounter.textContent = (currentIndex + 1) + ' / ' + lightboxLinks.length;
    }

    function step(delta) {
        show(currentIndex + delta);
    }

    function open(index) {
        if (!overlay) {
            buildOverlay();
        }

        lastFocused = document.activeElement;
        show(index);
        document.body.classList.add('is-locked');
        overlay.hidden = false;
        overlay.querySelector('[data-close]').focus();
    }

    function close() {
        overlay.hidden = true;
        document.body.classList.remove('is-locked');

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    lightboxLinks.forEach(function (link, index) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            open(index);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (!overlay || overlay.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowLeft') {
            step(-1);
        } else if (event.key === 'ArrowRight') {
            step(1);
        }
    });
}());
