@extends('layouts.admin')

@section('content')

<h2>Edit Operating Unit</h2>

<form method="POST" action="{{ route('operating-units.update', $operatingUnit) }}">
    @csrf
    @method('PUT')

    <div>
        <label>Name</label><br>
        <input type="text" name="name"
               value="{{ $operatingUnit->name }}" required>
    </div>

    <div>
        <label>Code</label><br>
        <input type="text" name="code"
               value="{{ $operatingUnit->code }}" required>
    </div>

    <div>
        <label>Type of Unit</label><br>
        <select name="type" required>
            @foreach(['mill','estate','department','other'] as $type)
                <option value="{{ $type }}"
                    {{ $operatingUnit->type === $type ? 'selected' : '' }}>
                    {{ ucfirst($type) }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label>Address</label><br>
        <textarea name="address">{{ $operatingUnit->address }}</textarea>
    </div>

    <div>
        <label>Manager</label><br>
        <input type="text" name="manager"
               value="{{ $operatingUnit->manager }}" required>
    </div>

    <div>
        <label>Region</label><br>
        <select name="region_id" required>
            @foreach($regions as $region)
                <option value="{{ $region->id }}"
                    {{ $operatingUnit->region_id == $region->id ? 'selected' : '' }}>
                    {{ $region->name }} ({{ $region->code }})
                </option>
            @endforeach
        </select>
    </div>

    <button type="submit">Update</button>
</form>

@endsection
