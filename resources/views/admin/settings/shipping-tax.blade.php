@extends('layouts.admin')

@section('title', 'Shipping & Tax Settings')
@section('page_title', 'Shipping & Tax Settings')
@section('page_subtitle', 'Configure Malaysia tax and per-state shipping fees')

@section('content')
    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="admin-card">
            <p>There were some issues with your request:</p>
            <ul style="margin:8px 0 0; padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.shipping_tax.update') }}" class="admin-card">
        @csrf
        @method('PUT')

        <h3 style="margin-bottom: 12px;">Malaysia Tax (Default)</h3>
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div style="flex:0 1 220px;">
                <label for="tax_rate_percent" style="display:block; margin-bottom:6px;">Tax Rate (%)</label>
                <input type="number"
                       step="0.01"
                       min="0"
                       max="100"
                       id="tax_rate_percent"
                       name="tax_rate_percent"
                       value="{{ old('tax_rate_percent', $taxRatePercent) }}"
                       style="width:100%;"
                       required>
            </div>
            <div style="color:#bfbfbf; font-size:12px; flex:1 1 280px;">
                Used to calculate checkout tax and stored into each order as the final tax rate.
            </div>
        </div>

        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.08); margin:18px 0;">

        <h3 style="margin-bottom: 12px;">Shipping Fees (Per State)</h3>
        <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                <tr>
                    <th style="text-align:left; padding:10px 8px;">State</th>
                    <th style="text-align:left; padding:10px 8px; width:220px;">Shipping Fee (RM)</th>
                </tr>
                </thead>
                <tbody>
                @foreach($stateOptions as $stateKey => $stateLabel)
                    <tr>
                        <td style="padding:10px 8px;">{{ $stateLabel }}</td>
                        <td style="padding:10px 8px;">
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   name="shipping_fee[{{ $stateKey }}]"
                                   value="{{ old('shipping_fee.' . $stateKey, $shippingFees[$stateKey] ?? 0) }}"
                                   style="width:200px;">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.08); margin:18px 0;">

        <h3 style="margin-bottom: 12px;">Policy Text (Checkout)</h3>
        <div style="display:grid; gap:14px;">
            <div>
                <label for="shipping_policy_text" style="display:block; margin-bottom:6px;">Shipping Policy</label>
                <textarea id="shipping_policy_text"
                          name="shipping_policy_text"
                          rows="4"
                          style="width:100%;">{{ old('shipping_policy_text', $shippingPolicyText) }}</textarea>
            </div>
            <div>
                <label for="tax_policy_text" style="display:block; margin-bottom:6px;">Tax Policy</label>
                <textarea id="tax_policy_text"
                          name="tax_policy_text"
                          rows="4"
                          style="width:100%;">{{ old('tax_policy_text', $taxPolicyText) }}</textarea>
            </div>
        </div>

        <div style="margin-top:16px; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
@endsection

