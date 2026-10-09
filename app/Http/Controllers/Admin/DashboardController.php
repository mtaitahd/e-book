<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the admin area landing dashboard.
     */
    public function index(): View
    {
        $stats = [
            'books' => Book::count(),
            'published_books' => Book::published()->count(),
            'draft_books' => Book::where('status', Book::STATUS_DRAFT)->count(),
            'categories' => Category::count(),
            'authors' => Author::count(),
            'orders' => Order::count(),
            'pending_orders' => Order::pending()->count(),
            'paid_orders' => Order::paid()->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}