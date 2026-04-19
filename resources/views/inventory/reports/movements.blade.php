@extends('layouts.admin')

@section('title', 'Stock Movement Report')
@section('page_title', 'Stock Movement Report')
@section('page_subtitle', 'Log of inventory adjustments')

@section('content')
    <div class="admin-card">
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ route('inventory.reports.movements', ['export' => 'csv']) }}">Export CSV</a>
            <a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('inventory.reports.movements', ['export' => 'pdf']) }}">Export PDF</a>
            <a class="btn btn-outline" href="{{ route('inventory.reports.movements', ['export' => 'excel']) }}">Export Excel</a>
        </div>
    </div>

    <div class="admin-card">
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Product</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Previous</th>
                <th>New</th>
                <th>Updated By</th>
                <th>Reason</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            @forelse($movements as $index => $movement)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $movement->product?->name ?? 'N/A' }}</td>
                    <td>{{ strtoupper($movement->type) }}</td>
                    <td>{{ $movement->quantity }}</td>
                    <td>{{ $movement->previous_stock ?? '-' }}</td>
                    <td>{{ $movement->new_stock ?? '-' }}</td>
                    <td>{{ $movement->user?->name ?? 'N/A' }}</td>
                    <td>{{ $movement->reason ?? '-' }}</td>
                    <td>{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No stock movements found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
