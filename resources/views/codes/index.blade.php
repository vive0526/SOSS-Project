@extends('layouts.admin')

@section('title', 'Codes')
@section('page_title', 'Codes')
@section('page_subtitle', 'Manage codes and tags used across the system')

@section('content')
<div class="admin-card">
    
  <a class="btn-add" href="{{ route('codes.create') }}">Add Code</a>

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Code</th>
            <th>Name</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        @foreach($codes as $index => $code)
        <tr>
            <td>{{ ($codes->firstItem() ?? 0) + $index }}</td>
            <td>{{ $code->code }}</td>
            <td>{{ $code->description }}</td>
            <td>{{ ucfirst($code->status) }}</td>
            <td>
                <a class="btn-admin btn-edit" href="{{ route('codes.edit', $code) }}">Edit</a>

                @if($code->status === 'active')
                    <form method="POST"
                          action="{{ route('codes.deactivate', $code) }}"
                          style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="btn-admin btn-delete"
                                onclick="return confirm('Deactivate this code?')">
                            deactivate
                        </button>
                    </form>
                @else
                    <form method="POST"
                          action="{{ route('codes.activate', $code) }}"
                          style="display:inline;">
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
    {{ $codes->links('pagination.admin') }}
</div>

@endsection
