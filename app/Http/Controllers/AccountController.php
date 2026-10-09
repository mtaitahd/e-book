<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Display the authenticated customer's account area.
     */
    public function index(): View
    {
        $user = Auth::user();

        $recentOrders = $user->orders()
            ->withCount('items')
            ->latest()
            ->take(3)
            ->get();

        $recentPurchases = $user->purchases()
            ->with('book')
            ->latest('id')
            ->take(3)
            ->get();

        return view('account.show', compact('recentOrders', 'recentPurchases'));
    }
}