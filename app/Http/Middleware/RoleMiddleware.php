<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, ...$roles)
    {
        if (! auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()
                ->guest(route('login'))
                ->with('warning', 'Please log in to access that page.');
        }

        if (! in_array(auth()->user()->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }

            $role = auth()->user()->role;
            $dashboard = match ($role) {
                'admin' => '/admin/dashboard',
                'staff' => '/staff/dashboard',
                'customer' => '/customer/dashboard',
                default => null,
            };

            if (! $dashboard) {
                abort(403);
            }

            return redirect($dashboard)
                ->with('warning', 'No access: your account does not have permission to view that page.');
        }

        return $next($request);
    }

}
