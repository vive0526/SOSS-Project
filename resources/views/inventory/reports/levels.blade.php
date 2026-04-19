@extends('layouts.admin')

@section('title', 'Inventory Level Report')
@section('page_title', 'Inventory Level Report')
@section('page_subtitle', 'Snapshot of current inventory levels')

@section('content')
    <div class="admin-card">
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ route('inventory.reports.levels', ['export' => 'csv']) }}">Export CSV</a>
            <a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('inventory.reports.levels', ['export' => 'pdf']) }}">Export PDF</a>
            <a class="btn btn-outline" href="{{ route('inventory.reports.levels', ['export' => 'excel']) }}">Export Excel</a>
        </div>
    </div>

    <div class="admin-card">
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Product</th>
                <th>Category</th>
                <th>Stock Quantity</th>
                <th>Reorder Level</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($products as $index => $product)
                @php
                    $status = 'Normal';
                    $statusClass = 'status-active';
                    if ($product->stock_quantity <= 0) {
                        $status = 'Out of Stock';
                        $statusClass = 'status-inactive';
                    } elseif ($product->stock_quantity <= $product->reorder_level) {
                        $status = 'Low';
                        $statusClass = 'status-low';
                    }
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                    <td>{{ $product->stock_quantity }}</td>
                    <td>{{ $product->reorder_level }}</td>
                    <td><span class="{{ $statusClass }}">{{ $status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
