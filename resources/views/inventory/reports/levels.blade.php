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
                <th>Physical Stock</th>
                <th>Reserved</th>
                <th>Available</th>
                <th>Reorder Level</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($products as $index => $product)
                @php
                    $reserved = (int) ($product->reserved_quantity ?? 0);
                    $available = $product->availableStock();
                    $status = 'Normal';
                    $statusClass = 'status-active';
                    if ($available <= 0) {
                        $status = 'Out of Stock';
                        $statusClass = 'status-inactive';
                    } elseif ($available <= $product->reorder_level) {
                        $status = 'Low';
                        $statusClass = 'status-low';
                    }
                @endphp
                <tr>
                    <td>{{ ($products->firstItem() ?? 0) + $index }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                    <td>{{ $product->stock_quantity }}</td>
                    <td>{{ $reserved }}</td>
                    <td>{{ $available }}</td>
                    <td>{{ $product->reorder_level }}</td>
                    <td><span class="{{ $statusClass }}">{{ $status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:12px;">
            {{ $products->links('pagination.admin') }}
        </div>
    </div>
@endsection
