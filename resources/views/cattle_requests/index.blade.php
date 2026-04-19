@extends('layouts.admin')

@section('title', 'Cattle Purchase Requests')
@section('page_title', 'Cattle Purchase Requests')
@section('page_subtitle', 'Review and manage cattle requests')

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
        <form method="GET" action="{{ route('cattle-requests.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }} ({{ $statusCounts[$status] ?? 0 }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:280px;">
                <label for="search">Search</label>
                <input type="text"
                       id="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Customer or product name">
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
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Purpose</th>
                    <th>Preferred Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td>#{{ $req->id }}</td>
                        <td>{{ $req->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            {{ $req->customer?->name ?? '-' }}<br>
                            <span style="color:#bfbfbf; font-size:12px;">{{ $req->customer?->email ?? '' }}</span>
                        </td>
                        <td>{{ $req->phone ?? '-' }}</td>
                        <td>{{ $req->product?->name ?? '-' }}</td>
                        <td>{{ $req->quantity }}</td>
                        <td>{{ $req->purpose ? ucfirst($req->purpose) : '-' }}</td>
                        <td>{{ $req->preferred_date?->format('Y-m-d') ?? '-' }}</td>
                        <td>{{ ucfirst($req->status) }}</td>
                        <td>
                            <a class="btn btn-outline" href="{{ route('cattle-requests.show', $req) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">No requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

