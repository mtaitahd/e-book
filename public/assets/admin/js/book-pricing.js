/**
 * Free vs Paid on the admin book form.
 *
 * The form posts `pricing_type` alongside `price`, and only one of the two is
 * ever meaningful: a free book has no price to enter, a paid book must have
 * one. This hides the price box for a free book so the admin is not asked for
 * an amount that will be ignored.
 *
 * The price input is never `required` in the markup - it is switched on and off
 * from here - so the form still submits (and the server still validates) if
 * this file fails to load, and a book can never be created with a silently
 * empty price.
 *
 * Like the chapter toolbar, the behaviour travels with the form partial: the
 * standalone page calls initBookPricing() on load and admin-modal.js calls it
 * again on the fragment it injects, so the popup and the page behave the same.
 */
(function () {
    'use strict';

    var CHOICE = '[data-book-pricing-choice]';
    var FIELD = '[data-book-price-field]';
    var INPUT = '[data-book-price-input]';
    var REQUIRED = '[data-book-price-required]';

    function isFree(scope) {
        var checked = scope.querySelector(CHOICE + ':checked');

        return !!checked && checked.value === 'free';
    }

    /**
     * Show or hide the price field to match the selected pricing type.
     *
     * @param {Element|Document} scope Container holding the book form fields.
     */
    function sync(scope) {
        var root = scope || document;

        var field = root.querySelector(FIELD);
        var input = root.querySelector(INPUT);
        var required = root.querySelector(REQUIRED);

        var free = isFree(root);

        if (field) {
            field.hidden = free;
        }

        if (input) {
            // A disabled control is never submitted, so a leftover amount in the
            // box cannot reach the server for a book that is being given away.
            input.disabled = free;
            input.required = !free;

            if (free) {
                input.value = '';
                input.classList.remove('is-invalid');
            }
        }

        if (required) {
            required.hidden = free;
        }
    }

    /**
     * Wire the radios in a form. Safe to call more than once.
     *
     * @param {Element|Document} scope Container holding the book form fields.
     */
    function initBookPricing(scope) {
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

    window.initBookPricing = initBookPricing;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initBookPricing(document);
        });
    } else {
        initBookPricing(document);
    }
})();
