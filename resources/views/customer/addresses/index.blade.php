@extends('layouts.storefront')

@section('title', 'My Addresses')
@section('page_title', 'My Addresses')
@section('page_subtitle', 'Manage your saved delivery addresses')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

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

    <div class="customer-card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;">Saved Addresses</h3>
            <div style="color:#7b6a5b; font-size:12px; margin-top:4px;">
                Maximum {{ (int) ($maxAddresses ?? 5) }} addresses per account. You must keep at least one default address.
            </div>
        </div>
        <a class="btn btn-primary" href="{{ route('customer.addresses.create') }}">Add Address</a>
    </div>

    @forelse($addresses as $address)
        <div class="customer-card" style="display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap;">
            <div style="flex:1 1 520px;">
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <strong>{{ $address->label ?: 'Address' }}</strong>
                    @if($address->is_default)
                        <span class="customer-badge">Default</span>
                    @endif
                </div>
                <div style="margin-top:8px; line-height:1.6;">
                    <div><strong>{{ $address->recipient_name }}</strong> — {{ $address->phone }}</div>
                    <div>{{ $address->address_line }}</div>
                    <div>{{ $address->postcode }} {{ $address->city }}, {{ \App\Support\MalaysiaStates::label($address->state_key) }}, {{ $address->country }}</div>
                </div>
            </div>

            <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                @if(!$address->is_default)
                    <form method="POST" action="{{ route('customer.addresses.default', $address) }}">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-outline">Set Default</button>
                    </form>
                @endif
                <a class="btn btn-outline" href="{{ route('customer.addresses.edit', $address) }}">Edit</a>
                <form method="POST" action="{{ route('customer.addresses.destroy', $address) }}"
                      onsubmit="return confirm('Delete this address? You must keep at least one address.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="customer-card">
            <p>No saved addresses yet.</p>
            <a class="btn btn-primary" href="{{ route('customer.addresses.create') }}">Add your first address</a>
        </div>
    @endforelse
@endsection

