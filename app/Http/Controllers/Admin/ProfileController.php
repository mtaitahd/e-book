<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the signed-in administrator's account settings.
     */
    public function edit(): View
    {
        return view('admin.profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update the administrator's own name, email, phone, password and photo.
     *
     * Only the currently signed-in account can be touched: the model is taken
     * from the authenticated user, never from the request, so this endpoint can
     * never be pointed at somebody else's account.
     */
    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $oldAvatar = $user->avatar;
        $avatarPath = $oldAvatar;
        $avatarUploaded = false;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $avatarUploaded = true;
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $request->normalizedPhone();

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->avatar = $avatarPath;
        $user->save();

        // The old file is only removed after the row is safely saved, so a
        // failed update never leaves the account pointing at a deleted photo.
        if ($avatarUploaded && $oldAvatar && $oldAvatar !== $avatarPath && Storage::disk('public')->exists($oldAvatar)) {
            Storage::disk('public')->delete($oldAvatar);
        }

        $message = 'Profile updated successfully.'.($avatarUploaded ? ' Photo updated.' : '');

        return redirect()
            ->route('admin.profile.edit')
            ->with('success', $message);
    }
}
