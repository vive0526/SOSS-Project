@extends('layouts.admin')

@section('title', 'Return Request Details')
@section('page_title', 'Return Request Details')
@section('page_subtitle', 'Review and process this return/refund request')

@section('content')
    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="admin-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <div style="margin-bottom:16px;">
        <a class="btn btn-outline" href="{{ route('order-return-requests.index') }}">Back to Requests</a>
        @if($returnRequest->order)
            <a class="btn btn-outline" href="{{ route('orders.show', $returnRequest->order) }}">View Order</a>
        @endif
    </div>

    <div class="admin-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 8px;">Request</h3>
            <p><strong>ID:</strong> #{{ $returnRequest->id }}</p>
            <p><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $returnRequest->status)) }}</p>
            <p><strong>Reason:</strong> {{ $returnRequest->reasonLabel() }}</p>
            <p><strong>Amount:</strong> RM {{ number_format($returnRequest->amountRm(), 2) }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Customer</h3>
            <p><strong>Name:</strong> {{ $returnRequest->customer?->name ?? '-' }}</p>
            <p><strong>Email:</strong> {{ $returnRequest->customer?->email ?? '-' }}</p>
            <p><strong>Submitted:</strong> {{ $returnRequest->created_at?->format('Y-m-d H:i') }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Order</h3>
            <p><strong>Order:</strong> {{ $returnRequest->order?->order_number ?? '-' }}</p>
            <p><strong>Payment:</strong> {{ ucwords(str_replace('_', ' ', $returnRequest->order?->payment_status ?? '-')) }}</p>
            <p><strong>Method:</strong> {{ $returnRequest->order?->payment_method ?? '-' }}</p>
            <p><strong>Total:</strong> RM {{ number_format((float) ($returnRequest->order?->total_amount ?? 0), 2) }}</p>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 8px;">Notes</h3>
        <p><strong>Customer Note:</strong> {{ $returnRequest->customer_note ?: '-' }}</p>
        <p><strong>Staff Note:</strong> {{ $returnRequest->staff_note ?: '-' }}</p>
        <p><strong>Rejection Reason:</strong> {{ $returnRequest->rejection_reason ?: '-' }}</p>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Customer Proof Photos</h3>
        @if($returnRequest->evidenceImages->isEmpty())
            <p>No proof photos uploaded.</p>
        @else
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:14px;">
                @foreach($returnRequest->evidenceImages as $image)
                    <a href="{{ $image->url() }}" target="_blank" rel="noopener" style="display:block; text-decoration:none;">
                        <img src="{{ $image->url() }}"
                             alt="Customer proof photo {{ $loop->iteration }}"
                             style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:14px; border:1px solid rgba(212, 175, 55, 0.28);">
                        <div style="margin-top:6px; color:#bfbfbf; font-size:12px;">
                            {{ $image->original_name ?: ('Photo ' . $loop->iteration) }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="admin-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 12px;">Approve</h3>
            <form method="POST" action="{{ route('order-return-requests.approve', $returnRequest) }}">
                @csrf
                @method('PATCH')
                <label for="approve_staff_note">Staff Note (optional)</label>
                <textarea id="approve_staff_note" name="staff_note" rows="3"></textarea>
                <button type="submit" class="btn btn-primary" {{ $returnRequest->status !== 'pending' ? 'disabled' : '' }}>
                    Approve
                </button>
            </form>
        </div>
        <div>
            <h3 style="margin-bottom: 12px;">Reject</h3>
            <form method="POST" action="{{ route('order-return-requests.reject', $returnRequest) }}">
                @csrf
                @method('PATCH')
                <label for="rejection_reason">Rejection Reason</label>
                <input id="rejection_reason" type="text" name="rejection_reason" required>
                <label for="reject_staff_note">Staff Note (optional)</label>
                <textarea id="reject_staff_note" name="staff_note" rows="3"></textarea>
                <button type="submit" class="btn btn-outline" {{ !in_array($returnRequest->status, ['pending', 'approved'], true) ? 'disabled' : '' }}>
                    Reject
                </button>
            </form>
        </div>
        <div>
            <h3 style="margin-bottom: 12px;">Return Received</h3>
            <form method="POST" action="{{ route('order-return-requests.return-received', $returnRequest) }}">
                @csrf
                @method('PATCH')
                <label for="received_staff_note">Staff Note (optional)</label>
                <textarea id="received_staff_note" name="staff_note" rows="3"></textarea>
                <label style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                    <input type="checkbox" name="return_to_stock" value="1">
                    Return items to sellable stock
                </label>
                <button type="submit" class="btn btn-outline" {{ $returnRequest->status !== 'approved' ? 'disabled' : '' }}>
                    Mark Received
                </button>
            </form>
        </div>
        @if(auth()->user()?->role === 'admin')
            <div>
                <h3 style="margin-bottom: 12px;">Refund Completed</h3>
                @if($returnRequest->order && in_array($returnRequest->order->payment_method, ['stripe_card', 'stripe_fpx'], true))
                    <form method="POST" action="{{ route('orders.refund.stripe', $returnRequest->order) }}" style="margin-bottom:14px;">
                        @csrf
                        <label for="stripe_refund_amount">Stripe Refund Amount (RM)</label>
                        <input id="stripe_refund_amount"
                               type="number"
                               step="0.01"
                               min="0.01"
                               name="amount"
                               value="{{ number_format($returnRequest->amountRm(), 2, '.', '') }}">
                        <input type="hidden" name="reason" value="requested_by_customer">
                        <button type="submit"
                                class="btn btn-outline"
                                {{ !$returnRequest->order->payment_reference || !in_array($returnRequest->order->payment_status, ['paid', 'partial_refund'], true) ? 'disabled' : '' }}>
                            Initiate Stripe Refund
                        </button>
                    </form>
                    @if(!$returnRequest->order->payment_reference)
                        <p style="color:#bfbfbf; font-size:12px;">
                            Stripe refund is disabled because this order has no Stripe payment reference.
                        </p>
                    @elseif(!in_array($returnRequest->order->payment_status, ['paid', 'partial_refund'], true))
                        <p style="color:#bfbfbf; font-size:12px;">
                            Stripe refund is disabled because payment status is {{ ucwords(str_replace('_', ' ', $returnRequest->order->payment_status ?? '-')) }}.
                        </p>
                    @else
                        <p style="color:#bfbfbf; font-size:12px;">
                            After Stripe confirms the refund, mark this request as refunded.
                        </p>
                    @endif
                @else
                    <p style="color:#bfbfbf; font-size:12px;">
                        This is a non-Stripe order. Process the refund manually, then mark it as refunded here.
                    </p>
                @endif
                <form method="POST" action="{{ route('order-return-requests.refunded', $returnRequest) }}">
                    @csrf
                    @method('PATCH')
                    <label for="refund_staff_note">Staff Note (optional)</label>
                    <textarea id="refund_staff_note" name="staff_note" rows="3"></textarea>
                    <button type="submit" class="btn btn-primary" {{ !in_array($returnRequest->status, ['approved', 'return_received'], true) ? 'disabled' : '' }}>
                        Mark Refunded
                    </button>
                </form>
            </div>
        @endif
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 8px;">Processing</h3>
        <p><strong>Handled By:</strong> {{ $returnRequest->handledBy?->name ?? '-' }}</p>
        <p><strong>Handled At:</strong> {{ $returnRequest->handled_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>Return Received:</strong> {{ $returnRequest->return_received_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>Stock Returned:</strong> {{ $returnRequest->stock_returned_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>Refunded:</strong> {{ $returnRequest->refunded_at?->format('Y-m-d H:i') ?? '-' }}</p>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Status History</h3>
        @if($returnRequest->statusHistories->isEmpty())
            <p>No status history available.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Changed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($returnRequest->statusHistories as $index => $history)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $history->status)) }}</td>
                            <td>{{ $history->note ?? '-' }}</td>
                            <td>{{ $history->changedBy?->name ?? '-' }}</td>
                            <td>{{ $history->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
