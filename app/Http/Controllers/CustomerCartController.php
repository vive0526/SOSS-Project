<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CustomerCartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        if (!empty($cart)) {
            $invalidKeys = [];
            foreach ($cart as $key => $item) {
                if (empty($item['product_id']) || !Product::find($item['product_id'])) {
                    $invalidKeys[] = $key;
                }
            }

            if (!empty($invalidKeys)) {
                $request->session()->forget('cart');
                $cart = [];
            }
        }
        $totals = $this->calculateTotals($cart);

        return view('customer.cart.index', [
            'cart' => $cart,
            'subtotal' => $totals['subtotal'],
            'totalQuantity' => $totals['totalQuantity'],
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

        if ($product->stock_quantity < $data['quantity']) {
            return back()->withErrors([
                'quantity' => 'Not enough stock available for this product.',
            ])->withInput();
        }

        $cart = $request->session()->get('cart', []);
        $key = $this->buildKey($product->getKey(), $maintenanceYear);
        $existingQty = $cart[$key]['quantity'] ?? 0;
        $newQty = $existingQty + $data['quantity'];

        if ($product->stock_quantity < $newQty) {
            return back()->withErrors([
                'quantity' => 'You can only add up to ' . $product->stock_quantity . ' unit(s).',
            ])->withInput();
        }

        $unitPrice = $this->resolveUnitPrice($product, $maintenanceYear);

        $cart[$key] = [
            'key' => $key,
            'product_id' => $product->getKey(),
            'name' => $product->name,
            'price' => $unitPrice,
            'quantity' => $newQty,
            'maintenance_year' => $maintenanceYear,
            'image' => $product->image,
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

        if ($product->stock_quantity < $data['quantity']) {
            $message = 'Only ' . $product->stock_quantity . ' unit(s) available.';
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
            $totals = $this->calculateTotals($cart);

            return response()->json([
                'ok' => true,
                'itemKey' => $itemKey,
                'quantity' => (int) $data['quantity'],
                'itemSubtotal' => (float) $cart[$itemKey]['price'] * (int) $data['quantity'],
                'subtotal' => (float) $totals['subtotal'],
                'totalQuantity' => (int) $totals['totalQuantity'],
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

    private function calculateTotals(array $cart): array
    {
        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($cart as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
            $totalQuantity += (int) $item['quantity'];
        }

        return [
            'subtotal' => $subtotal,
            'totalQuantity' => $totalQuantity,
        ];
    }
}
