@extends((auth()->check() && auth()->user()->role === 'staff') ? 'layouts.admin' : 'layouts.customer')

@section('title', 'Profile')
@section('page_title', 'Your Profile')
@section('page_subtitle', 'Manage your account details and security')

@section('content')
@php
    $requiresCheckoutProfile = auth()->check() && auth()->user()->role === 'customer';
@endphp
<div class="customer-profile">
    <section class="customer-profile__intro">
        <div class="customer-profile__intro-text">
            <span class="customer-pill">Account Center</span>
            <h2>Profile and security</h2>
            <p>Keep your contact details fresh and your account protected.</p>
            @if(auth()->check() && auth()->user()->role === 'customer')
                <div class="customer-profile__intro-actions">
                    <a class="btn btn-outline" href="{{ route('customer.products.index') }}">Browse Products</a>
                </div>
            @endif
        </div>
        <div class="customer-profile__intro-card">
            <div class="customer-profile__avatar">
                @if($user->profile_photo)
                    <button type="button" class="customer-avatar-btn" data-avatar-trigger>
                        <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="Profile photo">
                    </button>
                @else
                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                @endif
            </div>
            <div class="customer-profile__name">{{ $user->name }}</div>
            <div class="customer-profile__meta">{{ $user->email }}</div>
            <div class="customer-profile__meta">
                Member since {{ $user->created_at?->format('M Y') ?? 'recently' }}
            </div>
        </div>
    </section>

    @if(session('status'))
        <div class="customer-alert">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="customer-alert customer-alert--error">
            <div class="customer-alert__title">Please check the highlighted fields.</div>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="customer-profile__grid">
        {{-- Profile Form --}}
        <section class="customer-card customer-profile__card">
            <div class="customer-card__head">
                <h3>Profile details</h3>
                <p>Update your contact information and delivery address.</p>
            </div>
            <form class="customer-form" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="customer-form__row">
                    <div>
                        <label for="name">Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    </div>
                </div>

                <div class="customer-form__row">
                    <div>
                        <label for="phone">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" {{ $requiresCheckoutProfile ? 'required' : '' }}>
                    </div>
                    <div>
                        <label for="profile_photo">Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/*">
                    </div>
                </div>

                <div>
                    <label for="shipping_address">Shipping Address</label>
                    <textarea name="shipping_address" {{ $requiresCheckoutProfile ? 'required' : '' }}>{{ old('shipping_address', $user->shipping_address) }}</textarea>
                </div>

                <div class="customer-form__row">
                    <div>
                        <label for="shipping_city">City</label>
                        <input type="text"
                               id="shipping_city"
                               name="shipping_city"
                               value="{{ old('shipping_city', $user->shipping_city) }}"
                               {{ $requiresCheckoutProfile ? 'required' : '' }}>
                    </div>
                    <div>
                        <label for="shipping_state">State</label>
                        <input type="text"
                               id="shipping_state"
                               name="shipping_state"
                               value="{{ old('shipping_state', $user->shipping_state) }}"
                               {{ $requiresCheckoutProfile ? 'required' : '' }}>
                    </div>
                </div>

                <div class="customer-form__row">
                    <div>
                        <label for="shipping_postcode">Postcode</label>
                        <input type="text"
                               id="shipping_postcode"
                               name="shipping_postcode"
                               value="{{ old('shipping_postcode', $user->shipping_postcode) }}"
                               {{ $requiresCheckoutProfile ? 'required' : '' }}>
                    </div>
                    <div>
                        <label for="shipping_country">Country</label>
                        <input type="text"
                               id="shipping_country"
                               name="shipping_country"
                               value="{{ old('shipping_country', $user->shipping_country ?? 'Malaysia') }}"
                               {{ $requiresCheckoutProfile ? 'required' : '' }}>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </section>

        {{-- Change Password Link --}}
        <section class="customer-card customer-profile__card">
            <div class="customer-card__head">
                <h3>Security settings</h3>
                <p>Change your password on a dedicated page.</p>
            </div>

            <a href="{{ route('profile.password.edit') }}" class="btn btn-primary">Change Password</a>
        </section>
    </div>
</div>

@if($user->profile_photo)
    <div class="customer-photo-modal" data-avatar-modal>
        <button type="button" class="customer-photo-modal__backdrop" data-avatar-close></button>
        <div class="customer-photo-modal__content" role="dialog" aria-modal="true">
            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="Profile photo">
            <button type="button" class="customer-photo-modal__close" data-avatar-close>Close</button>
        </div>
    </div>

    <script>
        (function () {
            const trigger = document.querySelector('[data-avatar-trigger]');
            const modal = document.querySelector('[data-avatar-modal]');
            if (!trigger || !modal) return;

            const closeButtons = modal.querySelectorAll('[data-avatar-close]');

            const openModal = () => {
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            };

            const closeModal = () => {
                modal.classList.remove('is-open');
                document.body.style.overflow = '';
            };

            trigger.addEventListener('click', openModal);
            closeButtons.forEach(button => button.addEventListener('click', closeModal));
        })();
    </script>
@endif
@endsection
