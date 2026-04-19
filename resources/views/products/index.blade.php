@extends('layouts.admin')

@section('title', 'Product List')
@section('page_title', 'Product List')
@section('page_subtitle', 'Manage your products')

@section('content')
    <div class="admin-card">
        <a href="{{ route('products.create') }}" class="btn btn-add">Add Product</a>

        <form method="GET" action="{{ route('products.index') }}" style="margin-top: 15px;">
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

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('products.index') }}" class="btn">Reset</a>
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
                    <th>Stock Quantity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $index => $product)
                <tr>
                    <td>{{ $index + 1 }}</td>
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
                        @if((string) $product->category_id === '3' && !empty($product->maintenance_prices))
                            @php
                                $maintenancePrices = $product->maintenance_prices ?? [];
                                if (!empty($maintenancePrices)) {
                                    ksort($maintenancePrices);
                                }
                                $firstMaintenanceYear = !empty($maintenancePrices) ? (int) array_key_first($maintenancePrices) : null;
                                $firstMaintenancePrice = $firstMaintenanceYear ? $maintenancePrices[$firstMaintenanceYear] : null;
                            @endphp
                            <span class="maintenance-price-value" data-maintenance-price>
                                {{ $firstMaintenancePrice !== null ? 'RM ' . number_format((float) $firstMaintenancePrice, 2) : '-' }}
                            </span>
                        @else
                            RM {{ number_format((float) $product->price, 2) }}
                        @endif
                    </td>
                    <td>
                        @if((string) $product->category_id === '3' && !empty($product->maintenance_prices))
                            @php
                                $maintenancePrices = $product->maintenance_prices ?? [];
                                if (!empty($maintenancePrices)) {
                                    ksort($maintenancePrices);
                                }
                            @endphp
                            <select name="maintenance_year_{{ $product->product_id }}" data-maintenance-select>
                                @for($year = 1; $year <= 5; $year++)
                                    @php
                                        $yearPrice = $maintenancePrices[$year] ?? null;
                                    @endphp
                                    <option value="{{ $year }}"
                                            data-price="{{ $yearPrice !== null ? number_format((float) $yearPrice, 2, '.', '') : '' }}"
                                            {{ $year === (int) array_key_first($maintenancePrices) ? 'selected' : '' }}
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
    </div>

    <script>
        (function () {
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
