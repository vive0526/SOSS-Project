@extends('layouts.admin')

@section('title', 'Create User')
@section('page_title', 'Create New User')
@section('page_subtitle', 'Add a new customer')

@section('content')
    <div class="admin-card">
        @php
            $storeRoute = auth()->user()->role === 'staff' ? 'staff.users.store' : 'users.store';
        @endphp
        <form action="{{ route($storeRoute) }}" method="POST">
            @csrf

            <label for="name">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required>

            <label for="phone">Phone Number</label>
            <input type="text" name="phone" value="{{ old('phone') }}">

            <label for="shipping_address">Shipping Address</label>
            <textarea name="shipping_address">{{ old('shipping_address') }}</textarea>

            <label for="role">Role</label>
            <input type="text" name="role" value="customer" readonly>

            <label for="status">Status</label>
            <select name="status" required>
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <label for="password">Password</label>
            <input type="password" name="password" required>

            <label for="password_confirmation">Confirm Password</label>
            <input type="password" name="password_confirmation" required>

            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
@endsection
