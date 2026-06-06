<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\OrderRefund;
use App\Models\OrderReturnRequest;
use App\Models\OrderReturnRequestStatusHistory;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\OrderStateEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderReturnRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderReturnRequest::query()
            ->with(['order', 'customer', 'handledBy'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', function ($orderQuery) use ($search) {
                    $orderQuery->where('order_number', 'like', '%' . $search . '%');
                })->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('order_return_requests.index', [
            'requests' => $requests,
            'statuses' => OrderReturnRequest::STATUSES,
            'statusCounts' => OrderReturnRequest::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(OrderReturnRequest $orderReturnRequest)
    {
        $orderReturnRequest->load([
            'order.items.product',
            'order.refunds.requestedBy',
            'customer',
            'handledBy',
            'evidenceImages',
            'statusHistories.changedBy',
        ]);

        return view('order_return_requests.show', [
            'returnRequest' => $orderReturnRequest,
        ]);
    }

    public function approve(Request $request, OrderReturnRequest $orderReturnRequest)
    {
        if ($orderReturnRequest->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending requests can be approved.']);
        }

        $data = $request->validate([
            'staff_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $orderReturnRequest, $data) {
            $orderReturnRequest->status = 'approved';
            $orderReturnRequest->staff_note = $data['staff_note'] ?? null;
            $orderReturnRequest->rejection_reason = null;
            $orderReturnRequest->handled_by = $request->user()?->getKey();
            $orderReturnRequest->handled_at = now();
            $orderReturnRequest->save();

            OrderReturnRequestStatusHistory::create([
                'order_return_request_id' => $orderReturnRequest->id,
                'status' => 'approved',
                'note' => 'Return/refund request approved by staff.',
                'changed_by' => $request->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Return/refund request approved.');
    }

    public function reject(Request $request, OrderReturnRequest $orderReturnRequest)
    {
        if (!in_array($orderReturnRequest->status, ['pending', 'approved'], true)) {
            return back()->withErrors(['status' => 'Only pending or approved requests can be rejected.']);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:255',
            'staff_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $orderReturnRequest, $data) {
            $orderReturnRequest->status = 'rejected';
            $orderReturnRequest->rejection_reason = $data['rejection_reason'];
            $orderReturnRequest->staff_note = $data['staff_note'] ?? null;
            $orderReturnRequest->handled_by = $request->user()?->getKey();
            $orderReturnRequest->handled_at = now();
            $orderReturnRequest->save();

            OrderReturnRequestStatusHistory::create([
                'order_return_request_id' => $orderReturnRequest->id,
                'status' => 'rejected',
                'note' => 'Rejected: ' . $data['rejection_reason'],
                'changed_by' => $request->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Return/refund request rejected.');
    }

    public function markReturnReceived(Request $request, OrderReturnRequest $orderReturnRequest)
    {
        if ($orderReturnRequest->status !== 'approved') {
            return back()->withErrors(['status' => 'Only approved requests can be marked as received.']);
        }

        $data = $request->validate([
            'staff_note' => 'nullable|string|max:2000',
            'return_to_stock' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $orderReturnRequest, $data) {
            $lockedRequest = OrderReturnRequest::query()
                ->whereKey($orderReturnRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRequest->load('order.items.product');

            if ($request->boolean('return_to_stock') && !$lockedRequest->stock_returned_at) {
                $this->returnItemsToStock($lockedRequest);
                $lockedRequest->stock_returned_at = now();
            }

            $lockedRequest->status = 'return_received';
            $lockedRequest->return_received_at = now();
            if (!empty($data['staff_note'])) {
                $lockedRequest->staff_note = $data['staff_note'];
            }
            $lockedRequest->handled_by = $request->user()?->getKey();
            $lockedRequest->handled_at = now();
            $lockedRequest->save();

            OrderReturnRequestStatusHistory::create([
                'order_return_request_id' => $lockedRequest->id,
                'status' => 'return_received',
                'note' => $request->boolean('return_to_stock')
                    ? 'Return received and stock returned.'
                    : 'Return received. Stock was not returned.',
                'changed_by' => $request->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Return marked as received.');
    }

    public function markRefunded(Request $request, OrderReturnRequest $orderReturnRequest, OrderStateEngine $orderStateEngine)
    {
        if (!in_array($orderReturnRequest->status, ['approved', 'return_received'], true)) {
            return back()->withErrors(['status' => 'Only approved or received requests can be marked as refunded.']);
        }

        $orderReturnRequest->load('order');
        $order = $orderReturnRequest->order;

        if ($order && in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true)
            && !in_array($order->payment_status, ['partial_refund', 'refunded'], true)) {
            return back()->withErrors([
                'status' => 'Create the Stripe refund first, then mark this request refunded after Stripe confirms it.',
            ]);
        }

        $data = $request->validate([
            'staff_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request, $orderReturnRequest, $data, $orderStateEngine) {
            $orderReturnRequest->load('order');
            $order = $orderReturnRequest->order;

            if ($order && !in_array($order->payment_method, ['stripe_card', 'stripe_fpx'], true)) {
                OrderRefund::query()->updateOrCreate(
                    [
                        'provider' => 'manual',
                        'provider_refund_id' => 'manual-return-' . $orderReturnRequest->id,
                    ],
                    [
                        'order_id' => $order->getKey(),
                        'provider_payment_intent_id' => null,
                        'amount_cents' => (int) $orderReturnRequest->requested_amount_cents,
                        'currency' => $orderReturnRequest->currency ?: 'myr',
                        'reason' => $orderReturnRequest->reason,
                        'status' => 'succeeded',
                        'requested_by' => $request->user()?->getKey(),
                        'processed_at' => now(),
                        'provider_payload' => [
                            'source' => 'manual_return_request',
                            'return_request_id' => $orderReturnRequest->id,
                        ],
                    ]
                );

                $orderStateEngine->recalculateRefundPaymentStatus($order);

                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'status' => $order->status,
                    'note' => 'Manual refund recorded for return request #' . $orderReturnRequest->id . '.',
                    'changed_by' => $request->user()?->getKey(),
                ]);
            }

            $orderReturnRequest->status = 'refunded';
            $orderReturnRequest->refunded_at = now();
            if (!empty($data['staff_note'])) {
                $orderReturnRequest->staff_note = $data['staff_note'];
            }
            $orderReturnRequest->handled_by = $request->user()?->getKey();
            $orderReturnRequest->handled_at = now();
            $orderReturnRequest->save();

            OrderReturnRequestStatusHistory::create([
                'order_return_request_id' => $orderReturnRequest->id,
                'status' => 'refunded',
                'note' => 'Refund completion recorded by staff.',
                'changed_by' => $request->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Return/refund request marked as refunded.');
    }

    private function returnItemsToStock(OrderReturnRequest $returnRequest): void
    {
        $order = $returnRequest->order;
        if (!$order) {
            return;
        }

        $productIds = collect($order->items)->pluck('product_id')->filter()->unique()->values()->all();
        $products = Product::query()
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        foreach ($order->items as $item) {
            $productId = (string) ($item->product_id ?? '');
            $product = $productId !== '' ? $products->get($productId) : null;

            if (!$product && $item->product) {
                $product = $item->product;
            }

            if (!$product) {
                continue;
            }

            $previousStock = (int) $product->stock_quantity;
            $quantity = (int) $item->quantity;
            $newStock = $previousStock + $quantity;

            $product->stock_quantity = $newStock;

            $maintenanceYear = $item->maintenance_year !== null ? (int) $item->maintenance_year : null;
            if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
                $stocks = $product->maintenance_stocks ?? [];
                $current = (int) ($stocks[$maintenanceYear] ?? $stocks[(string) $maintenanceYear] ?? 0);
                $stocks[$maintenanceYear] = $current + $quantity;
                $product->maintenance_stocks = $stocks;
            }

            $product->save();

            InventoryMovement::create([
                'product_id' => $product->getKey(),
                'user_id' => auth()->id(),
                'type' => 'in',
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => 'Order ' . $order->order_number . ' return received.',
            ]);
        }
    }
}
