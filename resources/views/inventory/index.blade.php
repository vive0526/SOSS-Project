@extends('layouts.admin')

@section('title', 'Inventory Dashboard')
@section('page_title', 'Inventory Dashboard')
@section('page_subtitle', 'Track stock levels and reorder points')

@section('content')
    @php
        $isAdmin = auth()->user()->role === 'admin';
        $isStaff = auth()->user()->role === 'staff';
    @endphp

    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Inventory Alerts</h3>
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <span class="status-low">Low Stock: {{ $lowStockCount }}</span>
            <span class="status-inactive">Out of Stock: {{ $outOfStockCount }}</span>
        </div>
        @if($lowStockCount === 0 && $outOfStockCount === 0)
            <p style="margin-top:12px; color:#bfbfbf;">All inventory levels look healthy.</p>
        @endif
    </div>

    <div class="admin-card">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Total Products</div>
                <div style="font-size:24px; font-weight:700;">{{ $totalProducts }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Low Stock</div>
                <div style="font-size:24px; font-weight:700;">{{ $lowStockCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Out of Stock</div>
                <div style="font-size:24px; font-weight:700;">{{ $outOfStockCount }}</div>
            </div>
            @if($isAdmin)
                <div>
                    <div style="color:#bfbfbf; font-size:12px;">History</div>
                    <a class="btn btn-outline" href="{{ route('inventory.history') }}">View History</a>
                </div>
            @endif
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
                <th>Physical Stock</th>
                <th>Reserved</th>
                <th>Available</th>
                <th>Reorder Level</th>
                <th>Status</th>
                @if($isAdmin || $isStaff)
                    <th>Actions</th>
                @endif
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
                    @if($isAdmin || $isStaff)
                        <td>
                            <div class="admin-actions">
                                @if($isAdmin)
                                    <a class="btn-admin btn-edit" href="{{ route('inventory.edit', $product) }}">Edit</a>
                                @endif
                                <a class="btn-admin btn-activate" href="{{ route('inventory.adjust', $product) }}">Adjust</a>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:12px;">
            {{ $products->links('pagination.admin') }}
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Low Stock Alerts</h3>
        @if($lowStockCount === 0)
            <p>No products below reorder level.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Physical Stock</th>
                    <th>Reserved</th>
                    <th>Available</th>
                    <th>Reorder Level</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lowStockProducts as $index => $product)
                    @php
                        $reserved = (int) ($product->reserved_quantity ?? 0);
                        $available = $product->availableStock();
                    @endphp
                    <tr>
                        <td>{{ ($lowStockProducts->firstItem() ?? 0) + $index }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                        <td>{{ $product->stock_quantity }}</td>
                        <td>{{ $reserved }}</td>
                        <td>{{ $available }}</td>
                        <td>{{ $product->reorder_level }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top:12px;">
                {{ $lowStockProducts->links('pagination.admin') }}
            </div>
        @endif
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Out of Stock Products</h3>
        @if($outOfStockCount === 0)
            <p>No out of stock products.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Physical Stock</th>
                    <th>Reserved</th>
                    <th>Available</th>
                    <th>Reorder Level</th>
                </tr>
                </thead>
                <tbody>
                @foreach($outOfStockProducts as $index => $product)
                    @php
                        $reserved = (int) ($product->reserved_quantity ?? 0);
                        $available = $product->availableStock();
                    @endphp
                    <tr>
                        <td>{{ ($outOfStockProducts->firstItem() ?? 0) + $index }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                        <td>{{ $product->stock_quantity }}</td>
                        <td>{{ $reserved }}</td>
                        <td>{{ $available }}</td>
                        <td>{{ $product->reorder_level }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top:12px;">
                {{ $outOfStockProducts->links('pagination.admin') }}
            </div>
        @endif
    </div>
@endsection
