@extends('layouts.storefront')

@php
    $isEdit = ($mode ?? 'create') === 'edit';
@endphp

@section('title', $isEdit ? 'Edit Address' : 'Add Address')
@section('page_title', $isEdit ? 'Edit Address' : 'Add Address')
@section('page_subtitle', 'Save delivery details for faster checkout')

@section('content')
    @if($errors->any())
        <div class="customer-alert customer-alert--error" role="alert">
            <div class="customer-alert__title">Please check the highlighted fields.</div>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('customer.addresses.update', $address) : route('customer.addresses.store') }}"
          class="customer-form">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="customer-card">
            <h3 style="margin-bottom: 12px;">Address Details</h3>

            <div class="customer-form__row">
                <div class="customer-field">
                    <label for="label">Label (optional)</label>
                    <input type="text" id="label" name="label" value="{{ old('label', $address->label) }}">
                </div>
                <div class="customer-field" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" id="is_default" name="is_default" value="1" {{ old('is_default', $address->is_default) ? 'checked' : '' }}>
                    <label for="is_default" style="margin:0;">Set as default</label>
                </div>
            </div>

            <div class="customer-form__row">
                <div class="customer-field">
                    <label for="recipient_name">Recipient Name</label>
                    <input type="text" id="recipient_name" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}" required>
                </div>
                <div class="customer-field">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $address->phone) }}" required>
                </div>
            </div>

            <div class="customer-field">
                <label for="address_line">Address</label>
                <textarea id="address_line" name="address_line" required>{{ old('address_line', $address->address_line) }}</textarea>
            </div>

            <div class="customer-form__row">
                <div class="customer-field">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $address->city) }}" required>
                </div>
                <div class="customer-field">
                    <label for="state_key">State</label>
                    <select id="state_key" name="state_key" required>
                        <option value="">Select a state</option>
                        @foreach(($stateOptions ?? []) as $stateKey => $stateLabel)
                            <option value="{{ $stateKey }}" {{ old('state_key', $address->state_key) === $stateKey ? 'selected' : '' }}>
                                {{ $stateLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="customer-form__row">
                <div class="customer-field">
                    <label for="postcode">Postcode</label>
                    <input type="text" id="postcode" name="postcode" value="{{ old('postcode', $address->postcode) }}" required>
                </div>
                <div class="customer-field">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" value="{{ old('country', $address->country ?: 'Malaysia') }}" required>
                </div>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save Changes' : 'Save Address' }}</button>
                <a href="{{ route('customer.addresses.index') }}" class="btn btn-outline">Back</a>
            </div>
        </div>
    </form>
@endsection

