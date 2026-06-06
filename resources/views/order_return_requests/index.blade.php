@extends('layouts.admin')

@section('title', 'Return & Refund Requests')
@section('page_title', 'Return & Refund Requests')
@section('page_subtitle', 'Review customer return/refund requests')

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

    <div class="admin-card">
        <form method="GET" action="{{ route('order-return-requests.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $status)) }} ({{ $statusCounts[$status] ?? 0 }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:280px;">
                <label for="search">Search</label>
                <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Order, customer, email">
            </div>
            <button type="submit" class="btn btn-outline">Filter</button>
        </form>
    </div>

    <div class="admin-card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Created</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Reason</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $returnRequest)
                    <tr>
                        <td>#{{ $returnRequest->id }}</td>
                        <td>{{ $returnRequest->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $returnRequest->order?->order_number ?? '-' }}</td>
                        <td>
                            {{ $returnRequest->customer?->name ?? '-' }}<br>
                            <span style="color:#bfbfbf; font-size:12px;">{{ $returnRequest->customer?->email ?? '' }}</span>
                        </td>
                        <td>{{ $returnRequest->reasonLabel() }}</td>
                        <td>RM {{ number_format($returnRequest->amountRm(), 2) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $returnRequest->status)) }}</td>
                        <td>
                            <a class="btn btn-outline" href="{{ route('order-return-requests.show', $returnRequest) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No return/refund requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px;">
            {{ $requests->links('pagination.admin') }}
        </div>
    </div>
@endsection
