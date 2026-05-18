<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CompleteProfileNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $normalized = $this->normalizeMalaysiaPhone((string) $value);
                    if ($normalized === null) {
                        $fail('Please enter a valid Malaysia phone number (e.g. 012-3456789 or +6012-3456789).');
                    }
                },
            ],
            'password' => [
                'required',
                'confirmed',
                Rules\Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $normalizedPhone = $this->normalizeMalaysiaPhone((string) $request->phone);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer', // default role
            'phone' => $normalizedPhone,
        ]);

        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $e) {
            report($e);
            $request->session()->flash(
                'warning',
                'Your account was created, but we could not send the verification email right now. Please try again later or use "Resend verification email" once mail is configured.'
            );
        }

        Auth::login($user);

        $request->session()->forget('profile_prompt_dismissed');
        $request->session()->put('show_profile_completion_modal', true);

        if (Schema::hasTable('notifications')) {
            $user->notify(new CompleteProfileNotification($user->missingCheckoutProfileFields()));
        }

        return redirect()->route('verification.notice');
    }

    private function normalizeMalaysiaPhone(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Keep digits and '+' only.
        $raw = preg_replace('/[^0-9+]/', '', $raw) ?? $raw;

        // Convert 6012... -> +6012...
        if (str_starts_with($raw, '60')) {
            $raw = '+' . $raw;
        }

        // Convert 012... -> +6012...
        if (str_starts_with($raw, '0')) {
            $raw = '+60' . substr($raw, 1);
        }

        // Validate +60 + 1 + (8 or 9 digits)
        if (!preg_match('/^\\+601\\d{8,9}$/', $raw)) {
            return null;
        }

        return $raw;
    }
}
