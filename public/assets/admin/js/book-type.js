/**
 * Book type on the admin book form.
 *
 * The book's type decides which workflow the rest of the form is for, so
 * everything this file does is show-and-tell: the matching info message and
 * field block become visible, the other one disappears, and the "I understand
 * this changes the type" confirmation appears only when the selection no
 * longer matches the book being edited.
 *
 * It never carries a rule on its own. The server applies the real rules in
 * BookRequest (the PDF upload, the ownership block, the confirmation), and the
 * markup still submits and validates with JavaScript disabled - this file only
 * decides what is on screen while the form is being filled in. The one thing
 * it does actively prevent is a PDF picked and then abandoned by switching to
 * E-Book: that file input is cleared rather than travelling with the form into
 * a validation error the admin would not expect.
 *
 * Like book-pricing.js, the behaviour travels with the form partial: the
 * standalone page calls initBookType() on load and admin-modal.js calls it
 * again on the fragment it injects, so the popup and the page behave the same.
 */
(function () {
    'use strict';

    var CHOICE = '[data-book-type-choice]';
    var BLOCK = '[data-book-type-block]';
    var CONFIRM = '[data-book-type-confirm]';
    var FILE = '[data-book-pdf-file]';

    var TYPE_EBOOK = 'ebook';
    var TYPE_PDF = 'pdf';

    function selectedType(root) {
        var checked = root.querySelector(CHOICE + ':checked');

        return checked ? checked.value : null;
    }

    /**
     * The type the book already has, if this form is editing one. Absent on
     * the create form, where nothing can be changed out from under an owner.
     */
    function originalType(root) {
        var holder = root.querySelector(CONFIRM);

        return holder ? holder.getAttribute('data-book-type-original') : null;
    }

    function fileValue(root, attribute) {
        var input = root.querySelector(FILE);

        return input ? input.getAttribute(attribute) === '1' : false;
    }

    function sync(scope) {
        var root = scope || document;
        var type = selectedType(root);
        var original = originalType(root);
        var changed = !!original && type !== original && (type === TYPE_EBOOK || type === TYPE_PDF);

        root.querySelectorAll(CHOICE).forEach(function (choice) {
            var label = choice.closest('.book-type-option');
            if (label) {
                label.classList.toggle('is-checked', choice.checked);
            }
        });

        root.querySelectorAll(BLOCK).forEach(function (block) {
            block.hidden = block.getAttribute('data-book-type-block') !== type;
        });

        var confirm = root.querySelector(CONFIRM);
        if (confirm) {
            confirm.hidden = !changed;

            if (!changed) {
                var box = confirm.querySelector('input[type=checkbox]');
                if (box) {
                    box.checked = false;
                }
            }
        }

        var file = root.querySelector(FILE);
        if (file) {
            if (type === TYPE_EBOOK) {
                file.value = '';
                file.required = false;
            } else {
                // A new PDF book needs one now; an existing one only when the
                // type has just changed to PDF and nothing is stored yet.
                file.required = fileValue(root, 'data-book-pdf-create') ||
                    (changed && !fileValue(root, 'data-book-pdf-existing'));
            }
        }
    }

    /**
     * Wire the type radios in a form. Safe to call more than once.
     *
     * @param {Element|Document} scope Container holding the book form fields.
     */
    function initBookType(scope) {
        var root = scope || document;

        root.querySelectorAll(CHOICE).forEach(function (choice) {
            if (choice.dataset.bound === 'true') {
                return;
            }

            choice.dataset.bound = 'true';
            choice.addEventListener('change', function () {
                sync(root);
            });
        });

        sync(root);
    }

    window.initBookType = initBookType;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initBookType(document);
        });
    } else {
        initBookType(document);
    }
})();
