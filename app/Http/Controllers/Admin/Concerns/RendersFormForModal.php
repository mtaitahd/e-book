<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a create/edit page double as the content of the admin popup.
 *
 * The admin CRUD screens all share one Bootstrap modal. When the page is
 * requested normally the full Blade view is returned, exactly as before, so
 * direct URLs and JavaScript-free browsing keep working. When the request
 * comes from assets/admin/js/admin-modal.js (identified by the
 * X-Requested-With header it sends) the same controller hands back only the
 * form partial, which the modal injects.
 *
 * Because both paths render the identical partial, the popup and the page can
 * never drift apart.
 */
trait RendersFormForModal
{
    /**
     * Render the standalone page, or just its form when asked for via XHR.
     *
     * @param  string  $pageView  Full page view, e.g. 'admin.books.create'.
     * @param  string  $formView  Form partial, e.g. 'admin.books._form_page'.
     * @param  array  $data  View data shared by both.
     */
    protected function renderForm(Request $request, string $pageView, string $formView, array $data = []): View
    {
        if ($request->ajax()) {
            // The partial branches on `$modal` to hide the page-only controls,
            // such as the archive button.
            return view($formView, $data + ['modal' => true]);
        }

        return view($pageView, $data);
    }
}
