@extends('layouts.customer')

@section('title', 'Discounts')
@section('page_title', 'Discounts')
@section('page_subtitle', 'Claim coupons and apply them at checkout')

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

    <div class="customer-card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;">Available Coupons</h3>
            <p style="margin:6px 0 0; color:#7b6a5b;">Claim coupons here, then choose one on the checkout page.</p>
        </div>
        <a class="btn btn-outline" href="{{ route('customer.checkout.index') }}">Go to Checkout</a>
    </div>

    @if($coupons->isEmpty())
        <div class="customer-empty">No coupons are available right now.</div>
    @else
        <div style="display:grid; gap:16px;">
            @foreach($coupons as $coupon)
                @php
                    /** @var \App\Models\CouponClaim|null $claim */
                    $claim = $claimed->get($coupon->id);
                    $isRedeemed = $claim && $claim->redeemed_at;
                    $isClaimed = (bool) $claim;

                    $discountLabel = $coupon->discount_type === 'percent'
                        ? rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.') . '%'
                        : 'RM ' . number_format((float) $coupon->discount_value, 2);
                @endphp

                <div class="customer-card" style="display:grid; gap:10px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <div style="color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em; font-size:12px;">
                                Code: {{ $coupon->code }}
                            </div>
                            <div style="font-size:18px; font-weight:800; color:#4c2f1c;">
                                {{ $coupon->name }}
                            </div>
                            @if($coupon->description)
                                <div style="color:#7b6a5b; margin-top:4px;">{{ $coupon->description }}</div>
                            @endif
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:12px; color:#9b7f63;">Discount</div>
                            <div style="font-size:18px; font-weight:800; color:#4c2f1c;">{{ $discountLabel }}</div>
                            @if((float) ($coupon->min_subtotal ?? 0) > 0)
                                <div style="color:#7b6a5b; font-size:12px;">Min subtotal: RM {{ number_format((float) $coupon->min_subtotal, 2) }}</div>
                            @endif
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div style="color:#5e4a3b;">
                            @if($isRedeemed)
                                <strong>Status:</strong> Redeemed
                            @elseif($isClaimed)
                                <strong>Status:</strong> Claimed
                            @else
                                <strong>Status:</strong> Not claimed
                            @endif
                        </div>

                        @if($isRedeemed)
                            <button type="button" class="btn btn-outline" disabled>Redeemed</button>
                        @elseif(!$isClaimed)
                            <form method="POST" action="{{ route('customer.discounts.claim', $coupon) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Claim</button>
                            </form>
                        @else
                            <a class="btn btn-outline" href="{{ route('customer.checkout.index', ['coupon_code' => $coupon->code]) }}">
                                Use at Checkout
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
