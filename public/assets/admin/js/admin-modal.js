/**
 * Admin add/edit popups.
 *
 * The admin CRUD pages all reuse one Bootstrap modal. Clicking anything with
 * `data-modal-form="<url>"` fetches that URL as an XHR request, which makes the
 * controller return a bare form partial instead of a whole page, and drops the
 * HTML into the modal.
 *
 * Submitting posts the form with fetch() and asks for JSON back:
 *
 *  - A validation failure comes back as 422 with Laravel's usual error bag.
 *    The messages are painted next to the offending fields on the form that is
 *    already on screen, so nothing the administrator typed - including any
 *    chosen files - is thrown away. This is why the popup never re-fetches the
 *    form on error: the DOM copy is the authoritative one.
 *  - Anything else means the save worked, so the modal closes and the page
 *    reloads to show the fresh list and the flash message.
 *
 * The standalone create/edit pages are untouched, so everything still works
 * with JavaScript disabled.
 */
(function () {
    'use strict';

    var MODAL_ID = 'adminFormModal';
    var LOADING_ID = 'adminFormModalLoading';
    var BODY_ID = 'adminFormModalBody';

    var modalEl = document.getElementById(MODAL_ID);
    if (!modalEl || typeof window.bootstrap === 'undefined') {
        return;
    }

    var modal = new window.bootstrap.Modal(modalEl);
    var loadingEl = document.getElementById(LOADING_ID);
    var bodyEl = document.getElementById(BODY_ID);
    var titleEl = document.getElementById('adminFormModalTitle');

    function setLoading(isLoading) {
        if (loadingEl) {
            loadingEl.style.display = isLoading ? '' : 'none';
        }
    }

    /**
     * Open the modal and load a form into it.
     *
     * @param {string} url        The create/edit page to fetch.
     * @param {string|null} title Heading for the modal, or null for a blank one.
     * @param {string|null} size  Extra dialog size class, e.g. 'modal-xl'.
     */
    function openForm(url, title, size) {
        if (!bodyEl) {
            return;
        }

        if (titleEl) {
            titleEl.textContent = title || '';
        }

        var dialog = modalEl.querySelector('.modal-dialog');
        if (dialog) {
            dialog.classList.remove('modal-xl', 'modal-sm');
            if (size) {
                dialog.classList.add(size);
            }
        }

        bodyEl.innerHTML = '';
        setLoading(true);
        modal.show();

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed with status ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                bodyEl.innerHTML = html;
                setLoading(false);

                // The injected form can bring its own behaviour with it, such as
                // the chapter rich-text toolbar.
                if (typeof window.initChapterEditor === 'function') {
                    window.initChapterEditor(bodyEl);
                }

                if (typeof window.initBookPricing === 'function') {
                    window.initBookPricing(bodyEl);
                }

                if (typeof window.initBookType === 'function') {
                    window.initBookType(bodyEl);
                }

                focusField(bodyEl.querySelector('input:not([type=hidden]), select, textarea'));
            })
            .catch(function (error) {
                setLoading(false);
                if (window.console) {
                    window.console.error(error);
                }
                bodyEl.innerHTML =
                    '<div class="alert alert-danger mb-3">Could not load the form.</div>' +
                    '<a class="btn btn-outline-danger btn-sm" href="' + url + '">Open it as a page</a>';
            });
    }

    function focusField(field) {
        if (field && typeof field.focus === 'function') {
            field.focus();
        }
    }

    /** Every control that belongs to a validation key, e.g. `author_ids[]`. */
    function controlsFor(form, key) {
        // Validation keys are plain field names, so quoting is all that is
        // needed to keep the attribute selector safe.
        var name = key.replace(/["\\]/g, '\\$&');

        return form.querySelectorAll(
            '[name="' + name + '"], [name="' + name + '[]"], [name^="' + name + '["]'
        );
    }

    function clearErrors(form) {
        var summary = form.querySelector('[data-error-summary]');
        if (summary) {
            summary.parentNode.removeChild(summary);
        }

        form.querySelectorAll('.invalid-feedback[data-generated]').forEach(function (node) {
            node.parentNode.removeChild(node);
        });

        form.querySelectorAll('.is-invalid').forEach(function (control) {
            control.classList.remove('is-invalid');
        });
    }

    /**
     * Paint Laravel's 422 error bag onto the form that is already in the DOM.
     */
    function renderErrors(form, errors) {
        clearErrors(form);

        var summary = document.createElement('div');
        summary.className = 'alert alert-danger';
        summary.setAttribute('data-error-summary', '1');

        var heading = document.createElement('strong');
        heading.textContent = 'Please fix the following:';
        summary.appendChild(heading);

        var list = document.createElement('ul');
        list.className = 'mb-0 pl-3';

        var firstControl = null;

        Object.keys(errors).forEach(function (key) {
            var messages = [].concat(errors[key]);
            var controls = controlsFor(form, key);

            if (!firstControl && controls.length) {
                firstControl = controls[0];
            }

            controls.forEach(function (control) {
                control.classList.add('is-invalid');
            });

            // Always collect the message in the summary at the top, even when it
            // has no field of its own to sit beside.
            var item = document.createElement('li');
            item.textContent = messages.join(' ');
            list.appendChild(item);

            // A key does not always map to an input: it can cover a whole set of
            // related fields, or a rule about the form as a whole. There is then
            // nowhere sensible to anchor a message, so the summary carries it.
            if (!controls.length) {
                return;
            }

            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.setAttribute('data-generated', '1');
            feedback.textContent = messages.join(' ');

            // Where the message goes depends on the markup Bootstrap expects it
            // beside: inside a .form-group, after a bare .form-control, or next
            // to a custom checkbox/radio.
            var last = controls[controls.length - 1];
            var group = last.closest ? last.closest('.form-group') : null;

            if (group) {
                group.appendChild(feedback);
            } else if (last.classList.contains('form-control')) {
                last.parentNode.insertBefore(feedback, last.nextSibling);
            } else {
                var holder = (last.closest && last.closest('.custom-control')) || last.parentNode;
                holder.appendChild(feedback);
            }
        });

        summary.appendChild(list);
        form.insertBefore(summary, form.firstChild);

        if (firstControl) {
            summary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Open a form for any element carrying data-modal-form.
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest && event.target.closest('[data-modal-form]');
        if (!trigger) {
            return;
        }

        // Let people still open the real page (new tab, middle click, no JS).
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
            return;
        }

        event.preventDefault();

        openForm(
            trigger.getAttribute('data-modal-form'),
            trigger.getAttribute('data-modal-title') || trigger.textContent.trim(),
            trigger.getAttribute('data-modal-size') || null
        );
    });

    // Submit whatever form is inside the modal.
    if (bodyEl) {
        bodyEl.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.tagName !== 'FORM' || form.dataset.submitting === 'true') {
                return;
            }

            event.preventDefault();
            form.dataset.submitting = 'true';

            var submitButton = form.querySelector('button[type=submit]');
            if (submitButton) {
                submitButton.disabled = true;
            }

            // FormData keeps the file inputs, the repeated `name[]` fields and
            // the hidden @method field intact, so nothing needs encoding by hand.
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (response.status === 422) {
                        return response.json().then(function (data) {
                            renderErrors(form, data.errors || {});
                        });
                    }

                    if (!response.ok && response.status !== 0) {
                        throw new Error('Save failed with status ' + response.status);
                    }

                    modal.hide();

                    // A saved book may have somewhere else to go: an E-Book
                    // lands on its chapter manager, a PDF on its edit page
                    // where its file and reading settings live. Anything else
                    // simply returns to the list it came from.
                    response.json().then(function (data) {
                        if (data && data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }).catch(function () {
                        window.location.reload();
                    });
                })
                .catch(function (error) {
                    if (window.console) {
                        window.console.error(error);
                    }
                    // Never strand the user: fall back to a normal submit.
                    form.submit();
                })
                .then(function () {
                    form.dataset.submitting = 'false';
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        });
    }
})();
