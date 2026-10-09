/*
 * SweetAlert flash feedback.
 *
 * Reads every [data-swal-flash] block rendered by partials/swal-flash.blade.php,
 * hides the plain server-rendered fallback list and replaces it with a SweetAlert
 * popup. Safe to load on pages that render no flash block.
 */
(function (window, document) {
    'use strict';

    function parseJson(value, fallback) {
        if (!value) return fallback;
        try {
            var parsed = JSON.parse(value);
            return parsed === null ? fallback : parsed;
        } catch (err) {
            return fallback;
        }
    }

    function readPayload(node) {
        var messages = parseJson(node.getAttribute('data-swal-messages'), null);

        if (Array.isArray(messages)) {
            messages = messages
                .map(function (message) {
                    return typeof message === 'string' ? message.trim() : '';
                })
                .filter(Boolean);
        } else if (typeof messages === 'string') {
            messages = messages.trim() ? [messages.trim()] : [];
        } else {
            // Fall back to whatever the server rendered inside the list.
            messages = Array.prototype.map.call(
                node.querySelectorAll('[data-swal-fallback] li'),
                function (li) {
                    return (li.textContent || '').trim();
                }
            ).filter(Boolean);
        }

        var tone = node.getAttribute('data-swal-type');
        var type = tone === 'success' || tone === 'info' || tone === 'warning' ? tone : 'error';

        return {
            type: type,
            title: (node.getAttribute('data-swal-title') || '').trim(),
            ok: (node.getAttribute('data-swal-ok') || '').trim() || 'Got it',
            messages: messages
        };
    }

    function showPopup(payload) {
        var api = window.swal || window.Swal;
        if (typeof api !== 'function' || !payload.messages.length) return false;

        // This bundle is SweetAlert 2.x, whose signature is
        // swal(message, title, icon, options) -- note the title is the SECOND
        // argument. Passing it first makes it the heading, which is what we want.
        var isError = payload.type === 'error';
        var options = {
            buttons: { confirm: payload.ok },
            closeOnEsc: true,
            closeOnClickOutside: false,
            dangerMode: isError
        };

        // initText() turns each newline into a <br>, so join for multiple lines.
        var body = payload.messages.length > 1
            ? payload.messages.join('\n\n')
            : payload.messages[0];

        api.call(window, payload.title, body, payload.type, options);

        return true;
    }

    function highlightInvalidFields() {
        var invalid = document.querySelectorAll(
            '.is-invalid, input[aria-invalid="true"], [data-swal-invalid]'
        );

        for (var i = 0; i < invalid.length; i++) {
            var field = invalid[i];
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
        }

        if (invalid.length) {
            var wrapper = invalid[0].closest ? invalid[0].closest('form') : null;
            if (wrapper) wrapper.classList.add('has-swal-error');
        }
    }

    function focusFirstField() {
        var form = document.querySelector('form.has-swal-error') || document.querySelector('form');
        if (!form) return;

        var field = form.querySelector('input:not([type=hidden]):not([disabled])');
        if (!field) return;

        window.setTimeout(function () {
            try {
                field.focus({ preventScroll: true });
            } catch (err) {
                field.focus();
            }
        }, 120);
    }

    function run() {
        var blocks = document.querySelectorAll('[data-swal-flash]');

        for (var i = 0; i < blocks.length; i++) {
            var node = blocks[i];
            var payload = readPayload(node);
            var shown = showPopup(payload);

            var fallback = node.querySelector('[data-swal-fallback]');
            if (fallback && shown) {
                fallback.setAttribute('hidden', 'hidden');
                // Also clear it inline: project CSS could otherwise set a display
                // value on .errors/.success and win over the [hidden] attribute.
                fallback.style.display = 'none';
            }

            if (payload.type === 'error' && shown) {
                highlightInvalidFields();
                focusFirstField();
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    window.EbookSwalFlash = { show: showPopup, read: readPayload, run: run };
})(window, document);
