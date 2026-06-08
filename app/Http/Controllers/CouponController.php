<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query()
            ->withCount([
                'claims',
                'claims as redeemed_claims_count' => fn ($claimQuery) => $claimQuery->whereNotNull('redeemed_at'),
                'claims as available_claims_count' => fn ($claimQuery) => $claimQuery->whereNull('redeemed_at'),
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Coupon::STATUS_ACTIVE])
            ->orderByDesc('id');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($couponQuery) use ($search) {
                $couponQuery->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        $status = (string) $request->query('status', '');
        if (in_array($status, [Coupon::STATUS_ACTIVE, Coupon::STATUS_INACTIVE], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        $coupons = $query->paginate(20)->withQueryString();

        return view('coupons.index', [
            'coupons' => $coupons,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('coupons.create', [
            'coupon' => new Coupon([
                'status' => Coupon::STATUS_ACTIVE,
                'discount_type' => Coupon::TYPE_PERCENT,
                'min_subtotal' => 0,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        Coupon::create($this->validatedData($request));

        return redirect()
            ->route('coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        return view('coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validatedData($request, $coupon));

        return redirect()
            ->route('coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->claims()->exists()) {
            return redirect()
                ->route('coupons.index')
                ->with('error', 'Coupon cannot be deleted because it has already been claimed. Set it inactive instead.');
        }

        $coupon->delete();

        return redirect()
            ->route('coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    private function validatedData(Request $request, ?Coupon $coupon = null): array
    {
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'discount_type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_AMOUNT])],
            'discount_value' => 'required|numeric|gt:0',
            'min_subtotal' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'max_total_claims' => 'nullable|integer|min:1',
            'max_claims_per_user' => 'nullable|integer|min:1|max:1',
            'max_total_redemptions' => 'nullable|integer|min:1',
            'status' => ['required', Rule::in([Coupon::STATUS_ACTIVE, Coupon::STATUS_INACTIVE])],
        ], [
            'code.regex' => 'Coupon code may only contain letters, numbers, hyphens, and underscores.',
            'max_claims_per_user.max' => 'This system currently supports at most one claim per user for each coupon.',
        ]);

        $validator->after(function ($validator) use ($request, $coupon) {
            $discountType = (string) $request->input('discount_type');
            $discountValue = (float) $request->input('discount_value', 0);

            if ($discountType === Coupon::TYPE_PERCENT && $discountValue > 100) {
                $validator->errors()->add('discount_value', 'Percent discount cannot exceed 100.');
            }

            $maxTotalClaims = $request->filled('max_total_claims')
                ? (int) $request->input('max_total_claims')
                : null;
            $maxTotalRedemptions = $request->filled('max_total_redemptions')
                ? (int) $request->input('max_total_redemptions')
                : null;

            if ($maxTotalClaims !== null && $maxTotalRedemptions !== null && $maxTotalRedemptions > $maxTotalClaims) {
                $validator->errors()->add('max_total_redemptions', 'Redemption limit cannot exceed claim limit.');
            }

            if (!$coupon) {
                return;
            }

            $claimedCount = (int) $coupon->claims()->count();
            $redeemedCount = (int) $coupon->claims()->whereNotNull('redeemed_at')->count();

            if ($maxTotalClaims !== null && $maxTotalClaims < $claimedCount) {
                $validator->errors()->add('max_total_claims', 'Claim limit cannot be lower than current claimed count.');
            }

            if ($maxTotalRedemptions !== null && $maxTotalRedemptions < $redeemedCount) {
                $validator->errors()->add('max_total_redemptions', 'Redemption limit cannot be lower than current redeemed count.');
            }
        });

        $validated = $validator->validate();

        return [
            'code' => strtoupper(trim((string) $validated['code'])),
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) && trim((string) $validated['description']) !== ''
                ? trim((string) $validated['description'])
                : null,
            'discount_type' => (string) $validated['discount_type'],
            'discount_value' => $this->normalizeMoney((float) $validated['discount_value']),
            'min_subtotal' => $this->normalizeMoney((float) ($validated['min_subtotal'] ?? 0)),
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'max_total_claims' => $validated['max_total_claims'] ?? null,
            'max_claims_per_user' => $validated['max_claims_per_user'] ?? null,
            'max_total_redemptions' => $validated['max_total_redemptions'] ?? null,
            'status' => (string) $validated['status'],
        ];
    }

    private function normalizeMoney(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }
}
