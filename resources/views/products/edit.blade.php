@extends('layouts.admin')

@section('title', 'Edit Product')
@section('page_title', 'Edit Product')
@section('page_subtitle', 'Update the product details')

@section('content')
    <div class="admin-card">
        <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <label for="name">Product Name</label>
            <input type="text" name="name" required value="{{ old('name', $product->name) }}">

            <label for="description">Description</label>
            <textarea name="description" required>{{ old('description', $product->description) }}</textarea>

            <label for="category_id">Category</label>
            <select name="category_id">
                <option value="">Select a category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        {{ (string) old('category_id', $product->category_id) === (string) $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <label for="price">Price</label>
            <input type="number" name="price" required value="{{ old('price', $product->price) }}">

            <label for="product_type">Product Type</label>
            <select name="product_type" required>
                <option value="normal" {{ old('product_type', $product->product_type ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="cattle" {{ old('product_type', $product->product_type ?? 'normal') === 'cattle' ? 'selected' : '' }}>Cattle</option>
            </select>

            <label for="stock_quantity">Stock Quantity</label>
            <input type="number" name="stock_quantity" required value="{{ old('stock_quantity', $product->stock_quantity) }}">

            <label style="display:flex; gap:10px; align-items:center; margin-top:12px;">
                <input type="checkbox"
                       name="requires_maintenance"
                       value="1"
                       {{ old('requires_maintenance', $product->requires_maintenance) ? 'checked' : '' }}>
                Requires maintenance (select years & prices)
            </label>

            <div id="maintenance-section" style="display:none;">
                <label for="maintenance_years">Maintenance Support (Years)</label>
                <select name="maintenance_years" id="maintenance_years">
                    <option value="">Select duration</option>
                    @for($year = 1; $year <= 5; $year++)
                        <option value="{{ $year }}"
                            {{ (string) old('maintenance_years', $product->maintenance_years) === (string) $year ? 'selected' : '' }}>
                            {{ $year }} Year{{ $year > 1 ? 's' : '' }}
                        </option>
                    @endfor
                </select>

                @php
                    $maintenancePrices = $product->maintenance_prices ?? [];
                @endphp
                @for($year = 1; $year <= 5; $year++)
                    <div class="maintenance-price-row" data-maintenance-year="{{ $year }}">
                        <label for="maintenance_price_{{ $year }}">Year {{ $year }} Price</label>
                        <input type="number"
                               min="0"
                               step="0.01"
                               name="maintenance_prices[{{ $year }}]"
                               id="maintenance_price_{{ $year }}"
                               value="{{ old("maintenance_prices.$year", $maintenancePrices[$year] ?? '') }}">
                    </div>
                @endfor
            </div>

            <label for="image">Product Image</label>
            <input type="file" name="image" accept="image/*">

            <button type="submit" class="btn btn-primary">Update Product</button>
        </form>
    </div>

    <script>
        (function () {
            const toggle = document.querySelector('input[name="requires_maintenance"]');
            const section = document.getElementById('maintenance-section');
            const yearsSelect = document.getElementById('maintenance_years');

            if (!toggle || !section || !yearsSelect) return;

            const yearRows = Array.from(section.querySelectorAll('[data-maintenance-year]'));

            const syncMaintenance = () => {
                const enabled = !!toggle.checked;
                section.style.display = enabled ? 'block' : 'none';

                const years = parseInt(yearsSelect.value || '0', 10);
                yearRows.forEach(row => {
                    const year = parseInt(row.dataset.maintenanceYear, 10);
                    row.style.display = enabled && years >= year ? 'block' : 'none';
                });
            };

            toggle.addEventListener('change', syncMaintenance);
            yearsSelect.addEventListener('change', syncMaintenance);
            syncMaintenance();
        })();
    </script>
@endsection
