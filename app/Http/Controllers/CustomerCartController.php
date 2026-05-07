<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CustomerCartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        $priced = $this->priceCartFromDatabase($cart);

        if (!$priced['ok']) {
            $request->session()->forget('cart');
            $priced = [
                'cart' => [],
                'subtotal' => 0.0,
                'totalQuantity' => 0,
            ];
        }

        return view('customer.cart.index', [
            'cart' => $priced['cart'],
            'subtotal' => $priced['subtotal'],
            'totalQuantity' => $priced['totalQuantity'],
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|string|exists:products,product_id',
            'quantity' => 'required|integer|min:1',
            'maintenance_year' => 'nullable|integer|min:1|max:5',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $maintenanceYear = $data['maintenance_year'] ?? null;

        if (($product->product_type ?? 'normal') === 'cattle') {
            return back()->withErrors([
                'product_id' => 'This product must be purchased via Request Purchase (not cart/checkout).',
            ])->withInput();
        }

        if ((string) $product->category_id === '3') {
            if (!$maintenanceYear) {
                return back()->withErrors([
                    'maintenance_year' => 'Select a maintenance year for this product.',
                ])->withInput();
            }

            $prices = $product->maintenance_prices ?? [];
            if (!array_key_exists($maintenanceYear, $prices)) {
                return back()->withErrors([
                    'maintenance_year' => 'Selected maintenance year is not available.',
                ])->withInput();
            }
        } else {
            $maintenanceYear = null;
        }

        $availableStock = $product->availableStock();

        if ($availableStock < $data['quantity']) {
            return back()->withErrors([
                'quantity' => 'Only ' . $availableStock . ' unit(s) available.',
            ])->withInput();
        }

        $cart = $request->session()->get('cart', []);
        $key = $this->buildKey($product->getKey(), $maintenanceYear);
        $existingQty = $cart[$key]['quantity'] ?? 0;
        $newQty = $existingQty + $data['quantity'];

        if ($availableStock < $newQty) {
            return back()->withErrors([
                'quantity' => 'You can only add up to ' . $availableStock . ' unit(s).',
            ])->withInput();
        }

        $unitPrice = $this->resolveUnitPrice($product, $maintenanceYear);

        $cart[$key] = [
            'key' => $key,
            'product_id' => $product->getKey(),
            'quantity' => $newQty,
            'maintenance_year' => $maintenanceYear,
        ];

        $request->session()->put('cart', $cart);

        return redirect()
            ->route('customer.cart.index')
            ->with('success', 'Added to cart.');
    }

    public function update(Request $request, string $itemKey)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $request->session()->get('cart', []);
        if (!isset($cart[$itemKey])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Cart item not found.',
                ], 404);
            }

            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Cart item not found.']);
        }

        $item = $cart[$itemKey];
        $product = Product::find($item['product_id']);
        if (!$product) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Product is no longer available.',
                ], 404);
            }

            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Product is no longer available.']);
        }

        $availableStock = $product->availableStock();

        if ($availableStock < $data['quantity']) {
            $message = 'Only ' . $availableStock . ' unit(s) available.';
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'errors' => [
                        'quantity' => [$message],
                    ],
                ], 422);
            }

            return back()->withErrors([
                'quantity' => $message,
            ]);
        }

        $cart[$itemKey]['quantity'] = $data['quantity'];
        $request->session()->put('cart', $cart);

        if ($request->expectsJson()) {
            $priced = $this->priceCartFromDatabase($cart);
            if (!$priced['ok']) {
                return response()->json([
                    'ok' => false,
                    'message' => $priced['message'] ?? 'Unable to update cart.',
                ], 422);
            }

            $pricedItem = $priced['cart'][$itemKey] ?? null;
            if (!$pricedItem) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Cart item not found.',
                ], 404);
            }

            return response()->json([
                'ok' => true,
                'itemKey' => $itemKey,
                'quantity' => (int) $data['quantity'],
                'itemSubtotal' => (float) $pricedItem['price'] * (int) $data['quantity'],
                'subtotal' => (float) $priced['subtotal'],
                'totalQuantity' => (int) $priced['totalQuantity'],
            ]);
        }

        return redirect()->route('customer.cart.index')->with('success', 'Cart updated.');
    }

    public function remove(Request $request, string $itemKey)
    {
        $cart = $request->session()->get('cart', []);
        if (isset($cart[$itemKey])) {
            unset($cart[$itemKey]);
            $request->session()->put('cart', $cart);
        }

        return redirect()->route('customer.cart.index')->with('success', 'Item removed.');
    }

    private function buildKey(string $productId, ?int $maintenanceYear): string
    {
        return $productId . '-' . ($maintenanceYear ?? 0);
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

    /**
     * Price the cart using authoritative data from the database.
     *
     * @param array<string, array<string, mixed>> $cart
     * @return array{ok: bool, message?: string, cart?: array<string, array<string, mixed>>, subtotal?: float, totalQuantity?: int}
     */
    private function priceCartFromDatabase(array $cart): array
    {
        if (empty($cart)) {
            return [
                'ok' => true,
                'cart' => [],
                'subtotal' => 0.0,
                'totalQuantity' => 0,
            ];
        }

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

            $pricedCart[$key] = array_merge($item, [
                'key' => (string) ($item['key'] ?? $key),
                'product_id' => $product->getKey(),
                'name' => $product->name,
                'price' => $unitPrice,
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
                'image' => $product->image,
            ]);

            $subtotal += $unitPrice * $quantity;
            $totalQuantity += $quantity;
        }

        return [
            'ok' => true,
            'cart' => $pricedCart,
            'subtotal' => $subtotal,
            'totalQuantity' => $totalQuantity,
        ];
    }
}
