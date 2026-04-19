@extends('layouts.admin')

@section('title', 'Create Product')
@section('page_title', 'Create New Product')
@section('page_subtitle', 'Fill in the details')

@section('content')
    <div class="admin-card">
        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <label for="name">Product Name</label>
            <input type="text" name="name" required value="{{ old('name') }}">

            <label for="description">Description</label>
            <textarea name="description" required>{{ old('description') }}</textarea>

            <label for="category_id">Category</label>
            <select name="category_id">
                <option value="">Select a category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <label for="price">Price</label>
            <input type="number" name="price" required value="{{ old('price') }}">

            <label for="product_type">Product Type</label>
            <select name="product_type" required>
                <option value="normal" {{ old('product_type', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="cattle" {{ old('product_type', 'normal') === 'cattle' ? 'selected' : '' }}>Cattle</option>
            </select>

            <label for="stock_quantity">Stock Quantity</label>
            <input type="number" name="stock_quantity" required value="{{ old('stock_quantity') }}">

            <div id="maintenance-section" style="display:none;">
                <label for="maintenance_years">Maintenance Support (Years)</label>
                <select name="maintenance_years" id="maintenance_years">
                    <option value="">Select duration</option>
                    @for($year = 1; $year <= 5; $year++)
                        <option value="{{ $year }}"
                            {{ (string) old('maintenance_years') === (string) $year ? 'selected' : '' }}>
                            {{ $year }} Year{{ $year > 1 ? 's' : '' }}
                        </option>
                    @endfor
                </select>

                @for($year = 1; $year <= 5; $year++)
                    <div class="maintenance-price-row" data-maintenance-year="{{ $year }}">
                        <label for="maintenance_price_{{ $year }}">Year {{ $year }} Price</label>
                        <input type="number"
                               min="0"
                               step="0.01"
                               name="maintenance_prices[{{ $year }}]"
                               id="maintenance_price_{{ $year }}"
                               value="{{ old("maintenance_prices.$year") }}">
                    </div>
                @endfor
            </div>

            <label for="image">Product Image</label>
            <input type="file" name="image" accept="image/*">

            <button type="submit" class="btn btn-primary">Save Product</button>
        </form>
    </div>

    <script>
        (function () {
            const categorySelect = document.querySelector('select[name="category_id"]');
            const section = document.getElementById('maintenance-section');
            const yearsSelect = document.getElementById('maintenance_years');

            if (!categorySelect || !section || !yearsSelect) return;

            const yearRows = Array.from(section.querySelectorAll('[data-maintenance-year]'));

            const syncMaintenance = () => {
                const isTreePlanting = categorySelect.value === '3';
                section.style.display = isTreePlanting ? 'block' : 'none';

                const years = parseInt(yearsSelect.value || '0', 10);
                yearRows.forEach(row => {
                    const year = parseInt(row.dataset.maintenanceYear, 10);
                    row.style.display = isTreePlanting && years >= year ? 'block' : 'none';
                });
            };

            categorySelect.addEventListener('change', syncMaintenance);
            yearsSelect.addEventListener('change', syncMaintenance);
            syncMaintenance();
        })();
    </script>
@endsection
