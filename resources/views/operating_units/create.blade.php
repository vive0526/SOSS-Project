@extends('layouts.admin')

@section('content')

<h2>Add Operating Unit</h2>

<form method="POST" action="{{ route('operating-units.store') }}">
    @csrf

    <div>
        <label>Operating Unit Name*</label><br>
        <input type="text" name="name" required>
    </div>

    <div>
        <label>Code Name*</label><br>
        <input type="text" name="code" required>
    </div>

    <div>
        <label>Type of Unit</label><br>
        <select name="type" required>
            <option value="">-- Select --</option>
            <option value="mill">Mill</option>
            <option value="estate">Estate</option>
            <option value="department">Department</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div>
        <label>Address</label><br>
        <textarea name="address"></textarea>
    </div>

    <div>
        <label>Manager</label><br>
        <input type="text" name="manager" required>
    </div>

    <div>
        <label>Region</label><br>
        <select name="region_id" required>
            <option value="">-- Select Region --</option>
            @foreach($regions as $region)
                <option value="{{ $region->id }}">
                    {{ $region->name }} ({{ $region->code }})
                </option>
            @endforeach
        </select>
    </div>

    <button type="submit">Save</button>
</form>

@endsection
