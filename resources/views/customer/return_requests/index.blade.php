@extends('layouts.customer')

@section('title', 'My Return Requests')
@section('page_title', 'Return & Refund Requests')
@section('page_subtitle', 'Track your submitted return/refund requests')

@section('content')
    @php
        $total = $statusCounts->sum();
    @endphp

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

    <section class="customer-kpis">
        <div class="customer-card">
            <div class="customer-kpi__label">Total Requests</div>
            <div class="customer-kpi__value">{{ $total }}</div>
            <div class="customer-kpi__note">All time</div>
        </div>
        @foreach($statuses as $status)
            <div class="customer-card">
                <div class="customer-kpi__label">{{ ucwords(str_replace('_', ' ', $status)) }}</div>
                <div class="customer-kpi__value">{{ $statusCounts[$status] ?? 0 }}</div>
                <div class="customer-kpi__note">Requests</div>
            </div>
        @endforeach
    </section>

    <section class="customer-toolbar">
        <form class="customer-filter" method="GET" action="{{ route('customer.return-requests.index') }}">
            <div class="customer-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="customer-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('customer.return-requests.index') }}" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </section>

    <section class="customer-section">
        <div class="customer-section__head">
            <h2>Request History</h2>
        </div>

        @if($requests->isEmpty())
            <div class="customer-empty">You have not submitted any return/refund requests yet.</div>
        @else
            <div style="display:grid; gap:16px;">
                @foreach($requests as $returnRequest)
                    <div class="customer-card" style="display:grid; gap:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                            <div>
                                <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">Request</div>
                                <div style="font-size:18px; font-weight:800; color:#4c2f1c;">
                                    {{ $returnRequest->order?->order_number ?? '-' }}
                                </div>
                                <div style="color:#7b6a5b; font-size:12px;">
                                    Submitted {{ $returnRequest->created_at?->format('Y-m-d') }}
                                </div>
                            </div>
                            <span class="customer-status customer-status--{{ $returnRequest->status }}">
                                {{ ucwords(str_replace('_', ' ', $returnRequest->status)) }}
                            </span>
                        </div>
                        <div style="display:flex; gap:18px; flex-wrap:wrap; color:#5e4a3b;">
                            <div><strong>Reason:</strong> {{ $returnRequest->reasonLabel() }}</div>
                            <div><strong>Amount:</strong> RM {{ number_format($returnRequest->amountRm(), 2) }}</div>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a class="btn btn-outline" href="{{ route('customer.return-requests.show', $returnRequest) }}">View Details</a>
                            @if($returnRequest->order)
                                <a class="btn btn-outline" href="{{ route('customer.orders.show', $returnRequest->order) }}">View Order</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
