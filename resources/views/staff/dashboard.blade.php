@extends('layouts.admin')

@section('content')
    <h1>Staff Dashboard</h1>
    <p>Welcome, {{ auth()->user()->name }}</p>
@endsection
