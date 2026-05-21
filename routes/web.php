<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\CustomerNotificationController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\OperatingUnitController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerCartController;
use App\Http\Controllers\CustomerCheckoutController;
use App\Http\Controllers\CustomerDiscountController;
use App\Http\Controllers\CustomerStripeCheckoutController;
use App\Http\Controllers\CustomerCattleRequestController;
use App\Http\Controllers\CattleRequestController;
use App\Http\Controllers\CustomerUpdateController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\StripeRefundController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\AdminShippingTaxSettingsController;
use App\Models\Category;
use App\Models\Product;

/*
|--------------------------------------------------------------------------|
| Public                                                                   |
|--------------------------------------------------------------------------|
*/
Route::get('/', function () {
    $featuredProducts = Product::with('category')
        ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) > 0')
        ->orderByRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) desc')
        ->orderByDesc('updated_at')
        ->take(4)
        ->get();

    $heroProduct = $featuredProducts->first();

    return view('welcome', compact('featuredProducts', 'heroProduct'));
})->name('welcome');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

/*
|--------------------------------------------------------------------------|
| Dashboards                                                              |
|--------------------------------------------------------------------------|
*/
Route::get('/admin/dashboard', function () {
    return view('admin.dashboard');
})->middleware(['auth', 'role:admin']);

Route::get('/staff/dashboard', function () {
    return view('staff.dashboard');
})->middleware(['auth', 'role:staff,admin']);

Route::get('/customer/dashboard', function () {
    $featuredProducts = Product::with('category')
        ->orderByDesc('created_at')
        ->take(8)
        ->get();
    $collections = Category::withCount('products')
        ->orderByDesc('products_count')
        ->orderBy('name')
        ->take(6)
        ->get();
    $totalProducts = Product::count();
    $categoryCount = Category::count();
    $inStockCount = Product::whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) > 0')->count();

    return view('customer.dashboard', compact(
        'featuredProducts',
        'collections',
        'totalProducts',
        'categoryCount',
        'inStockCount'
    ));
})->middleware(['auth', 'verified', 'role:customer,staff,admin']);

/*
|--------------------------------------------------------------------------|
| Admin Profile (IMPORTANT)                                               |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/profile', function () {
        return view('admin.profile.edit');
    })->name('admin.profile.edit');

    Route::get('/admin/notifications/create', [AdminNotificationController::class, 'create'])
        ->name('admin.notifications.create');
    Route::post('/admin/notifications', [AdminNotificationController::class, 'store'])
        ->name('admin.notifications.store');

    Route::get('/admin/settings/shipping-tax', [AdminShippingTaxSettingsController::class, 'edit'])
        ->name('admin.settings.shipping_tax.edit');
    Route::put('/admin/settings/shipping-tax', [AdminShippingTaxSettingsController::class, 'update'])
        ->name('admin.settings.shipping_tax.update');
});

/*
|--------------------------------------------------------------------------|
| Shared Profile Actions (ALL ROLES)                                      |
|--------------------------------------------------------------------------|
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::get('/profile/password', [ProfileController::class, 'editPassword'])
        ->name('profile.password.edit');

    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------|
| User Management (Admin Only)                                            |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
});

/*
|--------------------------------------------------------------------------|
| User Management (Staff Only)                                            |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    });

/*
|--------------------------------------------------------------------------|
| General Settings - Regions                                               |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/regions', [RegionController::class, 'index'])->name('regions.index');
    Route::get('/regions/create', [RegionController::class, 'create'])->name('regions.create');
    Route::post('/regions', [RegionController::class, 'store'])->name('regions.store');
    Route::get('/regions/{region}/edit', [RegionController::class, 'edit'])->name('regions.edit');
    Route::put('/regions/{region}', [RegionController::class, 'update'])->name('regions.update');
    Route::patch('/regions/{region}/deactivate', [RegionController::class, 'deactivate'])->name('regions.deactivate');
    Route::patch('/regions/{region}/activate', [RegionController::class, 'activate'])->name('regions.activate');
});

/*
|--------------------------------------------------------------------------|
| General Settings - Operating Units                                       |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/operating-units', [OperatingUnitController::class, 'index'])->name('operating-units.index');
    Route::get('/operating-units/create', [OperatingUnitController::class, 'create'])->name('operating-units.create');
    Route::post('/operating-units', [OperatingUnitController::class, 'store'])->name('operating-units.store');
    Route::get('/operating-units/{operatingUnit}/edit', [OperatingUnitController::class, 'edit'])->name('operating-units.edit');
    Route::put('/operating-units/{operatingUnit}', [OperatingUnitController::class, 'update'])->name('operating-units.update');
    Route::patch('/operating-units/{operatingUnit}/deactivate', [OperatingUnitController::class, 'deactivate'])->name('operating-units.deactivate');
    Route::patch('/operating-units/{operatingUnit}/activate', [OperatingUnitController::class, 'activate'])->name('operating-units.activate');
});

/*
|--------------------------------------------------------------------------|
| General Settings - Companies                                             |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::patch('/companies/{company}/deactivate', [CompanyController::class, 'deactivate'])->name('companies.deactivate');
    Route::patch('/companies/{company}/activate', [CompanyController::class, 'activate'])->name('companies.activate');
});

/*
|--------------------------------------------------------------------------|
| General Settings - Codes                                                 |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/codes', [CodeController::class, 'index'])->name('codes.index');
    Route::get('/codes/create', [CodeController::class, 'create'])->name('codes.create');
    Route::post('/codes', [CodeController::class, 'store'])->name('codes.store');
    Route::get('/codes/{code}/edit', [CodeController::class, 'edit'])->name('codes.edit');
    Route::put('/codes/{code}', [CodeController::class, 'update'])->name('codes.update');
    Route::patch('/codes/{code}/deactivate', [CodeController::class, 'deactivate'])->name('codes.deactivate');
    Route::patch('/codes/{code}/activate', [CodeController::class, 'activate'])->name('codes.activate');
});

/*
|--------------------------------------------------------------------------|
| Product Inventory (Admin & Staff)                                        |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/products/inventory', [InventoryController::class, 'index'])
        ->name('products.inventory');
});

/*
|--------------------------------------------------------------------------|
| Order Management (Admin & Staff)                                         |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::patch('/orders/{order}/verify-payment', [OrderController::class, 'verifyPayment'])->name('orders.verify-payment');
    Route::patch('/orders/{order}/shipment', [OrderController::class, 'updateShipment'])->name('orders.update-shipment');
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::patch('/orders/{order}/reopen', [OrderController::class, 'reopen'])->name('orders.reopen');

    Route::get('/cattle-requests', [CattleRequestController::class, 'index'])->name('cattle-requests.index');
    Route::get('/cattle-requests/{cattleRequest}', [CattleRequestController::class, 'show'])->name('cattle-requests.show');
    Route::patch('/cattle-requests/{cattleRequest}/approve', [CattleRequestController::class, 'approve'])->name('cattle-requests.approve');
    Route::patch('/cattle-requests/{cattleRequest}/reject', [CattleRequestController::class, 'reject'])->name('cattle-requests.reject');
    Route::patch('/cattle-requests/{cattleRequest}/complete', [CattleRequestController::class, 'complete'])->name('cattle-requests.complete');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::patch('/orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
    Route::post('/orders/{order}/refund/stripe', [StripeRefundController::class, 'store'])
        ->name('orders.refund.stripe');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/reports/orders/summary', [OrderController::class, 'reportSummary'])
        ->name('orders.reports.summary');
});

/*
|--------------------------------------------------------------------------|
| Product Management                                                       |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::resource('products', ProductController::class);  // CRUD operations
});

/*
|--------------------------------------------------------------------------|
| Product Categories (Admin & Staff)                                       |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

/*
|--------------------------------------------------------------------------|
| Inventory Management (Admin Only)                                        |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {
        Route::get('/products/{product}/edit', [InventoryController::class, 'edit'])
            ->name('edit');
        Route::put('/products/{product}', [InventoryController::class, 'update'])
            ->name('update');
        Route::get('/history', [InventoryController::class, 'history'])
            ->name('history');
    });

/*
|--------------------------------------------------------------------------|
| Inventory Actions (Admin & Staff)                                        |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'role:admin,staff'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {
        Route::get('/products/{product}/adjust', [InventoryController::class, 'adjust'])
            ->name('adjust');
        Route::post('/products/{product}/adjust', [InventoryController::class, 'storeAdjustment'])
            ->name('adjust.store');
        Route::get('/reports/levels', [InventoryController::class, 'reportLevels'])
            ->name('reports.levels');
        Route::get('/reports/low-stock', [InventoryController::class, 'reportLowStock'])
            ->name('reports.low-stock');
        Route::get('/reports/movements', [InventoryController::class, 'reportMovements'])
            ->name('reports.movements');
    });

/*
|--------------------------------------------------------------------------|
| Customer Catalog (Customer Only)                                         |
|--------------------------------------------------------------------------|
*/
Route::middleware(['auth', 'verified', 'role:customer'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/addresses', [CustomerAddressController::class, 'index'])
            ->name('addresses.index');
        Route::get('/addresses/create', [CustomerAddressController::class, 'create'])
            ->name('addresses.create');
        Route::post('/addresses', [CustomerAddressController::class, 'store'])
            ->name('addresses.store');
        Route::get('/addresses/{address}/edit', [CustomerAddressController::class, 'edit'])
            ->name('addresses.edit');
        Route::put('/addresses/{address}', [CustomerAddressController::class, 'update'])
            ->name('addresses.update');
        Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy'])
            ->name('addresses.destroy');
        Route::put('/addresses/{address}/default', [CustomerAddressController::class, 'setDefault'])
            ->name('addresses.default');

        Route::get('/updates', [CustomerUpdateController::class, 'index'])
            ->name('updates.index');

        Route::get('/notifications', [CustomerNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('/notifications/read-all', [CustomerNotificationController::class, 'readAll'])
            ->name('notifications.read-all');
        Route::post('/notifications/{notificationId}/read', [CustomerNotificationController::class, 'read'])
            ->name('notifications.read');
        Route::post('/profile-prompt/dismiss', [CustomerNotificationController::class, 'dismissProfilePrompt'])
            ->name('profile-prompt.dismiss');

        Route::get('/cart', [CustomerCartController::class, 'index'])
            ->middleware('active_user')
            ->name('cart.index');
        Route::post('/cart/add', [CustomerCartController::class, 'add'])
            ->middleware('active_user')
            ->name('cart.add');
        Route::post('/cart/{itemKey}/update', [CustomerCartController::class, 'update'])
            ->middleware('active_user')
            ->name('cart.update');
        Route::post('/cart/{itemKey}/remove', [CustomerCartController::class, 'remove'])
            ->middleware('active_user')
            ->name('cart.remove');

        Route::get('/checkout', [CustomerCheckoutController::class, 'index'])
            ->middleware('active_user')
            ->name('checkout.index');
        Route::post('/checkout', [CustomerCheckoutController::class, 'place'])
            ->middleware('active_user')
            ->name('checkout.place');
        Route::get('/checkout/processing/{order}', [CustomerCheckoutController::class, 'processing'])
            ->middleware('active_user')
            ->name('checkout.processing');
        Route::get('/checkout/stripe/success', [CustomerStripeCheckoutController::class, 'success'])
            ->middleware('active_user')
            ->name('checkout.stripe.success');
        Route::get('/checkout/stripe/cancel/{order}', [CustomerStripeCheckoutController::class, 'cancel'])
            ->middleware('active_user')
            ->name('checkout.stripe.cancel');
        Route::get('/checkout/stripe/{order}', [CustomerStripeCheckoutController::class, 'start'])
            ->middleware('active_user')
            ->name('checkout.stripe.start');

        Route::get('/discounts', [CustomerDiscountController::class, 'index'])
            ->middleware('active_user')
            ->name('discounts.index');
        Route::post('/discounts/claim/{coupon}', [CustomerDiscountController::class, 'claim'])
            ->middleware('active_user')
            ->name('discounts.claim');

        Route::get('/orders', [CustomerOrderController::class, 'index'])
            ->name('orders.index');
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])
            ->name('orders.show');
        Route::patch('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])
            ->name('orders.cancel');

        Route::get('/products', [CustomerProductController::class, 'index'])
            ->name('products.index');
        Route::get('/products/{product}/stock', [CustomerProductController::class, 'stock'])
            ->name('products.stock');
        Route::get('/products/{product}', [CustomerProductController::class, 'show'])
            ->name('products.show');

        Route::get('/cattle-requests', [CustomerCattleRequestController::class, 'index'])
            ->name('cattle-requests.index');
        Route::get('/cattle-requests/{product}/create', [CustomerCattleRequestController::class, 'create'])
            ->name('cattle-requests.create');
        Route::get('/cattle-requests/{cattleRequest}', [CustomerCattleRequestController::class, 'show'])
            ->name('cattle-requests.show');
        Route::post('/cattle-requests', [CustomerCattleRequestController::class, 'store'])
            ->name('cattle-requests.store');
    });

/*
|--------------------------------------------------------------------------|
| Dashboard Redirection                                                     |
|--------------------------------------------------------------------------|
*/
Route::middleware('auth')->get('/dashboard', function () {
    $role = auth()->user()->role;

    return match ($role) {
        'admin' => redirect('/admin/dashboard'),
        'staff' => redirect('/staff/dashboard'),
        'customer' => redirect('/customer/dashboard'),
        default => abort(403),
    };
})->name('dashboard');

require __DIR__.'/auth.php';
