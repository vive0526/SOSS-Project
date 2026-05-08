<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\OrderPaymentService;
use App\Services\OrderStateEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('customer')->orderByDesc('created_at');
        $isStaff = auth()->user()?->role === 'staff';
        $hasFilters = $request->filled('status')
            || $request->filled('shipment_status')
            || $request->filled('payment')
            || $request->filled('search')
            || $request->filled('date_from')
            || $request->filled('date_to')
            || $request->boolean('show_all');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment')) {
            $payment = (string) $request->input('payment');

            // Backward compatible mapping for older URLs.
            if ($payment === 'verified') {
                $payment = 'paid';
            } elseif ($payment === 'unverified') {
                $payment = 'unpaid';
            }

            if (in_array($payment, Order::PAYMENT_STATUSES, true)) {
                $query->where('payment_status', $payment);
            }
        }

        if ($request->filled('shipment_status')) {
            $query->where('shipment_status', $request->input('shipment_status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $orders = $query->get();
        $statusCounts = Order::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'shipmentStatuses' => Order::SHIPMENT_STATUSES,
            'statusCounts' => $statusCounts,
            'totalOrders' => Order::count(),
            'filters' => $request->only(['status', 'shipment_status', 'payment', 'search', 'date_from', 'date_to', 'show_all']),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items.product', 'statusHistories.changedBy', 'refunds.requestedBy']);

        return view('orders.show', [
            'order' => $order,
            'statuses' => Order::STATUSES,
            'shipmentStatuses' => Order::SHIPMENT_STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderStateEngine $orderStateEngine)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', Order::STATUSES),
            'note' => 'nullable|string|max:500',
        ]);

        if ($data['status'] === 'cancelled') {
            return back()->withErrors(['status' => 'Use the cancel action to provide a reason.']);
        }

        if ($order->status === $data['status']) {
            return back()->with('success', 'Order status is already set.');
        }

        try {
            $orderStateEngine->transitionOrderStatus(
                $order,
                $data['status'],
                $data['note'] ?? 'Status updated.',
                auth()->id()
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Order status updated.');
    }

    public function verifyPayment(Request $request, Order $order, OrderPaymentService $orderPaymentService)
    {
        if ($order->payment_status === 'paid') {
            return back()->with('success', 'Payment is already verified.');
        }

        $order->load('items.product');
        $insufficient = [];

        foreach ($order->items as $item) {
            if (!$item->product) {
                $insufficient[] = $item->product_name . ' (missing product)';
                continue;
            }

            $qty = (int) $item->quantity;

            if ($order->reserved_at) {
                if ((int) $item->product->stock_quantity < $qty || (int) ($item->product->reserved_quantity ?? 0) < $qty) {
                    $insufficient[] = $item->product->name;
                }
            } else {
                if ($item->product->availableStock() < $qty) {
                    $insufficient[] = $item->product->name;
                }
            }
        }

        if (!empty($insufficient)) {
            return back()->withErrors([
                'payment' => 'Not enough stock for: ' . implode(', ', $insufficient),
            ]);
        }

        $orderPaymentService->verifyPayment(
            $order,
            $request->user()->getKey(),
            $order->payment_reference,
            'Payment verified (manual).'
        );

        return back()->with('success', 'Payment verified and stock updated.');
    }

    public function assign(Request $request, Order $order)
    {
        $data = $request->validate([
            'assigned_to' => 'nullable|string|max:100',
        ]);

        $order->assigned_to = $data['assigned_to'];
        $order->save();

        $note = $order->assigned_to
            ? 'Assigned to ' . $order->assigned_to . '.'
            : 'Assignment cleared.';

        $this->logStatus($order, $order->status, $note);

        return back()->with('success', 'Assignment updated.');
    }

    public function updateShipment(Request $request, Order $order)
    {
        if ($order->status === 'cancelled') {
            return back()->withErrors(['shipment_status' => 'Cannot update shipment for a cancelled order.']);
        }

        $role = $request->user()?->role;
        $isAdmin = $role === 'admin';

        $data = $request->validate([
            'shipment_status' => 'required|in:' . implode(',', Order::SHIPMENT_STATUSES),
            'tracking_number' => 'nullable|string|max:120',
            'shipping_name' => 'nullable|string|max:120',
            'shipping_phone' => 'nullable|string|max:60',
            'shipping_address' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:120',
            'shipping_state' => 'nullable|string|max:120',
            'shipping_postcode' => 'nullable|string|max:30',
            'shipping_country' => 'nullable|string|max:120',
            'confirm_shipping' => 'nullable|boolean',
        ]);

        $currentShipmentStatus = (string) ($order->shipment_status ?: 'pending');
        $nextShipmentStatus = (string) $data['shipment_status'];

        $allowedShipmentTransitions = [
            'pending' => ['shipped'],
            'shipped' => ['delivered'],
            'delivered' => [],
        ];

        if ($currentShipmentStatus !== $nextShipmentStatus) {
            $allowedNext = $allowedShipmentTransitions[$currentShipmentStatus] ?? [];
            if (!in_array($nextShipmentStatus, $allowedNext, true)) {
                return back()->withErrors([
                    'shipment_status' => 'Invalid shipment status transition: ' . $currentShipmentStatus . ' -> ' . $nextShipmentStatus . '.',
                ]);
            }
        }

        // Shipping is considered started once shipment_status reaches "shipped"
        // (and remains started for "delivered").
        $shipmentAlreadyStarted = in_array($currentShipmentStatus, ['shipped', 'delivered'], true);
        if ($shipmentAlreadyStarted && !$isAdmin) {
            $normalize = static function ($value) {
                if (is_string($value)) {
                    $value = trim($value);
                }

                return $value === '' ? null : $value;
            };

            $attemptedDetailsChange = false;
            $detailFields = [
                'tracking_number',
                'shipping_name',
                'shipping_phone',
                'shipping_address',
                'shipping_city',
                'shipping_state',
                'shipping_postcode',
                'shipping_country',
            ];

            foreach ($detailFields as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }

                $requested = $normalize($data[$field]);
                $current = $normalize($order->{$field});

                if ($requested !== $current) {
                    $attemptedDetailsChange = true;
                    break;
                }
            }

            $attemptedConfirmShipping = $request->boolean('confirm_shipping') && !$order->shipping_confirmed_at;

            if ($attemptedDetailsChange || $attemptedConfirmShipping) {
                return back()->withErrors([
                    'shipment_status' => 'Only admin can update tracking/shipping details after shipment has started.',
                ]);
            }
        }

        if (!$order->isPaymentAcceptableForFulfillment() && $data['shipment_status'] !== 'pending') {
            return back()->withErrors(['shipment_status' => 'Verify payment before shipping this order (Cash on Delivery orders are allowed).']);
        }

        $statusChanged = $order->shipment_status !== $data['shipment_status'];
        $order->shipment_status = $data['shipment_status'];

        if (array_key_exists('tracking_number', $data)) {
            $order->tracking_number = $data['tracking_number'] ?: null;
        }
        if (array_key_exists('shipping_name', $data)) {
            $order->shipping_name = $data['shipping_name'];
        }
        if (array_key_exists('shipping_phone', $data)) {
            $order->shipping_phone = $data['shipping_phone'];
        }
        if (array_key_exists('shipping_address', $data)) {
            $order->shipping_address = $data['shipping_address'];
        }
        if (array_key_exists('shipping_city', $data)) {
            $order->shipping_city = $data['shipping_city'];
        }
        if (array_key_exists('shipping_state', $data)) {
            $order->shipping_state = $data['shipping_state'];
        }
        if (array_key_exists('shipping_postcode', $data)) {
            $order->shipping_postcode = $data['shipping_postcode'];
        }
        if (array_key_exists('shipping_country', $data)) {
            $order->shipping_country = $data['shipping_country'];
        }

        if ($request->boolean('confirm_shipping') && !$order->shipping_confirmed_at) {
            $order->shipping_confirmed_at = now();
        }

        $order->save();

        if ($statusChanged) {
            $this->logStatus(
                $order,
                $order->status,
                'Shipment status updated to ' . $order->shipment_status . '.'
            );
        }

        return back()->with('success', 'Shipment details updated.');
    }

    public function cancel(Request $request, Order $order, OrderStateEngine $orderStateEngine)
    {
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

                if ($lockedOrder->status === 'cancelled') {
                    return;
                }

                $lockedOrder->load('items.product');

                $shouldReturnStock = $lockedOrder->payment_status === 'paid' && $lockedOrder->shipment_status === 'pending';
                $shouldReleaseReservation = $lockedOrder->payment_status !== 'paid' && $lockedOrder->reserved_at !== null;

                $note = 'Cancelled: ' . $data['cancel_reason'];
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
                        $product->save();

                        InventoryMovement::create([
                            'product_id' => $product->getKey(),
                            'user_id' => auth()->id(),
                            'type' => 'in',
                            'quantity' => $item->quantity,
                            'previous_stock' => $previousStock,
                            'new_stock' => $newStock,
                            'reason' => 'Order ' . $lockedOrder->order_number . ' cancelled (stock returned).',
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

    public function reopen(Request $request, Order $order, OrderStateEngine $orderStateEngine)
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $note = !empty($data['note'])
            ? 'Reopened: ' . $data['note']
            : 'Order reopened.';

        try {
            $orderStateEngine->reopenOrder($order, $note, auth()->id());
        } catch (\DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Order reopened.');
    }

    public function reportSummary(Request $request)
    {
        $query = Order::query();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->query('export') === 'excel' || $request->query('export') === 'pdf') {
            $totalOrders = (clone $query)->count();
            $totalValue = (clone $query)->sum('total_amount');
            $paymentPaidCount = (clone $query)->where('payment_status', 'paid')->count();
            $paymentPendingCount = (clone $query)->where('payment_status', 'pending')->count();
            $paymentUnpaidCount = (clone $query)->where('payment_status', 'unpaid')->count();
            $paymentRefundPendingCount = (clone $query)->where('payment_status', 'refund_pending')->count();
            $paymentPartialRefundCount = (clone $query)->where('payment_status', 'partial_refund')->count();
            $paymentRefundedCount = (clone $query)->where('payment_status', 'refunded')->count();

            $statusRows = (clone $query)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn ($row) => [$row->status, $row->total]);

            $dailyRows = (clone $query)
                ->selectRaw('date(created_at) as report_date, count(*) as total_orders, sum(total_amount) as total_value')
                ->groupBy('report_date')
                ->orderByDesc('report_date')
                ->get()
                ->map(fn ($day) => [$day->report_date, $day->total_orders, $day->total_value]);

            $tables = [
                [
                    'title' => 'Summary',
                    'headers' => ['Metric', 'Value'],
                    'rows' => [
                        ['Total Orders', $totalOrders],
                        ['Total Order Value', $totalValue],
                        ['Payments Paid', $paymentPaidCount],
                        ['Payments Pending', $paymentPendingCount],
                        ['Payments Unpaid', $paymentUnpaidCount],
                        ['Refund Pending', $paymentRefundPendingCount],
                        ['Partial Refund', $paymentPartialRefundCount],
                        ['Refunded', $paymentRefundedCount],
                    ],
                ],
                [
                    'title' => 'Status Breakdown',
                    'headers' => ['Status', 'Count'],
                    'rows' => $statusRows,
                ],
                [
                    'title' => 'Daily Summary',
                    'headers' => ['Date', 'Total Orders', 'Total Value'],
                    'rows' => $dailyRows,
                ],
            ];

            if ($request->query('export') === 'excel') {
                return $this->streamExcelTablesDownload(
                    'order-summary-report-' . now()->format('Y-m-d_His') . '.xls',
                    'Order Summary Report',
                    $tables
                );
            }

            return response()->view('reports.print', [
                'title' => 'Order Summary Report',
                'tables' => $tables,
            ]);
        }

        if ($request->query('export') === 'daily_csv') {
            $dailySummary = (clone $query)
                ->selectRaw('date(created_at) as report_date, count(*) as total_orders, sum(total_amount) as total_value')
                ->groupBy('report_date')
                ->orderByDesc('report_date')
                ->cursor();

            $rows = (function () use ($dailySummary) {
                foreach ($dailySummary as $day) {
                    yield [
                        $day->report_date,
                        $day->total_orders,
                        $day->total_value,
                    ];
                }
            })();

            return $this->streamCsvDownload(
                'order-summary-daily-' . now()->format('Y-m-d_His') . '.csv',
                ['Date', 'Total Orders', 'Total Value'],
                $rows
            );
        }

        if ($request->query('export') === 'status_csv') {
            $statusCounts = (clone $query)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderBy('status')
                ->cursor();

            $rows = (function () use ($statusCounts) {
                foreach ($statusCounts as $row) {
                    yield [
                        $row->status,
                        $row->total,
                    ];
                }
            })();

            return $this->streamCsvDownload(
                'order-summary-status-' . now()->format('Y-m-d_His') . '.csv',
                ['Status', 'Count'],
                $rows
            );
        }

        $totalOrders = (clone $query)->count();
        $totalValue = (clone $query)->sum('total_amount');
        $statusCounts = (clone $query)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $paymentPaidCount = (clone $query)->where('payment_status', 'paid')->count();
        $paymentPendingCount = (clone $query)->where('payment_status', 'pending')->count();
        $paymentUnpaidCount = (clone $query)->where('payment_status', 'unpaid')->count();
        $paymentRefundPendingCount = (clone $query)->where('payment_status', 'refund_pending')->count();
        $paymentPartialRefundCount = (clone $query)->where('payment_status', 'partial_refund')->count();
        $paymentRefundedCount = (clone $query)->where('payment_status', 'refunded')->count();

        $dailySummary = (clone $query)
            ->selectRaw('date(created_at) as report_date, count(*) as total_orders, sum(total_amount) as total_value')
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->get();

        return view('orders.reports.summary', [
            'totalOrders' => $totalOrders,
            'totalValue' => $totalValue,
            'statusCounts' => $statusCounts,
            'paymentPaidCount' => $paymentPaidCount,
            'paymentPendingCount' => $paymentPendingCount,
            'paymentUnpaidCount' => $paymentUnpaidCount,
            'paymentRefundPendingCount' => $paymentRefundPendingCount,
            'paymentPartialRefundCount' => $paymentPartialRefundCount,
            'paymentRefundedCount' => $paymentRefundedCount,
            'dailySummary' => $dailySummary,
            'statuses' => Order::STATUSES,
            'filters' => $request->only(['date_from', 'date_to']),
        ]);
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
