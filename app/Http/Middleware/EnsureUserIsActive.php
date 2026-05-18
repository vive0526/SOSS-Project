<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Block inactive users from protected customer actions (cart/checkout/etc),
     * even if they are already logged in.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || ($user->status ?? 'active') !== 'inactive') {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();

        $message = match (true) {
            str_starts_with($routeName, 'customer.cart.') => 'Your account is inactive, so items cannot be added to the cart.',
            str_starts_with($routeName, 'customer.checkout.') => 'Your account is inactive, so checkout is disabled.',
            default => 'Your account is inactive. Please contact support if you think this is a mistake.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 403);
        }

        return back()->with('warning', $message);
    }
}

