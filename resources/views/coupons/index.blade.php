@extends('layouts.admin')

@section('title', 'Coupons')
@section('page_title', 'Coupons')
@section('page_subtitle', 'Manage discount coupons for customers')

@section('content')
<div class="admin-card" style="display:grid; gap:16px;">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
        <a class="btn-add" href="{{ route('coupons.create') }}">Add Coupon</a>

        <form method="GET" action="{{ route('coupons.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search code or name">
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn-admin btn-edit">Filter</button>
        </form>
    </div>

    @if(session('success'))
        <p style="color:#86efac; margin:0;">{{ session('success') }}</p>
    @endif

    @if(session('error'))
        <p style="color:#fca5a5; margin:0;">{{ session('error') }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Code</th>
                <th>Name</th>
                <th>Discount</th>
                <th>Window</th>
                <th>Status</th>
                <th>Usage</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($coupons as $index => $coupon)
                <tr>
                    <td>{{ ($coupons->firstItem() ?? 0) + $index }}</td>
                    <td>{{ $coupon->code }}</td>
                    <td>
                        <div style="font-weight:700;">{{ $coupon->name }}</div>
                        @if($coupon->description)
                            <div style="font-size:12px; color:#bfae9f;">{{ $coupon->description }}</div>
                        @endif
                        @if((float) ($coupon->min_subtotal ?? 0) > 0)
                            <div style="font-size:12px; color:#bfae9f;">Min subtotal: RM {{ number_format((float) $coupon->min_subtotal, 2) }}</div>
                        @endif
                    </td>
                    <td>
                        @if($coupon->discount_type === 'percent')
                            {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.') }}%
                        @else
                            RM {{ number_format((float) $coupon->discount_value, 2) }}
                        @endif
                    </td>
                    <td>
                        <div>{{ $coupon->starts_at ? $coupon->starts_at->format('d M Y H:i') : 'Immediate' }}</div>
                        <div style="font-size:12px; color:#bfae9f;">to {{ $coupon->ends_at ? $coupon->ends_at->format('d M Y H:i') : 'No expiry' }}</div>
                    </td>
                    <td>{{ ucfirst($coupon->status) }}</td>
                    <td>
                        <div>Claimed: {{ (int) $coupon->claims_count }}</div>
                        <div style="font-size:12px; color:#bfae9f;">Available: {{ (int) $coupon->available_claims_count }} | Redeemed: {{ (int) $coupon->redeemed_claims_count }}</div>
                    </td>
                    <td>
                        <a class="btn-admin btn-edit" href="{{ route('coupons.edit', $coupon) }}">Edit</a>
                        <form method="POST" action="{{ route('coupons.destroy', $coupon) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-admin btn-delete" onclick="return confirm('Delete this coupon?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;">No coupons found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:12px;">
        {{ $coupons->links('pagination.admin') }}
    </div>
</div>
@endsection
