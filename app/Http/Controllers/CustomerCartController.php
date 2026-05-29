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

        $removedInvalidCount = (int) ($priced['removedInvalidCount'] ?? 0);
        $normalizedCount = (int) ($priced['normalizedCount'] ?? 0);
        if (($removedInvalidCount + $normalizedCount) > 0) {
            $request->session()->put('cart', $priced['sanitizedCart'] ?? []);
            if ($removedInvalidCount > 0) {
                $request->session()->flash('warning', "We removed {$removedInvalidCount} item(s) that are no longer available or invalid.");
            } elseif ($normalizedCount > 0) {
                $request->session()->flash('warning', 'We updated your cart to match the latest product information.');
            }
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

        $product = Product::query()
            ->where('product_id', $data['product_id'])
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return back()->withErrors([
                'product_id' => 'This product is no longer available.',
            ])->withInput();
        }
        $maintenanceYear = $data['maintenance_year'] ?? null;

        if (($product->product_type ?? 'normal') === 'cattle') {
            return back()->withErrors([
                'product_id' => 'This product must be purchased via Request Purchase (not cart/checkout).',
            ])->withInput();
        }

        if ((bool) ($product->requires_maintenance ?? false)) {
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
        if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
            $availableStock = min($availableStock, $product->availableMaintenanceStock((int) $maintenanceYear));
        }

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
        $product = Product::query()
            ->where('product_id', (string) ($item['product_id'] ?? ''))
            ->where('is_active', true)
            ->first();
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
        $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
            ? (int) $item['maintenance_year']
            : null;
        if ($maintenanceYear === 0) {
            $maintenanceYear = null;
        }
        if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
            $availableStock = min($availableStock, $product->availableMaintenanceStock((int) $maintenanceYear));
        }

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
            $removedInvalidCount = (int) ($priced['removedInvalidCount'] ?? 0);
            $normalizedCount = (int) ($priced['normalizedCount'] ?? 0);
            if (($removedInvalidCount + $normalizedCount) > 0) {
                $request->session()->put('cart', $priced['sanitizedCart'] ?? []);
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
        if ((bool) ($product->requires_maintenance ?? false) && $maintenanceYear) {
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
     * @return array{ok: bool, cart: array<string, array<string, mixed>>, sanitizedCart: array<string, array<string, mixed>>, subtotal: float, totalQuantity: int, removedInvalidCount?: int, normalizedCount?: int}
     */
    private function priceCartFromDatabase(array $cart): array
    {
        if (empty($cart)) {
            return [
                'ok' => true,
                'cart' => [],
                'sanitizedCart' => [],
                'subtotal' => 0.0,
                'totalQuantity' => 0,
            ];
        }

        $productIds = collect($cart)->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('product_id');

        $pricedCart = [];
        $sanitizedCart = [];
        $subtotal = 0.0;
        $totalQuantity = 0;
        $removedInvalidCount = 0;
        $normalizedCount = 0;

        foreach ($cart as $key => $item) {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '' || !$products->has($productId)) {
                $removedInvalidCount++;
                continue;
            }

            /** @var \App\Models\Product $product */
            $product = $products->get($productId);

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                $removedInvalidCount++;
                continue;
            }

            $maintenanceYear = isset($item['maintenance_year']) && $item['maintenance_year'] !== null
                ? (int) $item['maintenance_year']
                : null;
            if ($maintenanceYear === 0) {
                $maintenanceYear = null;
            }

            if ((bool) ($product->requires_maintenance ?? false)) {
                if (!$maintenanceYear) {
                    $removedInvalidCount++;
                    continue;
                }

                $prices = $product->maintenance_prices ?? [];
                if (!array_key_exists($maintenanceYear, $prices)) {
                    $removedInvalidCount++;
                    continue;
                }
            } else {
                $maintenanceYear = null;
            }

            $unitPrice = $this->resolveUnitPrice($product, $maintenanceYear);

            $resolvedKey = $this->buildKey((string) $product->getKey(), $maintenanceYear);
            if ($resolvedKey !== (string) $key) {
                $normalizedCount++;
            }

            $pricedCart[$resolvedKey] = array_merge($item, [
                'key' => $resolvedKey,
                'product_id' => $product->getKey(),
                'name' => $product->name,
                'price' => $unitPrice,
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
                'image' => $product->primaryImagePath(),
            ]);

            $sanitizedCart[$resolvedKey] = [
                'key' => $resolvedKey,
                'product_id' => (string) $product->getKey(),
                'quantity' => $quantity,
                'maintenance_year' => $maintenanceYear,
            ];

            $subtotal += $unitPrice * $quantity;
            $totalQuantity += $quantity;
        }

        return [
            'ok' => true,
            'cart' => $pricedCart,
            'sanitizedCart' => $sanitizedCart,
            'subtotal' => $subtotal,
            'totalQuantity' => $totalQuantity,
            'removedInvalidCount' => $removedInvalidCount,
            'normalizedCount' => $normalizedCount,
        ];
    }
}
