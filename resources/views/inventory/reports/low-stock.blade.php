@extends('layouts.admin')

@section('title', 'Low Stock Report')
@section('page_title', 'Low Stock Report')
@section('page_subtitle', 'Products below reorder level')

@section('content')
    <div class="admin-card">
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ route('inventory.reports.low-stock', ['export' => 'csv']) }}">Export CSV</a>
            <a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('inventory.reports.low-stock', ['export' => 'pdf']) }}">Export PDF</a>
            <a class="btn btn-outline" href="{{ route('inventory.reports.low-stock', ['export' => 'excel']) }}">Export Excel</a>
        </div>
    </div>

    <div class="admin-card">
        @if($products->isEmpty())
            <p>No products below reorder level.</p>
        @else
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
                        $status = $available <= 0 ? 'Out of Stock' : 'Low';
                        $statusClass = $available <= 0 ? 'status-inactive' : 'status-low';
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
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
        @endif
    </div>
@endsection
