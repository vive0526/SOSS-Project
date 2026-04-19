@extends('layouts.admin')

@section('content')

<h2>Edit Region</h2>

<form method="POST" action="{{ route('regions.update', $region) }}">
    @csrf
    @method('PUT')

    <div>
        <label>Name</label><br>
        <input type="text" name="name" value="{{ $region->name }}" required>
    </div>

    <div>
        <label>Code</label><br>
        <input type="text" name="code" value="{{ $region->code }}" required>
    </div>

    <button type="submit">Update</button>
</form>

@endsection
