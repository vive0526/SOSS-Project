@extends('layouts.customer')

@section('title', 'My Cattle Requests')
@section('page_title', 'Cattle Requests')
@section('page_subtitle', 'Track your cattle purchase requests')

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
                <div class="customer-kpi__label">{{ ucfirst($status) }}</div>
                <div class="customer-kpi__value">{{ $statusCounts[$status] ?? 0 }}</div>
                <div class="customer-kpi__note">Requests</div>
            </div>
        @endforeach
    </section>

    <section class="customer-toolbar">
        <form class="customer-filter" method="GET" action="{{ route('customer.cattle-requests.index') }}">
            <div class="customer-field">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="customer-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('customer.cattle-requests.index') }}" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </section>

    <section class="customer-section">
        <div class="customer-section__head">
            <h2>Request History</h2>
        </div>

        @if($requests->isEmpty())
            <div class="customer-empty">You have not submitted any cattle requests yet.</div>
        @else
            <div style="display:grid; gap:16px;">
                @foreach($requests as $req)
                    <div class="customer-card" style="display:grid; gap:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                            <div>
                                <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">Request</div>
                                <div style="font-size:18px; font-weight:800; color:#4c2f1c;">
                                    {{ $req->product?->name ?? 'Cattle Product' }}
                                </div>
                                <div style="color:#7b6a5b; font-size:12px;">
                                    Submitted {{ $req->created_at?->format('Y-m-d') }}
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <span class="customer-status customer-status--{{ $req->status }}">
                                    {{ ucfirst($req->status) }}
                                </span>
                            </div>
                        </div>
                        <div style="display:flex; gap:18px; flex-wrap:wrap; color:#5e4a3b;">
                            <div><strong>Qty:</strong> {{ $req->quantity }}</div>
                            <div><strong>Purpose:</strong> {{ $req->purpose ? ucfirst($req->purpose) : '-' }}</div>
                            <div><strong>Preferred Date:</strong> {{ $req->preferred_date?->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a class="btn btn-outline" href="{{ route('customer.cattle-requests.show', $req) }}">View Details</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

