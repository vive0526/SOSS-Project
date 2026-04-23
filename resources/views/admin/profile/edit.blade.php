@extends('layouts.admin')

@section('title', 'Edit Profile')
@section('page_title', 'Edit Profile')
@section('page_subtitle', 'Update your photo, contact and security settings')
@section('body_class', 'admin-body--profile')

@section('content')
    @php
        $user = auth()->user();
    @endphp

    <div class="admin-profile-page">
        <div class="admin-card admin-profile-card admin-profile-card--wide">
            <div class="admin-profile-head">
                <div>
                    <h2 class="admin-profile-title">Profile Settings</h2>
                    <p class="admin-profile-sub">Keep your account info up to date.</p>
                </div>

                <a href="{{ url('/admin/dashboard') }}" class="btn-admin btn-edit">
                    < Back to Dashboard
                </a>
            </div>

            <div class="admin-profile-sections">
                <section class="admin-profile-section">
                    <h3>Profile Information</h3>
                    @include('profile.partials.update-profile-information-form')
                </section>

                <section class="admin-profile-section">
                    <h3>Password</h3>
                    @include('profile.partials.update-password-form')
                </section>

                <section class="admin-profile-section">
                    <h3>Delete Account</h3>
                    @include('profile.partials.delete-user-form')
                </section>
            </div>
        </div>
    </div>
@endsection
