@php
    /** @var \App\Models\Coupon $coupon */
    $isEdit = $coupon->exists;
@endphp

@if($errors->any())
    <div class="admin-card" style="border-color:#7f1d1d; background:rgba(127,29,29,.08);">
        <div style="color:#fecaca; font-weight:700; margin-bottom:8px;">Please fix the following:</div>
        <ul style="margin:0; padding-left:18px; color:#fecaca;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="admin-card" style="display:grid; gap:16px;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
        <div>
            <label for="code">Coupon Code *</label>
            <input id="code" type="text" name="code" value="{{ old('code', $coupon->code) }}" maxlength="32" required>
            <div style="font-size:12px; color:#bfae9f; margin-top:6px;">Letters, numbers, hyphens, and underscores only.</div>
        </div>

        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" required>
                <option value="active" {{ old('status', $coupon->status ?: 'active') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $coupon->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
        <div>
            <label for="name">Name *</label>
            <input id="name" type="text" name="name" value="{{ old('name', $coupon->name) }}" maxlength="120" required>
        </div>

        <div>
            <label for="discount_type">Discount Type *</label>
            <select id="discount_type" name="discount_type" required>
                <option value="percent" {{ old('discount_type', $coupon->discount_type ?: 'percent') === 'percent' ? 'selected' : '' }}>Percent</option>
                <option value="amount" {{ old('discount_type', $coupon->discount_type) === 'amount' ? 'selected' : '' }}>Fixed Amount (RM)</option>
            </select>
        </div>

        <div>
            <label for="discount_value">Discount Value *</label>
            <input id="discount_value" type="number" name="discount_value" value="{{ old('discount_value', $coupon->discount_value) }}" min="0.01" step="0.01" required>
        </div>
    </div>

    <div>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3">{{ old('description', $coupon->description) }}</textarea>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <div>
            <label for="min_subtotal">Minimum Subtotal (RM)</label>
            <input id="min_subtotal" type="number" name="min_subtotal" value="{{ old('min_subtotal', $coupon->min_subtotal ?? 0) }}" min="0" step="0.01">
        </div>

        <div>
            <label for="starts_at">Starts At</label>
            <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\\TH:i')) }}">
        </div>

        <div>
            <label for="ends_at">Ends At</label>
            <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $coupon->ends_at?->format('Y-m-d\\TH:i')) }}">
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
        <div>
            <label for="max_total_claims">Total Claim Limit</label>
            <input id="max_total_claims" type="number" name="max_total_claims" value="{{ old('max_total_claims', $coupon->max_total_claims) }}" min="1" step="1">
        </div>

        <div>
            <label for="max_claims_per_user">Claim Limit Per User</label>
            <input id="max_claims_per_user" type="number" name="max_claims_per_user" value="{{ old('max_claims_per_user', $coupon->max_claims_per_user) }}" min="1" max="1" step="1">
            <div style="font-size:12px; color:#bfae9f; margin-top:6px;">Current claim flow supports one claim per user.</div>
        </div>

        <div>
            <label for="max_total_redemptions">Total Redemption Limit</label>
            <input id="max_total_redemptions" type="number" name="max_total_redemptions" value="{{ old('max_total_redemptions', $coupon->max_total_redemptions) }}" min="1" step="1">
        </div>
    </div>

    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <button type="submit" class="btn-add">{{ $isEdit ? 'Update Coupon' : 'Create Coupon' }}</button>
        <a href="{{ route('coupons.index') }}" class="btn-admin btn-edit">Cancel</a>
    </div>
</div>
