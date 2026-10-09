<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display a category and its published books.
     */
    public function show(Category $category): View
    {
        $category->load(['books' => function ($query) {
            $query->published()->with(['authors', 'categories']);
        }]);

        return view('categories.show', compact('category'));
    }
}