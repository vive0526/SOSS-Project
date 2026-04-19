@extends('layouts.admin')

@section('content')

<h2>Edit Code</h2>

@if($errors->any())
    <ul style="color:red;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('codes.update', $code) }}">
    @csrf
    @method('PUT')

    <div>
        <label>Code *</label><br>
        <input type="text" name="code"
               value="{{ old('code', $code->code) }}" required>
    </div>

    <div>
        <label>Description *</label><br>
        <input type="text" name="description"
               value="{{ old('description', $code->description) }}" required>
    </div>

    <div>
        <label>Category *</label><br>
        <input type="text" name="category"
               value="{{ old('category', $code->category) }}" required>
    </div>

    <div>
        <label>Color *</label><br>
        <input type="text" name="color"
               value="{{ old('color', $code->color) }}"
               placeholder="#FF0000" required>
    </div>

    <button type="submit">Update</button>
</form>

@endsection
