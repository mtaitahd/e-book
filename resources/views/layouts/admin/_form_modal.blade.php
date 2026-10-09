{{--
    Shared shell for the admin add/edit popups.

    Every admin form (books, authors, categories, chapters) is injected into
    this one modal by assets/admin/js/admin-modal.js. The controllers return
    a bare form partial when the request is XHR, so the same markup powers
    both the popup and the standalone page.
--}}
<div class="modal fade" id="adminFormModal" tabindex="-1" role="dialog" aria-labelledby="adminFormModalTitle" aria-hidden="true"
     data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-3">
                <h5 class="modal-title h6 m-0 font-weight-bold" id="adminFormModalTitle">&nbsp;</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center py-5" id="adminFormModalLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading…</span>
                    </div>
                </div>
                <div id="adminFormModalBody"></div>
            </div>
        </div>
    </div>
</div>
