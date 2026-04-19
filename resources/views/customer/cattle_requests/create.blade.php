@extends('layouts.customer')

@section('title', 'Request Purchase')
@section('page_title', 'Request Purchase')
@section('page_subtitle', 'Submit a purchase request for cattle products')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    @php
        $user = auth()->user();
    @endphp

    <div class="customer-card">
        <h3 style="margin-bottom: 12px;">Cattle Purchase Request</h3>

        <form method="POST" action="{{ route('customer.cattle-requests.store') }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->product_id }}">

            <label>Customer Name</label>
            <input type="text" value="{{ $user?->name ?? '' }}" disabled>

            <label>Email</label>
            <input type="text" value="{{ $user?->email ?? '' }}" disabled>

            <label>Cattle Product</label>
            <input type="text" value="{{ $product->name }}" disabled>

            <label for="phone">Phone Number</label>
            <input type="text"
                   id="phone"
                   name="phone"
                   value="{{ old('phone', $user?->phone ?? '') }}"
                   required>

            <label for="quantity">Quantity Requested</label>
            <input type="number"
                   id="quantity"
                   name="quantity"
                   min="1"
                   max="{{ $product->stock_quantity }}"
                   value="{{ old('quantity', 1) }}"
                   required>

            <label for="purpose">Purpose of Purchase</label>
            <select id="purpose" name="purpose" required>
                <option value="">Select purpose</option>
                <option value="breeding" {{ old('purpose') === 'breeding' ? 'selected' : '' }}>Breeding</option>
                <option value="slaughter" {{ old('purpose') === 'slaughter' ? 'selected' : '' }}>Slaughter</option>
                <option value="others" {{ old('purpose') === 'others' ? 'selected' : '' }}>Others</option>
            </select>

            <label for="preferred_date">Preferred Date</label>
            <input type="date"
                   id="preferred_date"
                   name="preferred_date"
                   value="{{ old('preferred_date') }}"
                   required>

            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top: 12px;">
                <button type="submit" class="btn btn-primary">Submit Request</button>
                <a class="btn btn-outline" href="{{ route('customer.products.show', $product) }}">Back to Product</a>
            </div>
        </form>
    </div>
@endsection

