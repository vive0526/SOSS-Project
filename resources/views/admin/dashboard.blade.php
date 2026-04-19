@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page_title', 'Admin Dashboard')
@section('page_subtitle', 'Control. Monitor. Improve.')

@section('content')
    <div class="admin-welcome">
        <h1 class="admin-welcome__title">Welcome back, {{ auth()->user()->name }}.</h1>
        <p class="admin-welcome__quote">
            “Simplicity is the ultimate sophistication.”
        </p>
    </div>
@endsection
