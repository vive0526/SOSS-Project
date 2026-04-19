@extends('layouts.admin')

@section('title', 'Edit Inventory Settings')
@section('page_title', 'Edit Inventory Settings')
@section('page_subtitle', 'Update reorder level and opening stock')

@section('content')
    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">{{ $product->name }}</h3>
        <p style="color:#bfbfbf; margin-bottom: 18px;">
            Current stock: {{ $product->stock_quantity }} | Reorder level: {{ $product->reorder_level }}
        </p>

        <form action="{{ route('inventory.update', $product) }}" method="POST">
            @csrf
            @method('PUT')

            <label for="reorder_level">Reorder Level</label>
            <input type="number"
                   name="reorder_level"
                   min="0"
                   value="{{ old('reorder_level', $product->reorder_level) }}"
                   required>

            <label for="stock_quantity">Set Stock Quantity (optional)</label>
            <input type="number"
                   name="stock_quantity"
                   min="0"
                   value="{{ old('stock_quantity') }}"
                   placeholder="Leave blank to keep current stock">

            <label for="reason">Reason (optional)</label>
            <input type="text"
                   name="reason"
                   value="{{ old('reason') }}"
                   placeholder="Initial stock or correction note">

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
@endsection
