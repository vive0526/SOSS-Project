<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.storefront', function ($view) {
            $navCategories = Cache::remember(
                'storefront.nav_categories.v1',
                now()->addMinutes(30),
                fn () => Category::withCount('products')
                    ->orderByDesc('products_count')
                    ->orderBy('name')
                    ->take(6)
                    ->get()
            );

            $cart = request()->session()->get('cart', []);
            $cartCount = 0;
            foreach ($cart as $item) {
                $cartCount += (int) ($item['quantity'] ?? 0);
            }

            $unreadCount = 0;
            $previewNotifications = collect();
            $user = auth()->user();
            if ($user && Schema::hasTable('notifications')) {
                $unreadCount = $user->unreadNotifications()->count();
                $previewNotifications = $user->notifications()->latest()->take(5)->get();
            }

            $view->with([
                'storefrontNavCategories' => $navCategories,
                'storefrontCartCount' => $cartCount,
                'storefrontUnreadNotificationsCount' => $unreadCount,
                'storefrontNotificationsPreview' => $previewNotifications,
            ]);
        });

        View::composer('layouts.customer', function ($view) {
            $user = auth()->user();
            $unreadCount = 0;

            if ($user && Schema::hasTable('notifications')) {
                $unreadCount = $user->unreadNotifications()->count();
            }

            $view->with([
                'customerUnreadNotificationsCount' => $unreadCount,
            ]);
        });
    }
}
