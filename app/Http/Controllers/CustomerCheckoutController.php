<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCheckoutController extends Controller
{
    private const SHIPPING_FEE = 10.00;

    private const PAYMENT_METHODS = [
        'cash_on_delivery' => 'Cash on Delivery',
        'bank_transfer' => 'Bank Transfer',
        'fpx' => 'FPX',
    ];

    public function index(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->withErrors(['cart' => 'Your cart is empty.']);
        }

        $totals = $this->calculateTotals($cart);

        return view('customer.checkout.index', [
            'cart' => $cart,
            'subtotal' => $totals['subtotal'],
            'shippingFee' => self::SHIPPING_FEE,
            'total' => $totals['subtotal'] + self::SHIPPING_FEE,
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

        $productIds = collect($cart)->pluck('product_id')->unique()->all();
        $products = Product::whereIn('product_id', $productIds)->get()->keyBy('product_id');

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                return redirect()->route('customer.cart.index')
                    ->withErrors(['cart' => 'A product in your cart is no longer available.']);
            }

            if ($product->stock_quantity < $item['quantity']) {
                return redirect()->route('customer.cart.index')
                    ->withErrors(['cart' => 'Not enough stock for ' . $product->name . '.']);
            }
        }

        $totals = $this->calculateTotals($cart);

        $order = DB::transaction(function () use ($request, $data, $cart, $totals) {
            $order = Order::create([
                'user_id' => $request->user()->getKey(),
                'status' => 'pending',
                'shipment_status' => 'pending',
                'total_amount' => $totals['subtotal'] + self::SHIPPING_FEE,
                'shipping_fee' => self::SHIPPING_FEE,
                'payment_method' => $data['payment_method'],
                'shipping_name' => $data['shipping_name'],
                'shipping_phone' => $data['shipping_phone'],
                'shipping_address' => $data['shipping_address'],
                'shipping_city' => $data['shipping_city'],
                'shipping_state' => $data['shipping_state'],
                'shipping_postcode' => $data['shipping_postcode'],
                'shipping_country' => $data['shipping_country'],
            ]);

            foreach ($cart as $item) {
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

        $request->session()->forget('cart');

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
