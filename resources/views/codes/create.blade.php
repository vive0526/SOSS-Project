@extends('layouts.admin')

@section('content')

<h2>Add Code</h2>

@if($errors->any())
    <ul style="color:red;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('codes.store') }}">
    @csrf

    <div>
        <label>Code *</label><br>
        <input type="text" name="code" value="{{ old('code') }}" required>
    </div>

    <div>
        <label>Description *</label><br>
        <input type="text" name="description" value="{{ old('description') }}" required>
    </div>

    <div>
        <label>Category *</label><br>
        <input type="text" name="category" value="{{ old('category') }}" required>
    </div>

    <div>
        <label>Color *</label><br>
        <input type="text" name="color"
               placeholder="#FF0000"
               value="{{ old('color') }}" required>
        <small>Format: #RRGGBB or #RGB</small>
    </div>

    <button type="submit">Save</button>
</form>

@endsection
