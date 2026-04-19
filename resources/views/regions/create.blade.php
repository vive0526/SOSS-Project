@extends('layouts.admin')

@section('content')

<h2>Add Region</h2>

<form method="POST" action="{{ route('regions.store') }}">
    @csrf

    <div>
        <label>Name</label><br>
        <input type="text" name="name" required>
    </div>

    <div>
        <label>Code</label><br>
        <input type="text" name="code" required>
    </div>

    <button type="submit">Save</button>
</form>

@endsection
