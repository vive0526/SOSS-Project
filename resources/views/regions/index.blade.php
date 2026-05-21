@extends('layouts.admin')

@section('title', 'Regions')
@section('page_title', 'Regions')
@section('page_subtitle', 'Manage region master data')

@section('content')
<div class="admin-card">

<a class="btn-add" href="{{ route('regions.create') }}">Add Region</a>

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
        @foreach($regions as $index => $region)
        <tr>
            <td>{{ ($regions->firstItem() ?? 0) + $index }}</td>
            <td>{{ $region->name }}</td>
            <td>{{ $region->code }}</td>
            <td>{{ ucfirst($region->status) }}</td>
            <td>
                <a class="btn-admin btn-edit" href="{{ route('regions.edit', $region) }}">Edit</a>

                @if($region->status === 'active')
                    <form method="POST" action="{{ route('regions.deactivate', $region) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="btn-admin btn-delete"
                            onclick="return confirm('Deactivate this region?')">
                            Delete
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('regions.activate', $region) }}" style="display:inline;">
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
    {{ $regions->links('pagination.admin') }}
</div>

@endsection
