@extends('layouts.admin')

@section('content')

<h2>Company</h2>

<div class="admin-card">

<a class="btn-add" href="{{ route('companies.create') }}">Add Company</a>

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

<table border="1" cellpadding="10" cellspacing="0">
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
            <td>{{ $index + 1 }}</td>
            <td>{{ $company->name }}</td>
            <td>{{ $company->code }}</td>
            <td>{{ ucfirst($company->status) }}</td>
            <td>
                <a href="{{ route('companies.edit', $company) }}">Edit</a>

                @if($company->status === 'active')
                    <form method="POST" action="{{ route('companies.deactivate', $company) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit" onclick="return confirm('Deactivate this company?')">Delete</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('companies.activate', $company) }}" style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit">Activate</button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
