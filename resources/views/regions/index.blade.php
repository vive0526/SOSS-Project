@extends('layouts.admin')

@section('content')

<h2>Regions</h2>

<div class="admin-card">

<a class="btn-add" href="{{ route('regions.create') }}">Add Region</a>

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
        @foreach($regions as $index => $region)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $region->name }}</td>
            <td>{{ $region->code }}</td>
            <td>{{ ucfirst($region->status) }}</td>
            <td>
                <a href="{{ route('regions.edit', $region) }}">Edit</a>

                @if($region->status === 'active')
                    <form method="POST" action="{{ route('regions.deactivate', $region) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            onclick="return confirm('Deactivate this region?')">
                            Delete
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('regions.activate', $region) }}" style="display:inline;">
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
