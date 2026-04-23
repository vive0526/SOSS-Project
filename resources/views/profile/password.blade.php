@extends((auth()->check() && in_array(auth()->user()->role, ['staff', 'admin'], true)) ? 'layouts.admin' : 'layouts.customer')

@section('title', 'Change Password')
@section('page_title', 'Change Password')
@section('page_subtitle', 'Update your account password')

@section('content')
<div class="customer-profile">
    <section class="customer-profile__intro">
        <div class="customer-profile__intro-text">
            <span class="customer-pill">Security</span>
            <h2 class="customer-profile__title">Change password</h2>
            <p class="customer-profile__subtitle">Use a strong password you don't use anywhere else.</p>
        </div>

        <div class="customer-profile__actions">
            <a href="{{ route('profile.edit') }}" class="btn btn-outline">Back to Profile</a>
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

    <section class="customer-card customer-profile__card">
        <div class="customer-card__head">
            <h3>Security settings</h3>
            <p>Enter your current password and choose a new one.</p>
        </div>

        <form class="customer-form" action="{{ route('profile.password.update') }}" method="POST">
            @csrf
            @method('PATCH')

            <div>
                <label for="current_password">Current Password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            </div>

            <div>
                <label for="new_password">New Password</label>
                <input id="new_password" type="password" name="new_password" autocomplete="new-password" required>
            </div>

            <div>
                <label for="new_password_confirmation">Confirm New Password</label>
                <input id="new_password_confirmation" type="password" name="new_password_confirmation" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </section>
</div>
@endsection
