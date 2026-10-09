<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersFormForModal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Support\Slugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use RendersFormForModal;

    /**
     * List categories with pagination.
     */
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount('books')->orderBy('name')->paginate(15),
        ]);
    }

    /**
     * Show the create-category form.
     */
    public function create(Request $request): View
    {
        return $this->renderForm($request, 'admin.categories.create', 'admin.categories._form_page', [
            'category' => null,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(CategoryRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        Category::create([
            'name' => $validated['name'],
            'slug' => Slugs::unique($validated['name'], Category::class),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? Category::STATUS_ACTIVE,
        ]);

        session()->flash('success', 'Category created successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => 'Category created successfully.'])
            : redirect()->route('admin.categories.index');
    }

    /**
     * Show the edit-category form.
     */
    public function edit(Request $request, Category $category): View
    {
        return $this->renderForm($request, 'admin.categories.edit', 'admin.categories._form_page', [
            'category' => $category,
        ]);
    }

    /**
     * Update the given category.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['name'] !== $category->name
                ? Slugs::unique($validated['name'], Category::class, $category->id)
                : $category->slug,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $category->status,
        ]);

        session()->flash('success', 'Category updated successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => 'Category updated successfully.'])
            : redirect()->route('admin.categories.index');
    }

    /**
     * Archive (deactivate) a category instead of hard-deleting, so book
     * category relationships always remain valid for future order records.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $category->update(['status' => Category::STATUS_INACTIVE]);

        session()->flash('success', 'Category archived successfully.');

        return redirect()->route('admin.categories.index');
    }

    /**
     * Bring an archived category back so it reappears in the catalogue.
     */
    public function restore(Category $category): RedirectResponse
    {
        $category->update(['status' => Category::STATUS_ACTIVE]);

        session()->flash('success', 'Category restored successfully.');

        return redirect()->route('admin.categories.index');
    }

    /**
     * Permanently delete a category.
     *
     * Refused while any book still carries the category: the book form and
     * the published page both resolve cataloguing through the pivot, so
     * silently dropping a linked category would loosen a book's grouping.
     * Archive it instead, or unlink the category from its books first.
     */
    public function forceDestroy(Category $category): RedirectResponse
    {
        $linked = $category->books()->count();

        if ($linked > 0) {
            session()->flash('error', 'This category cannot be permanently deleted because it is still '
                .'linked to '.$linked.' book'.($linked === 1 ? '' : 's')
                .'. Archive the category instead, or remove it from the book'.($linked === 1 ? '' : 's').' first.');

            return redirect()->route('admin.categories.index');
        }

        $category->delete();

        session()->flash('success', 'Category permanently deleted.');

        return redirect()->route('admin.categories.index');
    }
}
