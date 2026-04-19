@extends('layouts.admin')

@section('title', 'Inventory Overview')
@section('page_title', 'Inventory Overview')
@section('page_subtitle', 'Track stock levels and alerts')

@section('content')
    <div class="admin-card">
        <form method="GET" action="{{ route('products.inventory') }}"
              style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <div>
                <label for="low_stock_threshold">Low stock threshold</label>
                <input type="number"
                       min="0"
                       name="low_stock_threshold"
                       value="{{ $threshold }}"
                       style="width:140px;">
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>
    </div>

    <div class="admin-card">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Total Products</div>
                <div style="font-size:24px; font-weight:700;">{{ $totalProducts }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Low Stock (<= {{ $threshold }})</div>
                <div style="font-size:24px; font-weight:700;">{{ $lowStockCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Out of Stock</div>
                <div style="font-size:24px; font-weight:700;">{{ $outOfStockCount }}</div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">All Products</h3>
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Name</th>
                <th>Category</th>
                <th>Stock Quantity</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($products as $index => $product)
                @php
                    $stockStatus = 'In Stock';
                    $statusClass = 'status-active';
                    if ($product->stock_quantity <= 0) {
                        $stockStatus = 'Out of Stock';
                        $statusClass = 'status-inactive';
                    } elseif ($product->stock_quantity <= $threshold) {
                        $stockStatus = 'Low Stock';
                        $statusClass = 'status-low';
                    }
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                    <td>{{ $product->stock_quantity }}</td>
                    <td><span class="{{ $statusClass }}">{{ $stockStatus }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Low Stock Products</h3>
        @if($lowStockProducts->isEmpty())
            <p>No low stock products at the current threshold.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Stock Quantity</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lowStockProducts as $index => $product)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                        <td>{{ $product->stock_quantity }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Out of Stock Products</h3>
        @if($outOfStockProducts->isEmpty())
            <p>No out of stock products.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Stock Quantity</th>
                </tr>
                </thead>
                <tbody>
                @foreach($outOfStockProducts as $index => $product)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                        <td>{{ $product->stock_quantity }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
