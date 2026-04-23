<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Notifications\CompleteProfileNotification;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        // Admin gets dedicated admin profile page
        if ($request->user()->role === 'admin') {
            return view('admin.profile.edit');
        }

        // For Customer, we show their own profile edit page
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Display the user's password change form.
     */
    public function editPassword(Request $request)
    {
        // Admin already has a dedicated profile page with password management
        if ($request->user()->role === 'admin') {
            return Redirect::route('admin.profile.edit');
        }

        return view('profile.password', ['user' => $request->user()]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Validate profile fields (name, email, phone, shipping address)
        $isCustomer = ($user->role ?? null) === 'customer';

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
            'phone' => ($isCustomer ? 'required' : 'nullable') . '|string|max:20',
            'shipping_address' => ($isCustomer ? 'required' : 'nullable') . '|string|max:500',
            'shipping_city' => ($isCustomer ? 'required' : 'nullable') . '|string|max:120',
            'shipping_state' => ($isCustomer ? 'required' : 'nullable') . '|string|max:120',
            'shipping_postcode' => ($isCustomer ? 'required' : 'nullable') . '|string|max:30',
            'shipping_country' => ($isCustomer ? 'required' : 'nullable') . '|string|max:120',
        ]);

        // Update basic profile fields (name, email, phone, address)
        $user->fill($request->only([
            'name',
            'email',
            'phone',
            'shipping_address',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country',
        ]));

        // If email changed, reset verification (Breeze default behavior)
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Handle profile photo upload (optional)
        if ($request->hasFile('profile_photo')) {

            // Delete old photo if exists
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Store new photo
            $path = $request->file('profile_photo')
                            ->store('profile-photos', 'public');

             $user->profile_photo = $path;
         }

        $user->save();

        if ($isCustomer && $user->isCheckoutProfileComplete() && Schema::hasTable('notifications')) {
            $user->unreadNotifications()
                ->where('type', CompleteProfileNotification::class)
                ->update(['read_at' => now()]);

            $request->session()->forget([
                'show_profile_completion_modal',
                'profile_prompt_dismissed',
            ]);
        }

        return Redirect::back(fallback: route('profile.edit', absolute: false))
            ->with('status', 'Profile updated successfully!');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->new_password);
        $user->save();

        return Redirect::back(fallback: route('profile.edit', absolute: false))
            ->with('status', 'Password updated successfully!');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Log out the user
        Auth::logout();

        // Delete the user's account
        $user->delete();

        // Invalidate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
