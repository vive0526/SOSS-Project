@extends('layouts.admin')

@section('title', 'Inventory History')
@section('page_title', 'Inventory History')
@section('page_subtitle', 'Track stock movements and adjustments')

@section('content')
    <div class="admin-card">
        @if(session('success'))
            <p>{{ session('success') }}</p>
        @endif

        <form method="GET" action="{{ route('inventory.history') }}" style="display:flex; gap:16px; flex-wrap:wrap;">
            <div style="min-width:200px;">
                <label for="product_id">Product</label>
                <select name="product_id" id="product_id">
                    <option value="">All products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->product_id }}"
                            {{ (string) request('product_id') === (string) $product->product_id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="min-width:160px;">
                <label for="type">Type</label>
                <select name="type" id="type">
                    <option value="">All types</option>
                    <option value="in" {{ request('type') === 'in' ? 'selected' : '' }}>Stock In</option>
                    <option value="out" {{ request('type') === 'out' ? 'selected' : '' }}>Stock Out</option>
                    <option value="set" {{ request('type') === 'set' ? 'selected' : '' }}>Set Stock</option>
                </select>
            </div>

        </form>
    </div>

    <div class="admin-card">
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Product</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Previous</th>
                <th>New</th>
                <th>Updated By</th>
                <th>Reason</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            @forelse($movements as $index => $movement)
                <tr>
                    <td>{{ ($movements->firstItem() ?? 0) + $index }}</td>
                    <td>{{ $movement->product?->name ?? 'N/A' }}</td>
                    <td>{{ strtoupper($movement->type) }}</td>
                    <td>{{ $movement->quantity }}</td>
                    <td>{{ $movement->previous_stock ?? '-' }}</td>
                    <td>{{ $movement->new_stock ?? '-' }}</td>
                    <td>{{ $movement->user?->name ?? 'N/A' }}</td>
                    <td>{{ $movement->reason ?? '-' }}</td>
                    <td>{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No inventory movements recorded.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px;">
            {{ $movements->links('pagination.admin') }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.querySelector('form[action="{{ route('inventory.history') }}"]');
            if (!form) return;

            const fields = form.querySelectorAll('select[name="product_id"], select[name="type"]');
            if (!fields.length) return;

            let t = null;
            const submitSoon = () => {
                if (t) window.clearTimeout(t);
                t = window.setTimeout(() => {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }, 150);
            };

            fields.forEach((el) => el.addEventListener('change', submitSoon));
        })();
    </script>
@endsection
