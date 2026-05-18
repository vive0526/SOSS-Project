<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDiscountController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $coupons = Coupon::query()
            ->where('status', Coupon::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('id')
            ->get();

        $claimed = CouponClaim::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('coupon_id');

        return view('customer.discounts.index', [
            'coupons' => $coupons,
            'claimed' => $claimed,
        ]);
    }

    public function claim(Request $request, Coupon $coupon)
    {
        $user = $request->user();

        if ($coupon->status !== Coupon::STATUS_ACTIVE) {
            return redirect()->back()->withErrors(['coupon' => 'This coupon is not available.']);
        }

        $now = now();
        if ($coupon->starts_at && $coupon->starts_at->gt($now)) {
            return redirect()->back()->withErrors(['coupon' => 'This coupon is not available yet.']);
        }
        if ($coupon->ends_at && $coupon->ends_at->lt($now)) {
            return redirect()->back()->withErrors(['coupon' => 'This coupon has expired.']);
        }

        try {
            DB::transaction(function () use ($coupon, $user) {
                $existing = CouponClaim::query()
                    ->where('coupon_id', $coupon->id)
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return;
                }

                if ($coupon->max_total_claims !== null) {
                    $totalClaims = CouponClaim::query()
                        ->where('coupon_id', $coupon->id)
                        ->lockForUpdate()
                        ->count();

                    if ($totalClaims >= (int) $coupon->max_total_claims) {
                        throw new \RuntimeException('This coupon has reached its claim limit.');
                    }
                }

                if ($coupon->max_claims_per_user !== null) {
                    $userClaims = CouponClaim::query()
                        ->where('coupon_id', $coupon->id)
                        ->where('user_id', $user->getKey())
                        ->lockForUpdate()
                        ->count();

                    if ($userClaims >= (int) $coupon->max_claims_per_user) {
                        throw new \RuntimeException('You have already claimed the maximum allowed for this coupon.');
                    }
                }

                CouponClaim::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->getKey(),
                    'claimed_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['coupon' => $e->getMessage() ?: 'Unable to claim coupon.']);
        }

        return redirect()->back()->with('success', 'Coupon claimed successfully.');
    }
}

