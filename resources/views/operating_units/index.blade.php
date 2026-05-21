@extends('layouts.admin')

@section('title', 'Operating Units')
@section('page_title', 'Operating Units')
@section('page_subtitle', 'Manage mills, estates, and departments')

@section('content')
<div class="admin-card">

<a class="btn-add" href="{{ route('operating-units.create') }}">Add Operating Unit</a>

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Name</th>
            <th>Code</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        @foreach($units as $index => $unit)
        <tr>
            <td>{{ ($units->firstItem() ?? 0) + $index }}</td>
            <td>{{ $unit->name }}</td>
            <td>{{ $unit->code }}</td>
            <td>{{ ucfirst($unit->status) }}</td>
            <td>
                <a class="btn-admin btn-edit" href="{{ route('operating-units.edit', $unit) }}">Edit</a>

                @if($unit->status === 'active')
                    <form method="POST"
                          action="{{ route('operating-units.deactivate', $unit) }}"
                          style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="btn-admin btn-delete"
                                onclick="return confirm('Deactivate this operating unit?')">
                            Delete
                        </button>
                    </form>
                @else
                    <form method="POST"
                          action="{{ route('operating-units.activate', $unit) }}"
                          style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-admin btn-activate">
                            Activate
                        </button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div style="margin-top: 12px;">
    {{ $units->links('pagination.admin') }}
</div>

@endsection
