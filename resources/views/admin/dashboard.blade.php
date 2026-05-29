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

    @php
        $low = (int) ($adminLowStockCount ?? 0);
        $out = (int) ($adminOutOfStockCount ?? 0);
    @endphp
    @if($low > 0 || $out > 0)
        <div class="admin-card" style="margin-top:16px;">
            <h3 style="margin-bottom:10px;">Inventory Alert</h3>
            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                @if($low > 0)
                    <span class="status-low">Low Stock: {{ $low }}</span>
                @endif
                @if($out > 0)
                    <span class="status-inactive">Out of Stock: {{ $out }}</span>
                @endif
                <a class="btn btn-outline" href="{{ route('products.inventory') }}">Open Inventory</a>
            </div>
        </div>
    @endif
@endsection

