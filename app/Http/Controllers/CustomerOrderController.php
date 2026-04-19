<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::where('user_id', $request->user()->getKey())
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('shipment_status')) {
            $query->where('shipment_status', $request->input('shipment_status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('order_number', 'like', '%' . $search . '%');
        }

        $orders = $query->get();
        $statusCounts = Order::where('user_id', $request->user()->getKey())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('customer.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'shipmentStatuses' => Order::SHIPMENT_STATUSES,
            'statusCounts' => $statusCounts,
            'filters' => $request->only(['status', 'shipment_status', 'search']),
        ]);
    }

    public function show(Order $order)
    {
        $this->authorizeCustomer($order);

        $order->load(['items.product', 'statusHistories.changedBy']);

        return view('customer.orders.show', [
            'order' => $order,
            'statuses' => Order::STATUSES,
            'shipmentStatuses' => Order::SHIPMENT_STATUSES,
        ]);
    }

    public function cancel(Request $request, Order $order)
    {
        $this->authorizeCustomer($order);

        if ($order->status === 'cancelled') {
            return back()->with('success', 'Order is already cancelled.');
        }

        if ($order->status !== 'pending' || $order->shipment_status !== 'pending') {
            return back()->withErrors(['status' => 'This order can no longer be cancelled.']);
        }

        $data = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $shouldReturnStock = $order->payment_verified_at && $order->shipment_status === 'pending';
        $order->load('items.product');

        DB::transaction(function () use ($order, $data, $shouldReturnStock) {
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancelled_reason = $data['cancel_reason'];
            $order->save();

            if ($shouldReturnStock) {
                foreach ($order->items as $item) {
                    if (!$item->product) {
                        continue;
                    }

                    $product = $item->product;
                    $previousStock = $product->stock_quantity;
                    $newStock = $previousStock + $item->quantity;

                    $product->stock_quantity = $newStock;
                    $product->save();

                    InventoryMovement::create([
                        'product_id' => $product->getKey(),
                        'user_id' => auth()->id(),
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reason' => 'Order ' . $order->order_number . ' cancelled by customer.',
                    ]);
                }
            }
        });

        $note = 'Cancelled by customer: ' . $data['cancel_reason'];
        if ($shouldReturnStock) {
            $note .= ' Stock returned.';
        }

        $this->logStatus($order, 'cancelled', $note);

        return back()->with('success', 'Order cancelled.');
    }

    private function authorizeCustomer(Order $order): void
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function logStatus(Order $order, string $status, ?string $note = null): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'status' => $status,
            'note' => $note,
            'changed_by' => auth()->id(),
        ]);
    }
}
