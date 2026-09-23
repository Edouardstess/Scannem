/**
 * Client gallery: lazy pagination, lightbox, favourites, ZIP downloads.
 *
 * None of this is a security boundary. Hiding a download button does not
 * protect anything — the server refuses an original to a VIEW token whatever
 * the browser does. This file exists to make the gallery pleasant, not safe.
 */
(function () {
    'use strict';

    var config = window.GALLERY_CONFIG || {};
    var grid = document.querySelector('[data-photo-grid]');
    var root = document.querySelector('[data-lightbox-root]');

    if (!grid) {
        return;
    }

    /* ------------------------------------------------------------------ */
    /* Photo model                                                         */
    /* ------------------------------------------------------------------ */

    function collectPhotos() {
        return Array.prototype.slice.call(grid.querySelectorAll('.photo')).map(function (figure) {
            return {
                element: figure,
                id: parseInt(figure.getAttribute('data-photo-id'), 10),
                preview: figure.getAttribute('data-preview'),
                caption: figure.getAttribute('data-caption') || '',
                download: figure.getAttribute('data-download')
            };
        });
    }

    var photos = collectPhotos();

    /* ------------------------------------------------------------------ */
    /* Lightbox                                                            */
    /* ------------------------------------------------------------------ */

    var lightbox = null;

    if (root) {
        lightbox = {
            root: root,
            image: root.querySelector('[data-lightbox-image]'),
            caption: root.querySelector('[data-lightbox-caption]'),
            counter: root.querySelector('[data-lightbox-counter]'),
            spinner: root.querySelector('[data-lightbox-spinner]'),
            download: root.querySelector('[data-lightbox-download]'),
            index: 0,
            lastFocused: null
        };

        root.querySelector('[data-lightbox-close]').addEventListener('click', closeLightbox);
        root.querySelector('[data-lightbox-prev]').addEventListener('click', function () { stepLightbox(-1); });
        root.querySelector('[data-lightbox-next]').addEventListener('click', function () { stepLightbox(1); });

        var zoomButton = root.querySelector('[data-lightbox-zoom]');

        zoomButton.addEventListener('click', function () {
            var zoomed = root.classList.toggle('is-zoomed');
            zoomButton.setAttribute('aria-pressed', zoomed ? 'true' : 'false');
        });

        root.querySelector('[data-lightbox-fullscreen]').addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (root.requestFullscreen) {
                root.requestFullscreen();
            }
        });

        root.querySelector('[data-lightbox-stage]').addEventListener('click', function (event) {
            if (event.target === event.currentTarget) {
                closeLightbox();
            }
        });

        lightbox.image.addEventListener('load', function () {
            root.classList.remove('is-loading');
            lightbox.spinner.hidden = true;
        });

        document.addEventListener('keydown', function (event) {
            if (root.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                closeLightbox();
            } else if (event.key === 'ArrowLeft') {
                stepLightbox(-1);
            } else if (event.key === 'ArrowRight') {
                stepLightbox(1);
            }
        });

        // Swipe navigation on touch devices.
        var touchStartX = 0;

        root.addEventListener('touchstart', function (event) {
            touchStartX = event.changedTouches[0].clientX;
        }, { passive: true });

        root.addEventListener('touchend', function (event) {
            var delta = event.changedTouches[0].clientX - touchStartX;

            if (Math.abs(delta) > 60) {
                stepLightbox(delta < 0 ? 1 : -1);
            }
        }, { passive: true });
    }

    function openLightbox(index) {
        if (!lightbox) {
            return;
        }

        lightbox.lastFocused = document.activeElement;
        document.body.classList.add('is-locked');
        lightbox.root.hidden = false;
        renderLightbox(index);
        lightbox.root.querySelector('[data-lightbox-close]').focus();
    }

    function closeLightbox() {
        lightbox.root.hidden = true;
        lightbox.root.classList.remove('is-zoomed');
        document.body.classList.remove('is-locked');

        if (document.fullscreenElement) {
            document.exitFullscreen();
        }

        if (lightbox.lastFocused && typeof lightbox.lastFocused.focus === 'function') {
            lightbox.lastFocused.focus();
        }
    }

    function stepLightbox(delta) {
        renderLightbox(lightbox.index + delta);
    }

    function renderLightbox(index) {
        if (photos.length === 0) {
            return;
        }

        lightbox.index = (index + photos.length) % photos.length;

        var photo = photos[lightbox.index];

        lightbox.root.classList.add('is-loading');
        lightbox.root.classList.remove('is-zoomed');
        lightbox.spinner.hidden = false;
        lightbox.image.src = photo.preview;
        lightbox.image.alt = photo.caption;
        lightbox.caption.textContent = photo.caption;
        lightbox.counter.textContent = (lightbox.index + 1) + ' / ' + (config.total || photos.length);

        if (photo.download) {
            lightbox.download.href = photo.download;
            lightbox.download.hidden = false;
        } else {
            lightbox.download.removeAttribute('href');
            lightbox.download.hidden = true;
        }

        preload(lightbox.index + 1);
        preload(lightbox.index - 1);
    }

    // Fetching the neighbours makes arrow-key browsing feel instant without
    // pulling the whole gallery at full size.
    function preload(index) {
        if (photos.length === 0) {
            return;
        }

        var photo = photos[(index + photos.length) % photos.length];
        var image = new Image();
        image.src = photo.preview;
    }

    grid.addEventListener('click', function (event) {
        var button = event.target.closest('[data-open-lightbox]');

        if (!button) {
            return;
        }

        var figure = button.closest('.photo');
        var index = photos.findIndex(function (photo) { return photo.element === figure; });

        if (index >= 0) {
            openLightbox(index);
        }
    });

    /* ------------------------------------------------------------------ */
    /* Infinite loading                                                    */
    /* ------------------------------------------------------------------ */

    var loadMoreButton = document.querySelector('[data-load-more]');
    var nextPage = config.nextPage || 2;
    var hasMore = Boolean(config.hasMore);
    var loading = false;

    function appendPhotos(items) {
        var fragment = document.createDocumentFragment();

        items.forEach(function (item) {
            fragment.appendChild(buildPhotoElement(item));
        });

        grid.appendChild(fragment);
        photos = collectPhotos();
    }

    function buildPhotoElement(item) {
        var figure = document.createElement('figure');
        figure.className = 'photo';
        figure.style.setProperty('--ratio', (item.width || 3) + ' / ' + (item.height || 2));
        figure.setAttribute('data-photo-id', item.id);
        figure.setAttribute('data-preview', item.preview_url);
        figure.setAttribute('data-caption', item.name);

        if (item.download_url) {
            figure.setAttribute('data-download', item.download_url);
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'photo__button';
        button.setAttribute('data-open-lightbox', '');
        button.setAttribute('aria-label', 'Agrandir ' + item.name);

        var image = document.createElement('img');
        image.className = 'photo__image';
        image.src = item.thumb_url;
        image.alt = item.name;
        image.loading = 'lazy';
        image.decoding = 'async';

        button.appendChild(image);
        figure.appendChild(button);

        if (config.selectable && item.download_url) {
            figure.appendChild(buildCheckbox(item));
        }

        if (config.canSelect) {
            figure.appendChild(buildFavouriteButton(item));
        }

        if (config.canDownload && item.download_url) {
            var link = document.createElement('a');
            link.className = 'photo__download';
            link.href = item.download_url;
            link.setAttribute('download', '');
            link.setAttribute('aria-label', 'Télécharger ' + item.name);
            link.innerHTML = '<span aria-hidden="true">&#8595;</span>';
            figure.appendChild(link);
        }

        return figure;
    }

    function buildCheckbox(item) {
        var label = document.createElement('label');
        label.className = 'photo__check';

        var input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'photo__checkbox';
        input.value = item.id;
        input.setAttribute('data-select-photo', '');

        var text = document.createElement('span');
        text.className = 'sr-only';
        text.textContent = 'Sélectionner ' + item.name;

        var box = document.createElement('span');
        box.className = 'photo__check-box';
        box.setAttribute('aria-hidden', 'true');

        label.appendChild(input);
        label.appendChild(text);
        label.appendChild(box);

        return label;
    }

    function buildFavouriteButton(item) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'photo__favourite';
        button.setAttribute('data-favourite', '');
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', 'Ajouter ' + item.name + ' à ma sélection');
        button.innerHTML = '<span aria-hidden="true">&#9825;</span>';

        return button;
    }

    function loadNextPage() {
        if (loading || !hasMore || !config.photosEndpoint) {
            return Promise.resolve();
        }

        loading = true;

        if (loadMoreButton) {
            loadMoreButton.disabled = true;
            loadMoreButton.textContent = 'Chargement…';
        }

        return fetch(config.photosEndpoint + '?page=' + nextPage, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (data) {
                appendPhotos(data.photos || []);
                hasMore = Boolean(data.pagination && data.pagination.has_more);
                nextPage += 1;

                if (!hasMore && loadMoreButton) {
                    loadMoreButton.remove();
                } else if (loadMoreButton) {
                    loadMoreButton.disabled = false;
                    loadMoreButton.textContent = 'Charger plus de photos';
                }
            })
            .catch(function () {
                if (loadMoreButton) {
                    loadMoreButton.disabled = false;
                    loadMoreButton.textContent = 'Réessayer';
                }
            })
            .finally(function () {
                loading = false;
            });
    }

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', loadNextPage);

        // The button remains the accessible control; the observer simply
        // triggers it early when it scrolls into view.
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    loadNextPage();
                }
            }, { rootMargin: '600px' });

            observer.observe(loadMoreButton);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Favourites                                                          */
    /* ------------------------------------------------------------------ */

    var selectionCounter = document.querySelector('[data-selection-count]');

    if (config.selectEndpoint) {
        grid.addEventListener('click', function (event) {
            var button = event.target.closest('[data-favourite]');

            if (!button) {
                return;
            }

            var figure = button.closest('.photo');
            var photoId = figure.getAttribute('data-photo-id');

            button.disabled = true;

            fetch(config.selectEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ photo_id: parseInt(photoId, 10) })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.error) {
                        return;
                    }

                    button.setAttribute('aria-pressed', data.selected ? 'true' : 'false');
                    figure.classList.toggle('is-favourite', Boolean(data.selected));

                    if (selectionCounter) {
                        selectionCounter.textContent = data.total;
                    }
                })
                .catch(function () { /* A failed favourite is not worth interrupting for. */ })
                .finally(function () {
                    button.disabled = false;
                });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Downloads                                                           */
    /* ------------------------------------------------------------------ */

    var archiveEndpoint = config.archiveEndpoint;

    if (!archiveEndpoint) {
        return;
    }

    var selectionBar = document.querySelector('[data-selection-bar]');
    var selectedCount = document.querySelector('[data-selected-count]');
    var progress = document.querySelector('[data-download-progress]');
    var progressBar = document.querySelector('[data-progress-bar]');
    var progressLabel = document.querySelector('[data-progress-label]');
    var selectionMode = false;

    function selectedIds() {
        return Array.prototype.slice
            .call(grid.querySelectorAll('[data-select-photo]:checked'))
            .map(function (input) { return parseInt(input.value, 10); });
    }

    function refreshSelection() {
        var ids = selectedIds();

        if (selectedCount) {
            selectedCount.textContent = ids.length;
        }

        Array.prototype.slice.call(grid.querySelectorAll('[data-select-photo]')).forEach(function (input) {
            input.closest('.photo').classList.toggle('is-selected', input.checked);
        });
    }

    grid.addEventListener('change', function (event) {
        if (event.target.matches('[data-select-photo]')) {
            refreshSelection();
        }
    });

    var selectionModeButton = document.querySelector('[data-selection-mode]');

    if (selectionModeButton && selectionBar) {
        selectionModeButton.addEventListener('click', function () {
            selectionMode = !selectionMode;
            selectionBar.hidden = !selectionMode;
            selectionModeButton.textContent = selectionMode
                ? 'Quitter la sélection'
                : 'Sélectionner des photos';
        });
    }

    var selectAllButton = document.querySelector('[data-select-all]');

    if (selectAllButton) {
        selectAllButton.addEventListener('click', function () {
            Array.prototype.slice.call(grid.querySelectorAll('[data-select-photo]')).forEach(function (input) {
                if (!input.disabled) {
                    input.checked = true;
                }
            });

            refreshSelection();
        });
    }

    var clearSelectionButton = document.querySelector('[data-clear-selection]');

    if (clearSelectionButton) {
        clearSelectionButton.addEventListener('click', function () {
            Array.prototype.slice.call(grid.querySelectorAll('[data-select-photo]')).forEach(function (input) {
                input.checked = false;
            });

            refreshSelection();

            if (selectionBar) {
                selectionBar.hidden = true;
                selectionMode = false;

                if (selectionModeButton) {
                    selectionModeButton.textContent = 'Sélectionner des photos';
                }
            }
        });
    }

    function setProgress(label, percent) {
        if (!progress) {
            return;
        }

        progress.hidden = false;
        progressLabel.textContent = label;
        progressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
    }

    function hideProgressSoon() {
        window.setTimeout(function () {
            if (progress) {
                progress.hidden = true;
                progressBar.style.width = '0';
            }
        }, 4000);
    }

    /**
     * Ask the server to build an archive, then hand the browser its URL.
     *
     * The archive is prepared in one request and fetched in another, so the
     * page can report progress instead of freezing on a multi-gigabyte
     * response, and a failed download can be retried without rebuilding.
     */
    function requestArchive(ids) {
        var total = ids.length > 0 ? ids.length : (config.downloadable || config.total || 0);

        setProgress('Préparation de votre archive (' + total + ' photos)…', 12);

        return fetch(archiveEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ photo_ids: ids })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.error || 'Erreur ' + response.status);
                    }

                    return data;
                });
            })
            .then(function (data) {
                setProgress('Archive prête : ' + data.count + ' photos · ' + data.size + '. Téléchargement…', 100);

                // Navigating rather than using an <a download> keeps the
                // Content-Disposition filename the server chose.
                window.location.href = data.url;
                hideProgressSoon();
            })
            .catch(function (error) {
                setProgress(error.message || "L'archive n'a pas pu être créée.", 100);
                hideProgressSoon();
            });
    }

    var downloadAllButton = document.querySelector('[data-download-all]');

    if (downloadAllButton) {
        downloadAllButton.addEventListener('click', function () {
            downloadAllButton.disabled = true;
            requestArchive([]).finally(function () {
                downloadAllButton.disabled = false;
            });
        });
    }

    var downloadSelectionButton = document.querySelector('[data-download-selection]');

    if (downloadSelectionButton) {
        downloadSelectionButton.addEventListener('click', function () {
            var ids = selectedIds();

            if (ids.length === 0) {
                setProgress('Sélectionnez au moins une photo.', 100);
                hideProgressSoon();

                return;
            }

            downloadSelectionButton.disabled = true;
            requestArchive(ids).finally(function () {
                downloadSelectionButton.disabled = false;
            });
        });
    }
}());
