@extends('layouts.admin')

@section('content')

<h2>User Management</h2>

<div class="admin-card">

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

@php
    $isStaff = auth()->user()->role === 'staff';
    $isAdmin = auth()->user()->role === 'admin';
    $editRoute = $isStaff ? 'staff.users.edit' : 'users.edit';
    $deactivateRoute = $isStaff ? 'staff.users.deactivate' : 'users.deactivate';
    $activateRoute = $isStaff ? 'staff.users.activate' : 'users.activate';
@endphp

<table border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>No</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            @if($isAdmin || $isStaff)
                <th>Actions</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($users as $index => $user)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ ucfirst($user->role) }}</td>
            <td>{{ ucfirst($user->status) }}</td>

            @if($isAdmin || $isStaff)
            <td>
                @if($isAdmin || $user->role === 'customer')
                <div class="admin-card">
                <a href="{{ route($editRoute, $user) }}">Edit</a>

                @if($user->status === 'active')
                    <form action="{{ route($deactivateRoute, $user) }}"
                          method="POST" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            onclick="return confirm('Deactivate this user?')">
                            Deactivate
                        </button>
                    </form>
                @else
                    <form action="{{ route($activateRoute, $user) }}"
                          method="POST" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            onclick="return confirm('Activate this user?')">
                            Activate
                        </button>
                    </form>
                @endif
                </div>
                @else
                    <span>-</span>
                @endif
            </td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
