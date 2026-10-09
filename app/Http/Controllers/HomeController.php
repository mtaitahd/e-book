<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the public storefront landing page.
     */
    public function index(): View
    {
        $books = Book::published()
            ->with(['authors', 'categories'])
            ->latest('published_at')
            ->take(6)
            ->get();

        $moreBooks = Book::published()
            ->with(['authors', 'categories'])
            ->latest('published_at')
            ->skip(6)
            ->take(6)
            ->get();

        return view('home', compact('books', 'moreBooks'));
    }
}