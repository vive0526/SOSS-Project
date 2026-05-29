<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Notifications\CompleteProfileNotification;
use App\Notifications\LowStockDigestNotification;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if (auth()->user()->status === 'inactive') {
            auth()->logout();

            return back()->withErrors([
                'email' => 'Your account has been deactivated, please contact the administrator.',
            ]);
        }

        $user = $request->user();
        if ($user && ($user->role ?? null) === 'customer') {
            $request->session()->forget('profile_prompt_dismissed');

            if (!$user->isCheckoutProfileComplete()) {
                $request->session()->put('show_profile_completion_modal', true);

                if (Schema::hasTable('notifications')) {
                    $exists = $user->unreadNotifications()
                        ->where('type', CompleteProfileNotification::class)
                        ->exists();

                    if (!$exists) {
                        $user->notify(new CompleteProfileNotification($user->missingCheckoutProfileFields()));
                    }
                }
            }
        }

        if ($user && in_array(($user->role ?? null), ['admin', 'staff'], true) && Schema::hasTable('notifications') && Schema::hasTable('products')) {
            $lowStockCount = Product::query()
                ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) > 0')
                ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) <= reorder_level')
                ->count();
            $outOfStockCount = Product::query()
                ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) <= 0')
                ->count();

            if ($lowStockCount > 0 || $outOfStockCount > 0) {
                $digestDate = now()->toDateString();
                $exists = $user->notifications()
                    ->where('type', LowStockDigestNotification::class)
                    ->where('data->digest_date', $digestDate)
                    ->exists();

                if (!$exists) {
                    $user->notify(new LowStockDigestNotification($lowStockCount, $outOfStockCount, $digestDate));
                }
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('welcome', [], 303);
    }
}
