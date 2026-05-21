@extends('layouts.admin')

@section('title', 'User Management')
@section('page_title', 'User Management')
@section('page_subtitle', 'Manage admin, staff, and customer accounts')

@section('content')
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

<table>
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
            <td>{{ ($users->firstItem() ?? 0) + $index }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ ucfirst($user->role) }}</td>
            <td>{{ ucfirst($user->status) }}</td>

            @if($isAdmin || $isStaff)
            <td>
                @if($isAdmin || $user->role === 'customer')
                <a class="btn-admin btn-edit" href="{{ route($editRoute, $user) }}">Edit</a>

                @if($user->status === 'active')
                    <form action="{{ route($deactivateRoute, $user) }}"
                          method="POST" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="btn-admin btn-delete"
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
                            class="btn-admin btn-activate"
                            onclick="return confirm('Activate this user?')">
                            Activate
                        </button>
                    </form>
                @endif
                @else
                    <span>-</span>
                @endif
            </td>
            @endif
        </tr>
        @endforeach
    </tbody>
</table>

<div style="margin-top: 12px;">
    {{ $users->links('pagination.admin') }}
</div>

@endsection
