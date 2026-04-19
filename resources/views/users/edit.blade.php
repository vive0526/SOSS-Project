@extends('layouts.admin')

@section('title', 'Edit User')
@section('page_title', 'Edit User')
@section('page_subtitle', 'Update user information')

@section('content')
    <div class="admin-card">
        @php
            $updateRoute = auth()->user()->role === 'staff' ? 'staff.users.update' : 'users.update';
        @endphp
        <form method="POST" action="{{ route($updateRoute, $user) }}">
            @csrf
            @method('PUT')

            <div>
                <label for="name">Full Name</label><br>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </div>

            <div>
                <label for="email">Email</label><br>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>

            <div>
                <label for="phone">Phone Number</label><br>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}">
            </div>

            <div>
                <label for="shipping_address">Shipping Address</label><br>
                <textarea name="shipping_address">{{ old('shipping_address', $user->shipping_address) }}</textarea>
            </div>

            <div>
                <label for="role">Role</label><br>
                @if(auth()->user()->role === 'admin')
                    <select name="role" required>
                        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="staff" {{ $user->role === 'staff' ? 'selected' : '' }}>Staff</option>
                        <option value="customer" {{ $user->role === 'customer' ? 'selected' : '' }}>Customer</option>
                    </select>
                @else
                    <input type="text" name="role" value="customer" readonly>
                @endif
            </div>

            <div>
                <label for="status">Status</label><br>
                <select name="status" required>
                    <option value="active" {{ $user->status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $user->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <label for="password">New Password</label><br>
                <input type="password" name="password">
            </div>

            <div>
                <label for="password_confirmation">Confirm New Password</label><br>
                <input type="password" name="password_confirmation">
            </div>

            <button type="submit" class="btn btn-primary">Update User</button>
        </form>
    </div>
@endsection
