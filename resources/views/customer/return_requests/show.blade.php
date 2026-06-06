@extends('layouts.customer')

@section('title', 'Return Request Details')
@section('page_title', 'Return & Refund Request')
@section('page_subtitle', 'Track review and refund progress')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <div style="margin-bottom:16px;">
        <a class="btn btn-outline" href="{{ route('customer.return-requests.index') }}">Back to Requests</a>
        @if($returnRequest->order)
            <a class="btn btn-outline" href="{{ route('customer.orders.show', $returnRequest->order) }}">View Order</a>
        @endif
    </div>

    <div class="customer-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 8px;">Request</h3>
            <p><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $returnRequest->status)) }}</p>
            <p><strong>Reason:</strong> {{ $returnRequest->reasonLabel() }}</p>
            <p><strong>Requested Amount:</strong> RM {{ number_format($returnRequest->amountRm(), 2) }}</p>
            <p><strong>Submitted:</strong> {{ $returnRequest->created_at?->format('Y-m-d H:i') }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Order</h3>
            <p><strong>Order:</strong> {{ $returnRequest->order?->order_number ?? '-' }}</p>
            <p><strong>Order Status:</strong> {{ ucwords(str_replace('_', ' ', $returnRequest->order?->status ?? '-')) }}</p>
            <p><strong>Payment:</strong> {{ ucwords(str_replace('_', ' ', $returnRequest->order?->payment_status ?? '-')) }}</p>
            <p><strong>Total:</strong> RM {{ number_format((float) ($returnRequest->order?->total_amount ?? 0), 2) }}</p>
        </div>
    </div>

    <div class="customer-card">
        <h3 style="margin-bottom: 8px;">Notes</h3>
        <p><strong>Your Note:</strong> {{ $returnRequest->customer_note ?: '-' }}</p>
        <p><strong>Staff Note:</strong> {{ $returnRequest->staff_note ?: '-' }}</p>
        <p><strong>Rejection Reason:</strong> {{ $returnRequest->rejection_reason ?: '-' }}</p>
    </div>

    <div class="customer-card">
        <h3 style="margin-bottom: 12px;">Proof Photos</h3>
        @if($returnRequest->evidenceImages->isEmpty())
            <div class="customer-empty">No proof photos uploaded.</div>
        @else
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:12px;">
                @foreach($returnRequest->evidenceImages as $image)
                    <a href="{{ $image->url() }}" target="_blank" rel="noopener" style="display:block; text-decoration:none;">
                        <img src="{{ $image->url() }}"
                             alt="Proof photo {{ $loop->iteration }}"
                             style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:14px; border:1px solid rgba(0,0,0,.08);">
                        <div style="margin-top:6px; color:#7b6a5b; font-size:12px;">
                            View photo {{ $loop->iteration }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="customer-card">
        <h3 style="margin-bottom: 12px;">Updates</h3>
        @if($returnRequest->statusHistories->isEmpty())
            <div class="customer-empty">No updates yet.</div>
        @else
            <div style="display:grid; gap:10px;">
                @foreach($returnRequest->statusHistories as $history)
                    <div class="customer-card" style="background:#fff; border:1px solid rgba(0,0,0,.06);">
                        <strong>{{ ucwords(str_replace('_', ' ', $history->status)) }}</strong>
                        <div style="color:#7b6a5b; font-size:12px;">
                            {{ $history->created_at?->format('Y-m-d H:i') }}
                            @if($history->changedBy)
                                - {{ $history->changedBy->name }}
                            @endif
                        </div>
                        <div style="color:#5e4a3b; margin-top:6px;">
                            {{ $history->note ?: '-' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($returnRequest->status === 'pending')
        <div class="customer-card">
            <h3 style="margin-bottom: 12px;">Cancel Request</h3>
            <form method="POST" action="{{ route('customer.return-requests.cancel', $returnRequest) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-outline" onclick="return confirm('Cancel this return/refund request?')">
                    Cancel Request
                </button>
            </form>
        </div>
    @endif
@endsection
