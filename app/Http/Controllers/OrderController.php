<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
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
            if ($request->input('payment') === 'verified') {
                $query->whereNotNull('payment_verified_at');
            }
            if ($request->input('payment') === 'unverified') {
                $query->whereNull('payment_verified_at');
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
        $order->load(['customer', 'items.product', 'statusHistories.changedBy']);

        return view('orders.show', [
            'order' => $order,
            'statuses' => Order::STATUSES,
            'shipmentStatuses' => Order::SHIPMENT_STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
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

        $order->status = $data['status'];
        $order->cancelled_at = null;
        $order->cancelled_reason = null;
        $order->save();

        $this->logStatus($order, $data['status'], $data['note'] ?? 'Status updated.');

        return back()->with('success', 'Order status updated.');
    }

    public function verifyPayment(Request $request, Order $order)
    {
        if ($order->payment_verified_at) {
            return back()->with('success', 'Payment is already verified.');
        }

        $order->load('items.product');
        $insufficient = [];

        foreach ($order->items as $item) {
            if (!$item->product) {
                $insufficient[] = $item->product_name . ' (missing product)';
                continue;
            }

            if ($item->product->stock_quantity < $item->quantity) {
                $insufficient[] = $item->product->name;
            }
        }

        if (!empty($insufficient)) {
            return back()->withErrors([
                'payment' => 'Not enough stock for: ' . implode(', ', $insufficient),
            ]);
        }

        DB::transaction(function () use ($order, $request) {
            $order->payment_verified_at = now();
            $order->save();

            foreach ($order->items as $item) {
                if (!$item->product) {
                    continue;
                }

                $product = $item->product;
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $item->quantity;

                $product->stock_quantity = $newStock;
                $product->save();

                    InventoryMovement::create([
                        'product_id' => $product->getKey(),
                        'user_id' => $request->user()->getKey(),
                        'type' => 'out',
                        'quantity' => $item->quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reason' => 'Order ' . $order->order_number . ' payment verified.',
                    ]);
            }

            $this->logStatus($order, $order->status, 'Payment verified.');
        });

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

        if (!$order->payment_verified_at && $data['shipment_status'] !== 'pending') {
            return back()->withErrors(['shipment_status' => 'Verify payment before shipping this order.']);
        }

        $statusChanged = $order->shipment_status !== $data['shipment_status'];
        $order->shipment_status = $data['shipment_status'];
        $order->tracking_number = $data['tracking_number'] ?: null;
        $order->shipping_name = $data['shipping_name'] ?? $order->shipping_name;
        $order->shipping_phone = $data['shipping_phone'] ?? $order->shipping_phone;
        $order->shipping_address = $data['shipping_address'] ?? $order->shipping_address;
        $order->shipping_city = $data['shipping_city'] ?? $order->shipping_city;
        $order->shipping_state = $data['shipping_state'] ?? $order->shipping_state;
        $order->shipping_postcode = $data['shipping_postcode'] ?? $order->shipping_postcode;
        $order->shipping_country = $data['shipping_country'] ?? $order->shipping_country;

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

    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        if ($order->status === 'cancelled') {
            return back()->with('success', 'Order is already cancelled.');
        }

        $shouldReturnStock = $order->payment_verified_at && $order->shipment_status === 'pending';
        $order->load('items.product');

        DB::transaction(function () use ($order, $shouldReturnStock, $data) {
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
                        'reason' => 'Order ' . $order->order_number . ' cancelled (stock returned).',
                    ]);
                }
            }
        });

        $note = 'Cancelled: ' . $data['cancel_reason'];
        if ($shouldReturnStock) {
            $note .= ' Stock returned.';
        }
        $this->logStatus($order, 'cancelled', $note);

        return back()->with('success', 'Order cancelled.');
    }

    public function reopen(Request $request, Order $order)
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        if ($order->status !== 'cancelled') {
            return back()->withErrors(['status' => 'Only cancelled orders can be reopened.']);
        }

        if ($order->shipment_status !== 'pending') {
            return back()->withErrors(['status' => 'Cannot reopen after shipment has started.']);
        }

        $order->status = 'pending';
        $order->cancelled_at = null;
        $order->cancelled_reason = null;
        $order->shipment_status = 'pending';
        $order->save();

        $note = !empty($data['note'])
            ? 'Reopened: ' . $data['note']
            : 'Order reopened.';

        $this->logStatus($order, 'pending', $note);

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
            $paymentVerifiedCount = (clone $query)->whereNotNull('payment_verified_at')->count();
            $paymentUnverifiedCount = max($totalOrders - $paymentVerifiedCount, 0);

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
                        ['Payments Verified', $paymentVerifiedCount],
                        ['Payments Unverified', $paymentUnverifiedCount],
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

        $paymentVerifiedCount = (clone $query)->whereNotNull('payment_verified_at')->count();
        $paymentUnverifiedCount = max($totalOrders - $paymentVerifiedCount, 0);

        $dailySummary = (clone $query)
            ->selectRaw('date(created_at) as report_date, count(*) as total_orders, sum(total_amount) as total_value')
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->get();

        return view('orders.reports.summary', [
            'totalOrders' => $totalOrders,
            'totalValue' => $totalValue,
            'statusCounts' => $statusCounts,
            'paymentVerifiedCount' => $paymentVerifiedCount,
            'paymentUnverifiedCount' => $paymentUnverifiedCount,
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
