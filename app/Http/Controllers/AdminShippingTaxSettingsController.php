<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ShippingRate;
use App\Support\MalaysiaStates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminShippingTaxSettingsController extends Controller
{
    public function edit()
    {
        $stateOptions = MalaysiaStates::options();

        $rates = ShippingRate::query()
            ->whereIn('state_key', array_keys($stateOptions))
            ->get()
            ->keyBy('state_key');

        $shippingFees = [];
        foreach (array_keys($stateOptions) as $stateKey) {
            $shippingFees[$stateKey] = (float) (($rates->get($stateKey)?->shipping_fee) ?? 0);
        }

        $taxRate = AppSetting::getDecimal('malaysia_tax_rate', 0.06);
        $taxRatePercent = $taxRate * 100;

        return view('admin.settings.shipping-tax', [
            'stateOptions' => $stateOptions,
            'shippingFees' => $shippingFees,
            'taxRatePercent' => $taxRatePercent,
            'taxPolicyText' => AppSetting::getString('tax_policy_text', ''),
            'shippingPolicyText' => AppSetting::getString('shipping_policy_text', ''),
        ]);
    }

    public function update(Request $request)
    {
        $stateKeys = MalaysiaStates::keys();

        $data = $request->validate([
            'tax_rate_percent' => 'required|numeric|min:0|max:100',
            'shipping_fee' => 'required|array',
            'shipping_fee.*' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'tax_policy_text' => 'nullable|string|max:5000',
            'shipping_policy_text' => 'nullable|string|max:5000',
        ]);

        $shippingFee = (array) ($data['shipping_fee'] ?? []);

        // Enforce state keys (ignore unexpected inputs).
        $filteredShippingFees = [];
        foreach ($stateKeys as $stateKey) {
            if (array_key_exists($stateKey, $shippingFee)) {
                $filteredShippingFees[$stateKey] = (float) ($shippingFee[$stateKey] ?? 0);
            }
        }

        DB::transaction(function () use ($data, $filteredShippingFees) {
            $rate = ((float) $data['tax_rate_percent']) / 100;
            $rate = max(0.0, min(1.0, $rate));

            AppSetting::putString('malaysia_tax_rate', number_format($rate, 4, '.', ''));
            AppSetting::putString('tax_policy_text', (string) ($data['tax_policy_text'] ?? ''));
            AppSetting::putString('shipping_policy_text', (string) ($data['shipping_policy_text'] ?? ''));

            foreach ($filteredShippingFees as $stateKey => $fee) {
                ShippingRate::query()->updateOrCreate(
                    ['state_key' => $stateKey],
                    ['shipping_fee' => $fee, 'active' => true]
                );
            }
        });

        return redirect()
            ->route('admin.settings.shipping_tax.edit')
            ->with('success', 'Shipping and tax settings updated successfully.');
    }
}

