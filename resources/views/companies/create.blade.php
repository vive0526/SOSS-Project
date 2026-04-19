@extends('layouts.admin')

@section('content')

<h2>Add Company</h2>

@if($errors->any())
    <ul style="color:red;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('companies.store') }}">
    @csrf

    <div>
        <label>Company Name *</label><br>
        <input type="text" name="name" value="{{ old('name') }}" required>
    </div>

    <div>
        <label>Code *</label><br>
        <input type="text" name="code" value="{{ old('code') }}" required>
    </div>

    <div>
        <label>ROC Number</label><br>
        <input type="text" name="roc_number" value="{{ old('roc_number') }}">
    </div>

    <div>
        <label>Address</label><br>
        <textarea name="address">{{ old('address') }}</textarea>
    </div>

    <div>
        <label>Parent Company</label><br>
        <select name="parent_company_id">
            <option value="">-- No Parent --</option>
            @foreach($parentCompanies as $parent)
                <option value="{{ $parent->id }}" {{ old('parent_company_id') == $parent->id ? 'selected' : '' }}>
                    {{ $parent->name }} ({{ $parent->code }})
                </option>
            @endforeach
        </select>
    </div>

    <button type="submit">Save</button>
</form>

@endsection
