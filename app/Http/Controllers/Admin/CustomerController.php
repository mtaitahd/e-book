<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * List the store's customers (never administrators) with their order and
     * purchase footprint.
     */
    public function index(): View
    {
        return view('admin.customers.index', [
            'customers' => User::withCount(['orders', 'purchases'])
                ->where('role', User::ROLE_CUSTOMER)
                ->orderByDesc('created_at')
                ->paginate(15),
        ]);
    }

    /**
     * Permanently delete a customer account.
     *
     * A customer with any order row is refused: orders and the purchases that
     * settle them resolve through the customer, and their payment attempts can
     * still be in flight, so deleting the account would erase or orphan real
     * records. Only throwaway registrations that never engaged can be removed.
     */
    public function destroy(User $customer): RedirectResponse
    {
        if ($customer->isAdmin()) {
            session()->flash('error', 'Administrator accounts cannot be deleted from the customers list.');

            return redirect()->route('admin.customers.index');
        }

        $orders = $customer->orders()->count();
        $purchases = $customer->purchases()->count();

        if ($orders > 0 || $purchases > 0) {
            session()->flash('error', 'This customer cannot be deleted because their account has '
                .$orders.' order'.($orders === 1 ? '' : 's')
                .'. Removing it would erase the sales history those records point at.');

            return redirect()->route('admin.customers.index');
        }

        $customer->delete();

        session()->flash('success', 'Customer deleted successfully.');

        return redirect()->route('admin.customers.index');
    }
}