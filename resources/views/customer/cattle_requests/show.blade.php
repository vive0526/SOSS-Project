@extends('layouts.customer')

@section('title', 'Cattle Request Details')
@section('page_title', 'Cattle Request')
@section('page_subtitle', 'Track your cattle request status')

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

    <div class="customer-card" style="display:grid; gap:10px;">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">Cattle Product</div>
                <div style="font-size:18px; font-weight:800; color:#4c2f1c;">
                    {{ $request->product?->name ?? '-' }}
                </div>
                <div style="color:#7b6a5b; font-size:12px;">
                    Submitted {{ $request->created_at?->format('Y-m-d H:i') }}
                </div>
            </div>
            <div>
                <span class="customer-status customer-status--{{ $request->status }}">
                    {{ ucfirst($request->status) }}
                </span>
            </div>
        </div>

        <div style="display:flex; gap:18px; flex-wrap:wrap; color:#5e4a3b;">
            <div><strong>Phone:</strong> {{ $request->phone ?? '-' }}</div>
            <div><strong>Qty:</strong> {{ $request->quantity }}</div>
            <div><strong>Purpose:</strong> {{ $request->purpose ? ucfirst($request->purpose) : '-' }}</div>
            <div><strong>Preferred Date:</strong> {{ $request->preferred_date?->format('Y-m-d') ?? '-' }}</div>
        </div>

        <div style="color:#5e4a3b;">
            <strong>Notes:</strong> {{ $request->customer_note ?: '-' }}
        </div>
    </div>

    <div class="customer-card">
        <h3 style="margin-bottom: 10px;">Updates</h3>
        @if($request->statusHistories->isEmpty())
            <div class="customer-empty">No updates yet.</div>
        @else
            <div style="display:grid; gap:10px;">
                @foreach($request->statusHistories as $history)
                    <div class="customer-card" style="background:#fff; border:1px solid rgba(0,0,0,.06);">
                        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                            <div>
                                <strong>{{ ucfirst($history->status) }}</strong>
                                <div style="color:#7b6a5b; font-size:12px;">
                                    {{ $history->created_at?->format('Y-m-d H:i') }}
                                    @if($history->changedBy)
                                        • {{ $history->changedBy->name }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div style="color:#5e4a3b; margin-top:6px;">
                            {{ $history->note ?: '-' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="customer-card">
        <a class="btn btn-outline" href="{{ route('customer.cattle-requests.index') }}">Back to Requests</a>
        @if($request->product)
            <a class="btn btn-primary" href="{{ route('customer.products.show', $request->product->slug) }}">View Product</a>
        @endif
    </div>
@endsection
