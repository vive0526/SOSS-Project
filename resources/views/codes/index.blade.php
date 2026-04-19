@extends('layouts.admin')

@section('content')

<h2>Codes</h2>

<div class="admin-card">
    
  <a class="btn-add" href="{{ route('codes.create') }}">Add Code</a>

@if(session('success'))
    <p style="color: green;">{{ session('success') }}</p>
@endif

<table border="1" cellpadding="10" cellspacing="0">
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
            <td>{{ $index + 1 }}</td>
            <td>{{ $code->code }}</td>
            <td>{{ $code->description }}</td>
            <td>{{ ucfirst($code->status) }}</td>
            <td>
                <a href="{{ route('codes.edit', $code) }}">Edit</a>

                @if($code->status === 'active')
                    <form method="POST"
                          action="{{ route('codes.deactivate', $code) }}"
                          style="display:inline;">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
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
                        <button type="submit">Activate</button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@endsection
