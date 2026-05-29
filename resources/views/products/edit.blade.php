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

            <label for="slug">SEO Slug (optional)</label>
            <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="e.g. premium-cattle-feed">
            <small style="display:block; margin-top:-6px; margin-bottom:12px; color:#666;">
                Leave blank to auto-generate from the product name.
            </small>

            <label for="description">Description</label>
            <textarea name="description" required>{{ old('description', $product->description) }}</textarea>

            <div style="display:flex; gap:16px; margin: 12px 0 18px;">
                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label style="display:flex; gap:8px; align-items:center; margin:0;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                        Active (visible in storefront)
                    </label>
                </div>
                <div>
                    <input type="hidden" name="is_featured" value="0">
                    <label style="display:flex; gap:8px; align-items:center; margin:0;">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}>
                        Featured
                    </label>
                </div>
            </div>

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

            <div id="base-product-fields">
                <label for="price">Price</label>
                <input type="number" name="price" required value="{{ old('price', $product->price) }}">

                <label for="product_type">Product Type</label>
                <select name="product_type" required>
                    <option value="normal" {{ old('product_type', $product->product_type ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="cattle" {{ old('product_type', $product->product_type ?? 'normal') === 'cattle' ? 'selected' : '' }}>Cattle</option>
                </select>
            </div>

            <div id="main-stock-field">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" name="stock_quantity" required value="{{ old('stock_quantity', $product->stock_quantity) }}">
            </div>

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
                    $maintenanceStocks = $product->maintenance_stocks ?? [];
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

                        <label for="maintenance_stock_{{ $year }}" style="margin-top:10px;">Year {{ $year }} Stock</label>
                        <input type="number"
                               min="0"
                               step="1"
                               name="maintenance_stocks[{{ $year }}]"
                               id="maintenance_stock_{{ $year }}"
                               value="{{ old("maintenance_stocks.$year", $maintenanceStocks[$year] ?? '') }}">
                    </div>
                @endfor
            </div>

            <label for="image">Product Image</label>
            <input type="file" name="image" accept="image/*">

            <label for="gallery_images">Product Gallery (optional)</label>
            <input type="file" name="gallery_images[]" accept="image/*" multiple>

            <button type="submit" class="btn btn-primary">Update Product</button>
        </form>
    </div>

    @if($product->images && $product->images->count())
        <div class="admin-card" style="margin-top:16px;">
            <div style="font-weight:600; margin-bottom:8px;">Current Gallery</div>
            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                @foreach($product->images as $img)
                    <div style="width:140px;">
                        <div style="border:1px solid #ddd; border-radius:12px; overflow:hidden; width:140px; height:140px;">
                            <img src="{{ asset('storage/' . $img->path) }}" alt="Product image" style="width:140px; height:140px; object-fit:cover; display:block;">
                        </div>
                        <div style="margin-top:6px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            @if($img->is_primary)
                                <span class="customer-badge">Primary</span>
                            @else
                                <span style="color:#666; font-size:12px;">&nbsp;</span>
                            @endif
                            <form action="{{ route('products.images.destroy', [$product, $img]) }}" method="POST" onsubmit="return confirm('Remove this image?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-admin btn-delete" style="padding:6px 10px;">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <script>
        (function () {
            const toggle = document.querySelector('input[name="requires_maintenance"]');
            const section = document.getElementById('maintenance-section');
            const yearsSelect = document.getElementById('maintenance_years');
            const baseFields = document.getElementById('base-product-fields');
            const mainStockField = document.getElementById('main-stock-field');
            const categorySelect = document.querySelector('select[name="category_id"]');
            const TREE_PLANTING_CATEGORY_ID = '5';

            if (!section || !yearsSelect || !baseFields || !mainStockField || !categorySelect) return;

            const yearRows = Array.from(section.querySelectorAll('[data-maintenance-year]'));
            const baseRequiredFields = Array.from(baseFields.querySelectorAll('[required]'));
            const mainStockInput = mainStockField.querySelector('input[name="stock_quantity"]');

            const syncMaintenance = () => {
                const isTreePlanting = String(categorySelect.value || '') === TREE_PLANTING_CATEGORY_ID;
                const enabled = isTreePlanting;

                section.style.display = enabled ? 'block' : 'none';
                baseFields.style.display = enabled ? 'none' : 'block';
                mainStockField.style.display = enabled ? 'none' : 'block';

                baseRequiredFields.forEach((field) => {
                    if (enabled) {
                        field.dataset.wasRequired = '1';
                        field.removeAttribute('required');
                    } else if (field.dataset.wasRequired === '1') {
                        field.setAttribute('required', 'required');
                    }
                });

                if (mainStockInput) {
                    if (enabled) {
                        mainStockInput.dataset.wasRequired = '1';
                        mainStockInput.removeAttribute('required');
                    } else if (mainStockInput.dataset.wasRequired === '1') {
                        mainStockInput.setAttribute('required', 'required');
                    }
                }

                const years = parseInt(yearsSelect.value || '0', 10);
                yearRows.forEach(row => {
                    const year = parseInt(row.dataset.maintenanceYear, 10);
                    row.style.display = enabled && years >= year ? 'block' : 'none';
                });
            };

            yearsSelect.addEventListener('change', syncMaintenance);
            categorySelect.addEventListener('change', syncMaintenance);
            syncMaintenance();
        })();
    </script>
@endsection
