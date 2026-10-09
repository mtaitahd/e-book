/**
 * The chapter rich-text toolbar.
 *
 * This lives here rather than in a `@push('scripts')` block on the chapter form
 * because a pushed block only ever reaches the page it was rendered into. The
 * popup fetches the form as a bare partial over XHR, so anything the form pushed
 * would be discarded on the way and the toolbar would arrive dead.
 *
 * Loading it as a plain script means one implementation serves both: it wires
 * itself up on a normal page load, and assets/admin/js/admin-modal.js calls
 * `initChapterEditor` again after it injects a form into the dialog. The marker
 * attribute keeps it from being wired twice.
 */
window.initChapterEditor = function (root) {
    var scope = root || document;
    var editor = scope.querySelector('#content');

    if (!editor || editor.dataset.chapterEditorReady === '1') {
        return;
    }

    editor.dataset.chapterEditorReady = '1';

    function syncToolbar() {
        var toolbar = document.querySelector('.btn-toolbar-chapter');
        if (!toolbar) {
            return;
        }

        toolbar.querySelectorAll('[data-chapter-command]').forEach(function (button) {
            var command = button.getAttribute('data-chapter-command');
            var value = button.getAttribute('data-chapter-value') || command;
            var active = false;

            try {
                active = document.queryCommandState(command);
            } catch (e) {
                active = false;
            }

            if (command === 'formatBlock') {
                active = String(editor.value).length >= 0 &&
                    (document.queryCommandValue('formatBlock') || '').toLowerCase() === value;
            }

            button.classList.toggle('active', !!active);
        });
    }

    editor.addEventListener('input', function () {
        syncToolbar();
    });

    document.querySelectorAll('[data-chapter-command]').forEach(function (button) {
        // Keep the caret where it was instead of losing it to the button click.
        button.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });

        button.addEventListener('click', function () {
            var command = button.getAttribute('data-chapter-command');
            var value = button.getAttribute('data-chapter-value');

            editor.focus();

            if (command === 'createLink') {
                var url = window.prompt('Link address (https://…)');
                if (url === null) {
                    return;
                }
                if (url === '') {
                    document.execCommand('unlink', false, null);
                    return;
                }
                document.execCommand('createLink', false, url);
            } else if (command === 'formatBlock') {
                document.execCommand('formatBlock', false, value);
            } else {
                document.execCommand(command, false, null);
            }

            syncToolbar();
        });
    });
};

document.addEventListener('DOMContentLoaded', function () {
    window.initChapterEditor(document);
});
