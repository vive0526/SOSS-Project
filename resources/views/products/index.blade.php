@extends('layouts.admin')

@section('title', 'Product List')
@section('page_title', 'Product List')
@section('page_subtitle', 'Manage your products')

@section('content')
        <div class="admin-card">
        <a href="{{ route('products.create') }}" class="btn btn-add">Add Product</a>

        <form id="productFiltersForm" method="GET" action="{{ route('products.index') }}" style="margin-top: 15px;">
            <label for="search">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Product name or description">

            <label for="category_id">Category</label>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

        </form>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Maintenance</th>
                    <th>Physical</th>
                    <th>Reserved</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $index => $product)
                @php
                    $reserved = (int) ($product->reserved_quantity ?? 0);
                    $available = $product->availableStock();
                @endphp
                <tr>
                    <td>{{ ($products->firstItem() ?? 0) + $index }}</td>
                    <td>
                        @if($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" style="width:60px;height:60px;object-fit:cover;">
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                    <td>
                        @if($product->requires_maintenance && !empty($product->maintenance_prices))
                            @php
                                $maintenancePrices = $product->maintenance_prices ?? [];
                                $lowestMaintenancePrice = !empty($maintenancePrices) ? min($maintenancePrices) : null;
                                $lowestMaintenanceYears = !empty($maintenancePrices)
                                    ? array_keys(array_filter($maintenancePrices, fn ($value) => (float) $value === (float) $lowestMaintenancePrice))
                                    : [];
                                sort($lowestMaintenanceYears);
                                $defaultMaintenanceYear = (int) ($lowestMaintenanceYears[0] ?? 1);
                            @endphp
                            <span class="maintenance-price-value" data-maintenance-price>
                                {{ $lowestMaintenancePrice !== null ? 'From RM ' . number_format((float) $lowestMaintenancePrice, 2) : '-' }}
                            </span>
                        @else
                            RM {{ number_format((float) $product->price, 2) }}
                        @endif
                    </td>
                    <td>
                        @if($product->requires_maintenance && !empty($product->maintenance_prices))
                            @php
                                $maintenancePrices = $product->maintenance_prices ?? [];
                            @endphp
                            <select name="maintenance_year_{{ $product->product_id }}" data-maintenance-select>
                                @for($year = 1; $year <= 5; $year++)
                                    @php
                                        $yearPrice = $maintenancePrices[$year] ?? null;
                                    @endphp
                                    <option value="{{ $year }}"
                                            data-price="{{ $yearPrice !== null ? number_format((float) $yearPrice, 2, '.', '') : '' }}"
                                            {{ $year === (int) ($defaultMaintenanceYear ?? 1) ? 'selected' : '' }}
                                            {{ $yearPrice === null ? 'disabled' : '' }}>
                                        Year {{ $year }}{{ $yearPrice !== null ? ' - RM ' . number_format((float) $yearPrice, 2) : '' }}
                                    </option>
                                @endfor
                            </select>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $product->stock_quantity }}</td>
                    <td>{{ $reserved }}</td>
                    <td>{{ $available }}</td>
                    <td>
                        <a href="{{ route('products.edit', $product) }}" class="btn-admin btn-edit">Edit</a>
                        <form action="{{ route('products.destroy', $product) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-admin btn-delete" onclick="return confirm('Delete this product?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $products->links('pagination.admin') }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('productFiltersForm');
            if (form) {
                const categorySelect = form.querySelector('select[name=\"category_id\"]');
                if (categorySelect) {
                    categorySelect.addEventListener('change', () => form.requestSubmit());
                }

                const searchInput = form.querySelector('input[name=\"search\"]');
                if (searchInput) {
                    searchInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            form.requestSubmit();
                        }
                    });
                }
            }

            const selects = document.querySelectorAll('[data-maintenance-select]');
            if (!selects.length) return;

            selects.forEach(select => {
                const row = select.closest('tr');
                const priceEl = row ? row.querySelector('[data-maintenance-price]') : null;
                if (!priceEl) return;

                const updatePrice = () => {
                    const option = select.selectedOptions[0];
                    const price = option ? option.dataset.price : '';
                    priceEl.textContent = price ? ('RM ' + Number(price).toFixed(2)) : '-';
                };

                select.addEventListener('change', updatePrice);
                updatePrice();
            });
        })();
    </script>
@endsection
