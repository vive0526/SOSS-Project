@extends('layouts.admin')

@section('content')

<h2>Edit Category</h2>

@if($errors->any())
    <ul style="color:red;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('categories.update', $category) }}">
    @csrf
    @method('PUT')

    <div>
        <label>Name *</label><br>
        <input type="text" name="name" value="{{ old('name', $category->name) }}" required>
    </div>

    <button type="submit">Update</button>
</form>

@endsection
