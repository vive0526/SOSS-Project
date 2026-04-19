@extends('layouts.admin')

@section('content')

<h2>Add Category</h2>

@if($errors->any())
    <ul style="color:red;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('categories.store') }}">
    @csrf

    <div>
        <label>Name *</label><br>
        <input type="text" name="name" value="{{ old('name') }}" required>
    </div>

    <button type="submit">Save</button>
</form>

@endsection
