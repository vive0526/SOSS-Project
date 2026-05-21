@extends('layouts.admin')

@section('title', 'Categories')
@section('page_title', 'Categories')
@section('page_subtitle', 'Manage product categories')

@section('content')
<div class="admin-card">
    <a class="btn-add" href="{{ route('categories.create') }}">Add Category</a>

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif

    @if(session('error'))
        <p style="color: red;">{{ session('error') }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $index => $category)
                <tr>
                    <td>{{ ($categories->firstItem() ?? 0) + $index }}</td>
                    <td>{{ $category->name }}</td>
                    <td>
                        <a class="btn-admin btn-edit" href="{{ route('categories.edit', $category) }}">Edit</a>
                        <form method="POST"
                              action="{{ route('categories.destroy', $category) }}"
                              style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="btn-admin btn-delete"
                                    onclick="return confirm('Delete this category?')">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 12px;">
        {{ $categories->links('pagination.admin') }}
    </div>
</div>

@endsection
