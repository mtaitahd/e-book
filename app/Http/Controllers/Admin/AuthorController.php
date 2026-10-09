<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersFormForModal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuthorRequest;
use App\Models\Author;
use App\Support\Slugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthorController extends Controller
{
    use RendersFormForModal;

    /**
     * List authors with pagination.
     */
    public function index(): View
    {
        return view('admin.authors.index', [
            'authors' => Author::withCount('books')->orderBy('name')->paginate(15),
        ]);
    }

    /**
     * Show the create-author form.
     */
    public function create(Request $request): View
    {
        return $this->renderForm($request, 'admin.authors.create', 'admin.authors._form_page', [
            'author' => null,
        ]);
    }

    /**
     * Store a newly created author.
     */
    public function store(AuthorRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        Author::create([
            'name' => $validated['name'],
            'slug' => Slugs::unique($validated['name'], Author::class),
            'bio' => $validated['bio'] ?? null,
            'status' => Author::STATUS_ACTIVE,
        ]);

        session()->flash('success', 'Author created successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => 'Author created successfully.'])
            : redirect()->route('admin.authors.index');
    }

    /**
     * Show the edit-author form.
     */
    public function edit(Request $request, Author $author): View
    {
        return $this->renderForm($request, 'admin.authors.edit', 'admin.authors._form_page', [
            'author' => $author,
        ]);
    }

    /**
     * Update the given author.
     */
    public function update(AuthorRequest $request, Author $author): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $author->update([
            'name' => $validated['name'],
            'slug' => $validated['name'] !== $author->name
                ? Slugs::unique($validated['name'], Author::class, $author->id)
                : $author->slug,
            'bio' => $validated['bio'] ?? null,
        ]);

        session()->flash('success', 'Author updated successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => 'Author updated successfully.'])
            : redirect()->route('admin.authors.index');
    }

    /**
     * Archive an author instead of hard-deleting, so existing book-author
     * relationships never become invalid. Archived authors remain visible
     * in book forms so published books keep their attribution.
     */
    public function destroy(Author $author): RedirectResponse
    {
        $author->update(['status' => Author::STATUS_INACTIVE]);

        session()->flash('success', 'Author archived successfully.');

        return redirect()->route('admin.authors.index');
    }

    /**
     * Bring an archived author back so they appear in the catalogue again.
     */
    public function restore(Author $author): RedirectResponse
    {
        $author->update(['status' => Author::STATUS_ACTIVE]);

        session()->flash('success', 'Author restored successfully.');

        return redirect()->route('admin.authors.index');
    }

    /**
     * Permanently delete an author.
     *
     * Refused while any book still credits the author: the book form and the
     * published page both resolve attribution through the pivot, so silently
     * dropping a credited author would orphan a book's byline. Archive it
     * instead, or unlink the author from its books first.
     */
    public function forceDestroy(Author $author): RedirectResponse
    {
        $credited = $author->books()->count();

        if ($credited > 0) {
            session()->flash('error', 'This author cannot be permanently deleted because they are still '
                .'credited on '.$credited.' book'.($credited === 1 ? '' : 's')
                .'. Archive the author instead, or remove them from the book'.($credited === 1 ? '' : 's').' first.');

            return redirect()->route('admin.authors.index');
        }

        $author->delete();

        session()->flash('success', 'Author permanently deleted.');

        return redirect()->route('admin.authors.index');
    }
}
