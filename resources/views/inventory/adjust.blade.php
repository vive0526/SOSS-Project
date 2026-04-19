@extends('layouts.admin')

@section('title', 'Adjust Stock')
@section('page_title', 'Manual Stock Adjustment')
@section('page_subtitle', 'Increase or decrease stock with reason tracking')

@section('content')
    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">{{ $product->name }}</h3>
        <p style="color:#bfbfbf; margin-bottom: 18px;">
            Current stock: {{ $product->stock_quantity }}
        </p>

        @if($errors->any())
            <div class="error">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('inventory.adjust.store', $product) }}" method="POST">
            @csrf

            <label for="type">Adjustment Type</label>
            <select name="type" required>
                <option value="in" {{ old('type') === 'in' ? 'selected' : '' }}>Increase (Restock)</option>
                <option value="out" {{ old('type') === 'out' ? 'selected' : '' }}>Decrease (Damaged/Correction)</option>
            </select>

            <label for="quantity">Quantity</label>
            <input type="number"
                   name="quantity"
                   min="1"
                   value="{{ old('quantity') }}"
                   required>

            <label for="reason">Reason (optional)</label>
            <input type="text"
                   name="reason"
                   value="{{ old('reason') }}"
                   placeholder="Restock, damaged items, correction">

            <button type="submit" class="btn btn-primary">Save Adjustment</button>
        </form>
    </div>
@endsection
