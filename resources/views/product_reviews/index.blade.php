@extends('layouts.admin')

@section('title', 'Product Reviews')
@section('page_title', 'Product Reviews')
@section('page_subtitle', 'Manage customer comments and ratings')

@section('content')
    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="admin-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="admin-card">
        <form method="GET" action="{{ route('product-reviews.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }} ({{ $statusCounts[$status] ?? 0 }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rating">Rating</label>
                <select name="rating" id="rating">
                    <option value="">All ratings</option>
                    @for($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" {{ (string) ($filters['rating'] ?? '') === (string) $rating ? 'selected' : '' }}>
                            {{ $rating }} star
                        </option>
                    @endfor
                </select>
            </div>
            <div style="min-width:280px;">
                <label for="search">Search</label>
                <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Product, customer, order, comment">
            </div>
            <button type="submit" class="btn btn-outline">Filter</button>
            <a href="{{ route('product-reviews.index') }}" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <div class="admin-card">
        <table>
            <thead>
                <tr>
                    <th>Created</th>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Order</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                    <tr>
                        <td>{{ $review->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $review->product?->name ?? '-' }}</td>
                        <td>
                            {{ $review->customer?->name ?? ($review->is_dummy ? 'Demo Customer' : '-') }}<br>
                            <span style="color:#bfbfbf; font-size:12px;">{{ $review->customer?->email ?? '' }}</span>
                        </td>
                        <td>{{ $review->order?->order_number ?? ($review->is_dummy ? 'Dummy' : '-') }}</td>
                        <td style="color:#d4af37;">{{ str_repeat('★', (int) $review->rating) }}</td>
                        <td style="max-width:360px;">{{ $review->comment ?: '-' }}</td>
                        <td>{{ ucfirst($review->status) }}</td>
                        <td>
                            <form method="POST" action="{{ route('product-reviews.update-status', $review) }}" style="display:flex; gap:8px; flex-wrap:wrap;">
                                @csrf
                                @method('PATCH')
                                @if($review->status !== 'approved')
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-primary">Approve</button>
                                @else
                                    <input type="hidden" name="status" value="hidden">
                                    <button type="submit" class="btn btn-outline">Hide</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No reviews found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:12px;">
            {{ $reviews->links('pagination.admin') }}
        </div>
    </div>
@endsection
