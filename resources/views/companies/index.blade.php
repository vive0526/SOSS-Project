@extends('layouts.admin')

@section('title', 'Companies')
@section('page_title', 'Companies')
@section('page_subtitle', 'Manage company master data')

@section('content')
<div class="admin-card">

<a class="btn-add" href="{{ route('companies.create') }}">Add Company</a>

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Company Name</th>
            <th>Code</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        @foreach($companies as $index => $company)
        <tr>
            <td>{{ ($companies->firstItem() ?? 0) + $index }}</td>
            <td>{{ $company->name }}</td>
            <td>{{ $company->code }}</td>
            <td>{{ ucfirst($company->status) }}</td>
            <td>
                <a class="btn-admin btn-edit" href="{{ route('companies.edit', $company) }}">Edit</a>

                @if($company->status === 'active')
                    <form method="POST" action="{{ route('companies.deactivate', $company) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-admin btn-delete" onclick="return confirm('Deactivate this company?')">Delete</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('companies.activate', $company) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-admin btn-activate">Activate</button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div style="margin-top: 12px;">
    {{ $companies->links('pagination.admin') }}
</div>

@endsection
