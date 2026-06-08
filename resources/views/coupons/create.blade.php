@extends('layouts.admin')

@section('title', 'Create Coupon')
@section('page_title', 'Create Coupon')
@section('page_subtitle', 'Add a customer discount coupon')

@section('content')
<form method="POST" action="{{ route('coupons.store') }}">
    @csrf
    @include('coupons._form')
</form>
@endsection
