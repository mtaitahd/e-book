/*
 * Live feedback for the book cover picker.
 *
 * The cover field is a plain Bootstrap custom-file input, which only ever echoed
 * the file name back: an administrator who picked the wrong file, or one over
 * the 2 MB limit, had to submit the whole form to find out, and had no preview of
 * what they were about to save. This shows a preview, the name and the size as
 * soon as a file is chosen, and rejects the two mistakes the server would reject
 * anyway (BookRequest: image, mimes:jpeg,png,webp, max:2048 KB) before the
 * round-trip.
 *
 * The limits are duplicated from the server rule on purpose, so the browser can
 * catch a bad file immediately. The server remains the authority: a client-side
 * check is a convenience, never a guarantee.
 */
(function () {
    'use strict';

    var MAX_KB = 2048;
    var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    function humanSize(kb) {
        if (kb < 1024) {
            return kb + ' KB';
        }
        return (kb / 1024).toFixed(1) + ' MB';
    }

    function bytesToKb(bytes) {
        return Math.round(bytes / 1024);
    }

    /**
     * Render the feedback panel for whichever file is currently selected.
     *
     * @param {HTMLInputElement} input
     */
    function render(input) {
        var panel = document.getElementById('coverImageFeedback');

        if (!panel) {
            return;
        }

        panel.innerHTML = '';
        panel.className = 'mt-2 small';

        var file = input.files && input.files[0];

        if (!file) {
            // No replacement selected - restore existing cover if present
            var existing = document.getElementById('coverImageCurrent');
            if (existing) {
                existing.style.display = '';
            }
            var existingLabel = document.getElementById('coverImageCurrentLabel');
            if (existingLabel) {
                existingLabel.style.display = '';
            }
            return;
        }

        var kb = bytesToKb(file.size);

        if (ALLOWED.indexOf(file.type) === -1) {
            panel.className = 'mt-2 small text-danger';
            panel.textContent = 'That is not a JPG, PNG or WebP image. Choose a different file.';

            return;
        }

        if (kb > MAX_KB) {
            panel.className = 'mt-2 small text-danger';
            panel.textContent = 'That image is ' + humanSize(kb) + '. The cover must not be larger than 2 MB.';

            return;
        }

        var preview = document.createElement('img');
        preview.alt = 'Selected cover preview';
        preview.style.width = '72px';
        preview.style.height = '96px';
        preview.style.objectFit = 'cover';
        preview.style.borderRadius = '4px';
        preview.src = URL.createObjectURL(file);

        var meta = document.createElement('span');
        meta.className = 'small text-success ml-2 align-middle';
        meta.textContent = file.name + ' — ' + humanSize(kb) + '. Ready to upload.';

        panel.appendChild(preview);
        panel.appendChild(meta);

        // Hide the existing saved cover when showing a replacement preview
        var existing = document.getElementById('coverImageCurrent');
        if (existing) {
            existing.style.display = 'none';
        }
        var existingLabel = document.getElementById('coverImageCurrentLabel');
        if (existingLabel) {
            existingLabel.style.display = 'none';
        }

        // Store the object URL on the preview element and revoke it when the input changes again or on page unload
        preview.dataset.objectUrl = preview.src;
        preview.addEventListener('load', function () {
            // Revoke after load to be safe
            try { URL.revokeObjectURL(preview.dataset.objectUrl); } catch (e) {}
        });
    }

    // Delegated from the document because the book form is injected over XHR by
    // admin-modal.js, so a listener bound at load time would never see it.
    // Also handle clearing when form is reset? Not needed for normal use.
    document.addEventListener('change', function (event) {
        var input = event.target;

        if (input && input.id === 'cover_image') {
            render(input);
        }
    });
    
    // Revoke any object URLs when navigating away to prevent leaks
    window.addEventListener('beforeunload', function () {
        var imgs = document.querySelectorAll('#coverImageFeedback img[data-object-url]');
        imgs.forEach(function (img) {
            try { URL.revokeObjectURL(img.dataset.objectUrl); } catch (e) {}
        });
    });
})();
