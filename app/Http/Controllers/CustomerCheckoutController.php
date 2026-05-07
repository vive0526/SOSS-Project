<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Notifications\CompleteProfileNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerCheckoutController extends Controller
{
    private const SHIPPING_FEE = 10.00;

    private const PAYMENT_METHODS = [
        'cash_on_delivery' => 'Cash on Delivery',
        'stripe_card' => 'Card (Stripe)',
        'stripe_fpx' => 'FPX (Stripe)',
    ];

    public function index(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Your cart is empty.']);
        }

        $profileRedirect = $this->guardCheckoutProfile($request);
        if ($profileRedirect) {
            return $profileRedirect;
        }

        $priced = $this->priceCartFromDatabase($cart);
        if (!$priced['ok']) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => $priced['message']]);
        }

        return view('customer.checkout.index', [
            'cart' => $priced['cart'],
            'subtotal' => $priced['subtotal'],
            'shippingFee' => self::SHIPPING_FEE,
            'total' => $priced['subtotal'] + self::SHIPPING_FEE,
            'paymentMethods' => self::PAYMENT_METHODS,
            'customer' => $request->user(),
        ]);
    }

    public function place(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Your cart is empty.']);
        }

        $profileRedirect = $this->guardCheckoutProfile($request);
        if ($profileRedirect) {
            return $profileRedirect;
        }

        $data = $request->validate([
            'shipping_name' => 'required|string|max:120',
            'shipping_phone' => 'required|string|max:60',
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:120',
            'shipping_state' => 'required|string|max:120',
            'shipping_postcode' => 'required|string|max:30',
            'shipping_country' => 'required|string|max:120',
            'payment_method' => 'required|in:' . implode(',', array_keys(self::PAYMENT_METHODS)),
        ]);

        $priced = $this->priceCartFromDatabase($cart);
        if (!$priced['ok']) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => $priced['message']]);
        }

        $isStripe = in_array($data['payment_method'], ['stripe_card', 'stripe_fpx'], true);
        $reservedAt = now();
        $reservationExpiresAt = $isStripe ? now()->addMinutes(5) : null;

        try {
            $order = DB::transaction(function () use ($request, $data, $priced, $reservedAt, $reservationExpiresAt, $isStripe) {
                $productIds = collect($priced['cart'])->pluck('product_id')->filter()->unique()->values()->all();
                $products = Product::query()
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                foreach ($priced['cart'] as $item) {
                    $productId = (string) ($item['product_id'] ?? '');
                    /** @var \App\Models\Product|null $product */
                    $product = $products->get($productId);
                    if (!$product) {
                        throw new \RuntimeException('A product in your cart is no longer available.');
                    }

                    $requestedQty = (int) ($item['quantity'] ?? 0);
                    if ($requestedQty < 1) {
                        throw new \RuntimeException('Your cart has an invalid quantity. Please update your cart and try again.');
                    }

                    $reservedQty = (int) ($product->reserved_quantity ?? 0);
                    $available = (int) $product->stock_quantity - $reservedQty;
                    if ($available < $requestedQty) {
                        throw new InsufficientStockException(
                            (string) $product->getKey(),
                            'Sorry for the inconvenience — the last item was just reserved by another customer. Please review the latest stock before trying again.'
                        );
                    }
                }

                foreach ($priced['cart'] as $item) {
                    $productId = (string) ($item['product_id'] ?? '');
                    /** @var \App\Models\Product $product */
                    $product = $products->get($productId);

                    $requestedQty = (int) ($item['quantity'] ?? 0);
                    $product->reserved_quantity = (int) ($product->reserved_quantity ?? 0) + $requestedQty;
                    $product->save();
                }

                $order = Order::create([
                    'user_id' => $request->user()->getKey(),
                    'status' => 'pending',
                    'shipment_status' => 'pending',
                    'total_amount' => $priced['subtotal'] + self::SHIPPING_FEE,
                    'shipping_fee' => self::SHIPPING_FEE,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $isStripe ? 'pending' : 'unpaid',
                    'shipping_name' => $data['shipping_name'],
                    'shipping_phone' => $data['shipping_phone'],
                    'shipping_address' => $data['shipping_address'],
                    'shipping_city' => $data['shipping_city'],
                    'shipping_state' => $data['shipping_state'],
                    'shipping_postcode' => $data['shipping_postcode'],
                    'shipping_country' => $data['shipping_country'],
                    'reserved_at' => $reservedAt,
                    'reservation_expires_at' => $reservationExpiresAt,
                ]);

                foreach ($priced['cart'] as $item) {
                    OrderItem::create([
                        'order_id' => $order->getKey(),
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'maintenance_year' => $item['maintenance_year'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'total_price' => (float) $item['price'] * (int) $item['quantity'],
                    ]);
                }

                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'status' => 'pending',
                    'note' => 'Order placed by customer.',
                    'changed_by' => $request->user()->getKey(),
                ]);

                return $order;
            });
        } catch (\Throwable $e) {
            if ($e instanceof InsufficientStockException) {
                return redirect()
                    ->route('customer.products.show', $e->productId)
                    ->with('warning', $e->getMessage());
            }

            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => $e->getMessage() ?: 'Unable to place order. Please try again.']);
        }

        $request->session()->forget('cart');

        if (in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true)) {
            return redirect()->route('customer.checkout.stripe.start', $order);
        }

        return redirect()->route('customer.checkout.processing', $order)
            ->with('success', 'Order placed successfully.');
    }

    public function processing(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        return view('customer.checkout.processing', [
            'order' => $order,
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Price the cart using authoritative data from the database.
     *
     * @param array<string, array<string, mixed>> $cart
     * @return array{ok: bool, message?: string, cart?: array<string, array<string, mixed>>, products?: \Illuminate\Support\Collection, subtotal?: float, totalQuantity?: int}
     */
    private function priceCartFromDatabase(array $cart): array
    {
        $productIds = collect($cart)->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

        $pricedCart = [];
        $subtotal = 0.0;
        $totalQuantity = 0;

        foreach ($cart as $key => $item) {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '' || !$products->has($productId)) {
                return [
                    'ok' => false,
                    'message' => 'A product in your cart is no longer available.',
                ];
            }

            /** @var \App\Models\Product $product */
            $product = $products->get($productId);

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                return [
                    'ok' => false,
                    'message' => 'Your cart has an invalid quantity. Please update your cart and try again.',
                ];
            }

            $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
                ? (int) $item['maintenance_year']
                : null;
            if ($maintenanceYear === 0) {
                $maintenanceYear = null;
            }

            if ((string) $product->category_id === '3') {
                if (!$maintenanceYear) {
                    return [
                        'ok' => false,
                        'message' => 'Select a maintenance year for your maintenance product(s).',
                    ];
                }

                $prices = $product->maintenance_prices ?? [];
                if (!array_key_exists($maintenanceYear, $prices)) {
                    return [
                        'ok' => false,
                        'message' => 'Selected maintenance year is not available for one of your products.',
                    ];
                }
            } else {
                $maintenanceYear = null;
            }

            $unitPrice = $this->resolveUnitPrice($product, $maintenanceYear);

            $item = array_merge($item, [
                'product_id' => $product->getKey(),
                'name' => $product->name,
                'price' => $unitPrice,
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
                'image' => $product->image,
            ]);

            $pricedCart[$key] = $item;
            $subtotal += $unitPrice * $quantity;
            $totalQuantity += $quantity;
        }

        return [
            'ok' => true,
            'cart' => $pricedCart,
            'products' => $products,
            'subtotal' => $subtotal,
            'totalQuantity' => $totalQuantity,
        ];
    }

    private function resolveUnitPrice(Product $product, ?int $maintenanceYear): float
    {
        if ((string) $product->category_id === '3' && $maintenanceYear) {
            $prices = $product->maintenance_prices ?? [];
            $price = $prices[$maintenanceYear] ?? null;
            if ($price !== null) {
                return (float) $price;
            }
        }

        return (float) $product->price;
    }

    private function guardCheckoutProfile(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->isCheckoutProfileComplete()) {
            return null;
        }

        $request->session()->put('show_profile_completion_modal', true);

        if (Schema::hasTable('notifications')) {
            $exists = $user->unreadNotifications()
                ->where('type', CompleteProfileNotification::class)
                ->exists();

            if (!$exists) {
                $user->notify(new CompleteProfileNotification($user->missingCheckoutProfileFields()));
            }
        }

        return redirect()
            ->route('profile.edit')
            ->withErrors([
                'profile' => 'Please update your phone number and shipping address before checkout.',
            ]);
    }
}
