/**
 * Administration behaviour: sidebar, confirmations, uploader, reordering,
 * copy-to-clipboard and the chart hover layer.
 *
 * Every destructive action has a server-side guard; the confirmations here
 * are ergonomics, not protection.
 */
(function () {
    'use strict';

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    /* --- Sidebar --------------------------------------------------------- */

    var sidebar = document.querySelector('[data-sidebar]');
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButton = document.querySelector('[data-sidebar-close]');

    if (sidebar && openButton) {
        openButton.addEventListener('click', function () { sidebar.classList.add('is-open'); });
    }

    if (sidebar && closeButton) {
        closeButton.addEventListener('click', function () { sidebar.classList.remove('is-open'); });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar) {
            sidebar.classList.remove('is-open');
        }
    });

    /* --- Flash dismissal and confirmations ------------------------------- */

    document.addEventListener('click', function (event) {
        var dismiss = event.target.closest('[data-dismiss]');

        if (dismiss) {
            var flash = dismiss.closest('.flash');

            if (flash) {
                flash.remove();
            }
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-confirm]');

        if (form && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    /* --- Copy to clipboard ----------------------------------------------- */

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-copy]');

        if (!trigger) {
            return;
        }

        var target = document.querySelector(trigger.getAttribute('data-copy'));

        if (!target) {
            return;
        }

        var restore = trigger.textContent;

        var done = function () {
            trigger.textContent = 'Copié';
            window.setTimeout(function () { trigger.textContent = restore; }, 1600);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(target.value).then(done, fallback);
        } else {
            fallback();
        }

        // execCommand is deprecated but remains the only option on plain HTTP,
        // which is exactly where a photographer tests a fresh install.
        function fallback() {
            target.removeAttribute('readonly');
            target.select();
            target.setSelectionRange(0, target.value.length);

            try {
                document.execCommand('copy');
                done();
            } catch (error) {
                trigger.textContent = 'Copiez manuellement';
            }

            target.setAttribute('readonly', 'readonly');
        }
    });

    /* --- Expiry field toggle --------------------------------------------- */

    var expirySelect = document.querySelector('[data-expiry-select]');
    var expiryField = document.querySelector('[data-expiry-date]');

    if (expirySelect && expiryField) {
        expirySelect.addEventListener('change', function () {
            expiryField.hidden = expirySelect.value !== 'custom';
        });
    }

    /* --- Uploader --------------------------------------------------------- */

    var uploader = document.querySelector('[data-uploader]');

    if (uploader) {
        initUploader(uploader);
    }

    function initUploader(container) {
        var endpoint = container.getAttribute('data-endpoint');
        var token = container.getAttribute('data-csrf') || csrfToken;
        var dropzone = container.querySelector('[data-dropzone]');
        var input = container.querySelector('[data-file-input]');
        var queue = container.querySelector('[data-upload-queue]');
        var summary = container.querySelector('[data-upload-summary]');
        var bar = container.querySelector('[data-upload-bar]');
        var status = container.querySelector('[data-upload-status]');
        var totalCounter = document.querySelector('[data-photo-total]');
        var maxBytes = parseInt(container.getAttribute('data-max-bytes') || '0', 10);

        var pending = [];
        var completed = 0;
        var failed = 0;
        var running = false;

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            dropzone.addEventListener(name, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            dropzone.addEventListener(name, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                enqueue(event.dataTransfer.files);
            }
        });

        input.addEventListener('change', function () {
            if (input.files.length > 0) {
                enqueue(input.files);
                input.value = '';
            }
        });

        function enqueue(files) {
            Array.prototype.slice.call(files).forEach(function (file) {
                var item = { file: file, row: buildRow(file), attempts: 0 };

                // Refused before sending: the server would drop it anyway, and
                // only after the whole file had crossed the network.
                if (maxBytes > 0 && file.size > maxBytes) {
                    markFailed(item, 'trop lourde (' + megabytes(file.size) + ', maximum ' + megabytes(maxBytes)
                        + ') — réduisez-la puis importez-la à nouveau', false);
                    return;
                }

                pending.push(item);
            });

            summary.hidden = false;
            render();
            pump();
        }

        function buildRow(file) {
            var row = document.createElement('li');
            row.className = 'uploader__item';

            var name = document.createElement('span');
            name.className = 'uploader__item-name';
            name.textContent = file.name;

            var state = document.createElement('span');
            state.className = 'uploader__item-state';
            state.textContent = 'en attente';

            row.appendChild(name);
            row.appendChild(state);
            queue.appendChild(row);

            return { element: row, state: state };
        }

        function render() {
            var total = completed + failed + pending.length + (running ? 1 : 0);
            var done = completed + failed;
            var percent = total === 0 ? 0 : Math.round((done / total) * 100);

            bar.style.width = percent + '%';
            status.textContent = done + ' / ' + total + ' traitée(s)'
                + (failed > 0 ? ' · ' + failed + ' échec(s)' : '');
        }

        /**
         * Upload one file at a time.
         *
         * Sequential rather than parallel: shared hosting handles a single
         * large multipart request far better than eight at once, and a
         * 250-photo wedding gallery is exactly where that matters.
         */
        function pump() {
            if (running || pending.length === 0) {
                if (!running && pending.length === 0) {
                    render();
                }

                return;
            }

            var item = pending.shift();
            running = true;
            item.row.state.textContent = 'envoi…';
            render();

            var body = new FormData();
            body.append('photo', item.file);
            body.append('_token', token);

            fetch(endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: body
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    }, function () {
                        // Not JSON: the web server itself refused the request.
                        return { ok: false, data: { error: response.status === 413
                            ? 'fichier trop volumineux pour le serveur'
                            : 'erreur du serveur (' + response.status + ')' } };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data.uploaded && result.data.uploaded.length > 0) {
                        completed += 1;
                        item.row.element.classList.add('is-done');
                        item.row.state.textContent = 'importée';

                        if (totalCounter && typeof result.data.total === 'number') {
                            totalCounter.textContent = result.data.total;
                        }

                        return;
                    }

                    var message = 'échec';

                    if (result.data.failed && result.data.failed.length > 0) {
                        message = result.data.failed[0].error;
                    } else if (result.data.error) {
                        message = result.data.error;
                    }

                    markFailed(item, message);
                })
                .catch(function () {
                    markFailed(item, 'réseau indisponible');
                })
                .finally(function () {
                    running = false;
                    render();
                    pump();
                });
        }

        function megabytes(bytes) {
            return (bytes / 1048576).toFixed(1).replace('.', ',') + ' Mo';
        }

        function markFailed(item, message, retryable) {
            failed += 1;
            item.row.element.classList.add('is-failed');
            item.row.state.textContent = '';

            var label = document.createElement('span');
            label.textContent = message + ' ';
            item.row.state.appendChild(label);

            if (retryable === false) {
                render();
                return;
            }

            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'uploader__retry';
            retry.textContent = 'réessayer';

            retry.addEventListener('click', function () {
                failed -= 1;
                item.row.element.classList.remove('is-failed');
                item.row.state.textContent = 'en attente';
                item.attempts += 1;
                pending.push(item);
                render();
                pump();
            });

            item.row.state.appendChild(retry);
        }
    }

    /* --- Photo reordering ------------------------------------------------- */

    var sortable = document.querySelector('[data-sortable]');

    if (sortable) {
        initSortable(sortable);
    }

    function initSortable(container) {
        var endpoint = container.getAttribute('data-reorder-endpoint');
        var dragged = null;

        container.addEventListener('dragstart', function (event) {
            var figure = event.target.closest('.admin-photo');

            if (!figure) {
                return;
            }

            dragged = figure;
            figure.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            // Firefox refuses to start a drag without data on the transfer.
            event.dataTransfer.setData('text/plain', figure.getAttribute('data-photo-id'));
        });

        container.addEventListener('dragend', function () {
            if (dragged) {
                dragged.classList.remove('is-dragging');
                dragged = null;
            }

            Array.prototype.slice.call(container.querySelectorAll('.is-drop-target')).forEach(function (element) {
                element.classList.remove('is-drop-target');
            });
        });

        container.addEventListener('dragover', function (event) {
            event.preventDefault();
            var target = event.target.closest('.admin-photo');

            if (target && target !== dragged) {
                target.classList.add('is-drop-target');
            }
        });

        container.addEventListener('dragleave', function (event) {
            var target = event.target.closest('.admin-photo');

            if (target) {
                target.classList.remove('is-drop-target');
            }
        });

        container.addEventListener('drop', function (event) {
            event.preventDefault();
            var target = event.target.closest('.admin-photo');

            if (!target || !dragged || target === dragged) {
                return;
            }

            target.classList.remove('is-drop-target');

            var items = Array.prototype.slice.call(container.querySelectorAll('.admin-photo'));
            var from = items.indexOf(dragged);
            var to = items.indexOf(target);

            if (from < to) {
                target.after(dragged);
            } else {
                target.before(dragged);
            }

            persistOrder();
        });

        function persistOrder() {
            var order = Array.prototype.slice
                .call(container.querySelectorAll('.admin-photo'))
                .map(function (figure) { return parseInt(figure.getAttribute('data-photo-id'), 10); });

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order: order })
            }).catch(function () { /* The visual order stays; a reload restores truth. */ });
        }
    }

    /* --- Chart hover layer ------------------------------------------------ */

    Array.prototype.slice.call(document.querySelectorAll('[data-chart]')).forEach(initChart);

    function initChart(svg) {
        var data;

        try {
            data = JSON.parse(svg.getAttribute('data-chart'));
        } catch (error) {
            return;
        }

        var hover = svg.querySelector('[data-chart-hover]');
        var surface = svg.querySelector('[data-chart-surface]');
        var tooltip = svg.closest('.chart__canvas').querySelector('[data-chart-tooltip]');

        if (!hover || !surface || !tooltip || data.labels.length === 0) {
            return;
        }

        var crosshair = hover.querySelector('.chart__crosshair');
        var dots = Array.prototype.slice.call(hover.querySelectorAll('.chart__dot'));
        var geometry = data.geometry;
        var plotWidth = geometry.width - geometry.padLeft - geometry.padRight;
        var plotHeight = geometry.height - geometry.padTop - geometry.padBottom;
        var count = data.labels.length;

        function xFor(index) {
            return count <= 1
                ? geometry.padLeft + plotWidth / 2
                : geometry.padLeft + index * (plotWidth / (count - 1));
        }

        function yFor(value) {
            var ratio = geometry.max === 0 ? 0 : value / geometry.max;

            return geometry.padTop + plotHeight - ratio * plotHeight;
        }

        function indexFromEvent(event) {
            var rect = svg.getBoundingClientRect();
            var scale = geometry.width / rect.width;
            var x = (event.clientX - rect.left) * scale;
            var ratio = (x - geometry.padLeft) / plotWidth;

            return Math.max(0, Math.min(count - 1, Math.round(ratio * (count - 1))));
        }

        function showAt(index, event) {
            hover.hidden = false;
            crosshair.setAttribute('x1', xFor(index));
            crosshair.setAttribute('x2', xFor(index));

            data.series.forEach(function (series, position) {
                var dot = dots[position];

                if (!dot) {
                    return;
                }

                dot.setAttribute('cx', xFor(index));
                dot.setAttribute('cy', yFor(series.values[index] || 0));
            });

            var rows = data.series.map(function (series) {
                return '<span class="chart__tooltip-row">'
                    + '<span class="chart__tooltip-swatch" style="background: var(--series-'
                    + (series.variant === 'accent' ? 'accent' : 'primary') + ')"></span>'
                    + escapeHtml(series.label) + ' : <strong>' + (series.values[index] || 0) + '</strong>'
                    + '</span>';
            }).join('');

            tooltip.innerHTML = '<span class="chart__tooltip-date">'
                + escapeHtml(data.raw[index] || data.labels[index]) + '</span>' + rows;
            tooltip.hidden = false;

            var rect = svg.getBoundingClientRect();
            tooltip.style.left = (xFor(index) / geometry.width) * rect.width + 'px';
            tooltip.style.top = (yFor(Math.max.apply(null, data.series.map(function (series) {
                return series.values[index] || 0;
            }))) / geometry.height) * rect.height + 'px';
        }

        function hide() {
            hover.hidden = true;
            tooltip.hidden = true;
        }

        surface.addEventListener('mousemove', function (event) {
            showAt(indexFromEvent(event), event);
        });

        surface.addEventListener('mouseleave', hide);

        surface.addEventListener('touchmove', function (event) {
            if (event.touches.length === 1) {
                showAt(indexFromEvent(event.touches[0]), event);
            }
        }, { passive: true });

        surface.addEventListener('touchend', hide);
    }

    function escapeHtml(value) {
        var element = document.createElement('span');
        element.textContent = String(value);

        return element.innerHTML;
    }
}());
