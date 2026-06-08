<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderStateEngine;
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

        $order->load(['items.product', 'items.review', 'statusHistories.changedBy', 'refunds', 'returnRequests.statusHistories.changedBy']);

        return view('customer.orders.show', [
            'order' => $order,
            'statuses' => Order::STATUSES,
            'shipmentStatuses' => Order::SHIPMENT_STATUSES,
        ]);
    }

    public function cancel(Request $request, Order $order, OrderStateEngine $orderStateEngine)
    {
        $this->authorizeCustomer($order);

        $data = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        try {
            $didCancel = false;

            DB::transaction(function () use ($order, $data, $orderStateEngine, &$didCancel) {
                /** @var \App\Models\Order|null $lockedOrder */
                $lockedOrder = Order::query()
                    ->whereKey($order->getKey())
                    ->lockForUpdate()
                    ->first();

                if (!$lockedOrder) {
                    throw new \RuntimeException('Order not found.');
                }

                if ($lockedOrder->user_id !== auth()->id()) {
                    abort(403);
                }

                if ($lockedOrder->status === 'cancelled') {
                    return;
                }

                if ($lockedOrder->status !== 'pending' || $lockedOrder->shipment_status !== 'pending') {
                    throw new \DomainException('This order can no longer be cancelled.');
                }

                $lockedOrder->load('items.product');

                $shouldReturnStock = $lockedOrder->payment_status === 'paid' && $lockedOrder->shipment_status === 'pending';
                $shouldReleaseReservation = $lockedOrder->payment_status !== 'paid' && $lockedOrder->reserved_at !== null;

                $note = 'Cancelled by customer: ' . $data['cancel_reason'];
                if ($shouldReturnStock) {
                    $note .= ' Stock returned.';
                }
                if ($shouldReleaseReservation) {
                    $note .= ' Stock reservation released.';
                }

                // Validate cancellation rules before touching stock/reservations.
                $orderStateEngine->cancelOrderLocked(
                    $lockedOrder,
                    $data['cancel_reason'],
                    $note,
                    auth()->id()
                );

                $productIds = collect($lockedOrder->items)->pluck('product_id')->filter()->unique()->values()->all();
                $products = Product::query()
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                if ($shouldReturnStock) {
                    foreach ($lockedOrder->items as $item) {
                        $productId = (string) ($item->product_id ?? '');
                        /** @var \App\Models\Product|null $product */
                        $product = $productId !== '' ? $products->get($productId) : null;

                        if (!$product && $item->product) {
                            $product = $item->product;
                        }

                        if (!$product) {
                            continue;
                        }

                        $previousStock = (int) $product->stock_quantity;
                        $newStock = $previousStock + (int) $item->quantity;

                        $product->stock_quantity = $newStock;

                        $maintenanceYear = $item->maintenance_year !== null ? (int) $item->maintenance_year : null;
                        if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                            $stocks = $product->maintenance_stocks ?? [];
                            $current = (int) ($stocks[$maintenanceYear] ?? $stocks[(string) $maintenanceYear] ?? 0);
                            $stocks[$maintenanceYear] = $current + (int) $item->quantity;
                            $product->maintenance_stocks = $stocks;
                        }
                        $product->save();

                        InventoryMovement::create([
                            'product_id' => $product->getKey(),
                            'user_id' => auth()->id(),
                            'type' => 'in',
                            'quantity' => $item->quantity,
                            'previous_stock' => $previousStock,
                            'new_stock' => $newStock,
                            'reason' => 'Order ' . $lockedOrder->order_number . ' cancelled by customer.',
                        ]);
                    }
                }

                if ($shouldReleaseReservation) {
                    foreach ($lockedOrder->items as $item) {
                        $productId = (string) ($item->product_id ?? '');
                        /** @var \App\Models\Product|null $product */
                        $product = $productId !== '' ? $products->get($productId) : null;

                        if (!$product && $item->product) {
                            $product = $item->product;
                        }

                        if (!$product) {
                            continue;
                        }

                        $qty = (int) $item->quantity;
                        $currentReserved = (int) ($product->reserved_quantity ?? 0);
                        $product->reserved_quantity = max(0, $currentReserved - $qty);

                        $maintenanceYear = $item->maintenance_year !== null ? (int) $item->maintenance_year : null;
                        if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                            $reservedMap = $product->maintenance_reserved_quantities ?? [];
                            $currentYearReserved = (int) ($reservedMap[$maintenanceYear] ?? $reservedMap[(string) $maintenanceYear] ?? 0);
                            $reservedMap[$maintenanceYear] = max(0, $currentYearReserved - $qty);
                            $product->maintenance_reserved_quantities = $reservedMap;
                        }
                        $product->save();
                    }
                }

                $didCancel = true;
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        if (!empty($didCancel)) {
            return back()->with('success', 'Order cancelled.');
        }

        return back()->with('success', 'Order is already cancelled.');
    }

    private function authorizeCustomer(Order $order): void
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }
    }

}
