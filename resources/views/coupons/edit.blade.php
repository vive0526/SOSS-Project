@extends('layouts.admin')

@section('title', 'Edit Coupon')
@section('page_title', 'Edit Coupon')
@section('page_subtitle', 'Update coupon rules and availability')

@section('content')
<form method="POST" action="{{ route('coupons.update', $coupon) }}">
    @csrf
    @method('PUT')
    @include('coupons._form')
</form>
@endsection
