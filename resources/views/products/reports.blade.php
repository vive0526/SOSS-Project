@extends('layouts.admin')

@section('title', 'Product Reports')
@section('page_title', 'Product Reports')
@section('page_subtitle', 'View product statistics')

@section('content')
    <div class="admin-card">
        <h3>Total Products: {{ $totalProducts }}</h3>
        <h3>Low Stock Products: {{ count($lowStock) }}</h3>
        <h3>Out-of-Stock Products: {{ count($outOfStock) }}</h3>

        <h4>Low Stock Products</h4>
        <ul>
            @foreach($lowStock as $product)
                <li>{{ $product->name }} ({{ $product->stock_quantity }} left)</li>
            @endforeach
        </ul>

        <h4>Out-of-Stock Products</h4>
        <ul>
            @foreach($outOfStock as $product)
                <li>{{ $product->name }}</li>
            @endforeach
        </ul>
    </div>
@endsection
