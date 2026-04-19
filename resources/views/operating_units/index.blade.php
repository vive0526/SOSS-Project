@extends('layouts.admin')

@section('content')

<h2>Operating Units</h2>

<div class="admin-card">

<a class="btn-add" href="{{ route('operating-units.create') }}">Add Operating Unit</a>

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

<table border="1" cellpadding="10" cellspacing="0">
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
            <td>{{ $index + 1 }}</td>
            <td>{{ $unit->name }}</td>
            <td>{{ $unit->code }}</td>
            <td>{{ ucfirst($unit->status) }}</td>
            <td>
                <a href="{{ route('operating-units.edit', $unit) }}">Edit</a>

                @if($unit->status === 'active')
                    <form method="POST"
                          action="{{ route('operating-units.deactivate', $unit) }}"
                          style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
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
                        <button type="submit">
                            Activate
                        </button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
