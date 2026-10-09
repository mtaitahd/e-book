<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    /**
     * Create a storefront customer account from the auth modal and sign the new
     * customer in straight away, so they land on the page they were trying to
     * reach instead of being asked to type the same password twice.
     *
     * The role and status are assigned here rather than read from the request,
     * so a submission can never provision an administrator or a suspended user.
     * Validation failures are handled by RegisterRequest, which answers JSON
     * for the modal and a redirect back with errors for the no-JS fallback.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim()->value(),
            'email' => $request->string('email')->trim()->lower()->value(),
            'phone' => $request->normalizedPhone(),
            'password' => $request->string('password')->value(),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('account.show'));
    }
}
