<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturnRequest;
use App\Models\OrderReturnRequestImage;
use App\Models\OrderReturnRequestStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerOrderReturnRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderReturnRequest::query()
            ->with('order')
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get();

        $statusCounts = OrderReturnRequest::where('user_id', $request->user()->getKey())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('customer.return_requests.index', [
            'requests' => $requests,
            'statuses' => OrderReturnRequest::STATUSES,
            'statusCounts' => $statusCounts,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(Request $request, OrderReturnRequest $orderReturnRequest)
    {
        $this->authorizeCustomer($request, $orderReturnRequest);

        $orderReturnRequest->load([
            'order.items.product',
            'order.refunds',
            'evidenceImages',
            'statusHistories.changedBy',
        ]);

        return view('customer.return_requests.show', [
            'returnRequest' => $orderReturnRequest,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $this->authorizeOrderCustomer($request, $order);

        $reason = (string) $request->input('reason');
        $evidenceRequired = in_array($reason, OrderReturnRequest::EVIDENCE_REQUIRED_REASONS, true);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(OrderReturnRequest::REASONS))],
            'requested_amount' => ['nullable', 'numeric', 'min:0.01'],
            'customer_note' => ['required', 'string', 'max:2000'],
            'evidence_photos' => [$evidenceRequired ? 'required' : 'nullable', 'array', 'max:5'],
            'evidence_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'evidence_photos.required' => 'Please upload proof photos for this refund reason.',
            'evidence_photos.max' => 'You can upload up to 5 proof photos.',
            'evidence_photos.*.mimes' => 'Proof photos must be JPG, PNG, or WEBP images.',
            'evidence_photos.*.max' => 'Each proof photo must be 4MB or smaller.',
        ]);

        $order->load('refunds');

        if (!$this->isOrderEligible($order)) {
            return back()->withErrors([
                'return_request' => 'Only delivered paid orders with refundable balance can request return/refund.',
            ])->withInput();
        }

        if ($this->hasActiveRequest($order)) {
            return back()->withErrors([
                'return_request' => 'This order already has an active return/refund request.',
            ])->withInput();
        }

        $remainingCents = $order->remainingRefundableCents();
        $requestedCents = $remainingCents;

        if (!empty($data['requested_amount'])) {
            $requestedCents = (int) round(((float) $data['requested_amount']) * 100);
        }

        if ($requestedCents < 1 || $requestedCents > $remainingCents) {
            return back()->withErrors([
                'requested_amount' => 'Requested refund amount exceeds the remaining refundable balance.',
            ])->withInput();
        }

        $storedPaths = [];

        try {
            $returnRequest = DB::transaction(function () use ($request, $order, $data, $requestedCents, &$storedPaths) {
                $returnRequest = OrderReturnRequest::create([
                    'order_id' => $order->getKey(),
                    'user_id' => $request->user()->getKey(),
                    'status' => 'pending',
                    'reason' => $data['reason'],
                    'customer_note' => $data['customer_note'],
                    'requested_amount_cents' => $requestedCents,
                    'currency' => 'myr',
                ]);

                foreach ($request->file('evidence_photos', []) as $index => $photo) {
                    $path = $photo->store('return-request-evidence/' . $returnRequest->id, 'public');
                    $storedPaths[] = $path;

                    OrderReturnRequestImage::create([
                        'order_return_request_id' => $returnRequest->id,
                        'path' => $path,
                        'original_name' => $photo->getClientOriginalName(),
                        'mime_type' => $photo->getClientMimeType(),
                        'size' => $photo->getSize(),
                        'sort_order' => $index,
                    ]);
                }

                OrderReturnRequestStatusHistory::create([
                    'order_return_request_id' => $returnRequest->id,
                    'status' => 'pending',
                    'note' => 'Return/refund request submitted by customer.',
                    'changed_by' => $request->user()->getKey(),
                ]);

                return $returnRequest;
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            report($e);

            return back()->withErrors([
                'evidence_photos' => 'Unable to save proof photos. Please try again.',
            ])->withInput();
        }

        return redirect()
            ->route('customer.return-requests.show', $returnRequest)
            ->with('success', 'Return/refund request submitted. Staff will review it.');
    }

    public function cancel(Request $request, OrderReturnRequest $orderReturnRequest)
    {
        $this->authorizeCustomer($request, $orderReturnRequest);

        if ($orderReturnRequest->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending requests can be cancelled.']);
        }

        DB::transaction(function () use ($request, $orderReturnRequest) {
            $orderReturnRequest->status = 'cancelled';
            $orderReturnRequest->save();

            OrderReturnRequestStatusHistory::create([
                'order_return_request_id' => $orderReturnRequest->id,
                'status' => 'cancelled',
                'note' => 'Request cancelled by customer.',
                'changed_by' => $request->user()->getKey(),
            ]);
        });

        return back()->with('success', 'Return/refund request cancelled.');
    }

    private function authorizeCustomer(Request $request, OrderReturnRequest $orderReturnRequest): void
    {
        if ($orderReturnRequest->user_id !== $request->user()->getKey()) {
            abort(403);
        }
    }

    private function authorizeOrderCustomer(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()->getKey()) {
            abort(403);
        }
    }

    private function isOrderEligible(Order $order): bool
    {
        if ($order->status !== 'delivered' || $order->shipment_status !== 'delivered') {
            return false;
        }

        if (!in_array($order->payment_status, ['paid', 'partial_refund'], true)) {
            return false;
        }

        return $order->remainingRefundableCents() > 0;
    }

    private function hasActiveRequest(Order $order): bool
    {
        return OrderReturnRequest::query()
            ->where('order_id', $order->getKey())
            ->whereIn('status', ['pending', 'approved', 'return_received'])
            ->exists();
    }
}
