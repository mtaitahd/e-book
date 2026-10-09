<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

/**
 * Enforces that purchase entitlements are only ever accessed by the customer
 * who owns them. Ownership is checked against the authenticated user — a
 * frontend always presents hidden buttons, but the backend decides.
 */
class PurchasePolicy
{
    public function view(User $user, Purchase $purchase): bool
    {
        return $this->owns($user, $purchase);
    }

    public function download(User $user, Purchase $purchase): bool
    {
        return $this->owns($user, $purchase);
    }

    /**
     * Online reading (reader page + the authorized PDF content endpoint)
     * follows the exact same ownership rule as viewing/downloading.
     */
    public function read(User $user, Purchase $purchase): bool
    {
        return $this->owns($user, $purchase);
    }

    /**
     * The native HTML reader, its chapter content endpoint and its progress
     * writes. Same rule as read(): a customer may only ever open the chapters
     * of a book they personally bought.
     */
    public function readOnline(User $user, Purchase $purchase): bool
    {
        return $this->owns($user, $purchase);
    }

    /**
     * Bookmarks are private to their owner for the same reason.
     */
    public function bookmark(User $user, Purchase $purchase): bool
    {
        return $this->owns($user, $purchase);
    }

    /**
     * Reading-progress records are scoped to a purchase the current user
     * owns — a foreign user can never touch another customer's progress.
     */
    public function progress(User $user, Purchase $purchase): bool
    {
        return $this->owns($user, $purchase);
    }

    private function owns(User $user, Purchase $purchase): bool
    {
        return (int) $purchase->user_id === (int) $user->id;
    }
}
