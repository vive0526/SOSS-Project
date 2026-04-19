@extends('layouts.customer')

@section('title', 'Order Updates')
@section('page_title', 'Order Updates')
@section('page_subtitle', 'Latest updates for orders and cattle requests')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <section class="customer-section">
        <div class="customer-section__head" style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
            <h2>Recent Updates</h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn btn-outline" href="{{ route('customer.orders.index') }}">Orders</a>
                <a class="btn btn-outline" href="{{ route('customer.cattle-requests.index') }}">Cattle Requests</a>
            </div>
        </div>

        @if($events->isEmpty())
            <div class="customer-empty">No updates yet.</div>
        @else
            <div style="display:grid; gap:12px;">
                @foreach($events as $event)
                    <div class="customer-card" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">
                                {{ $event['type'] === 'cattle' ? 'Cattle Request' : 'Order' }}
                            </div>
                            <div style="font-size:16px; font-weight:800; color:#4c2f1c;">
                                {{ $event['title'] }}
                            </div>
                            <div style="color:#7b6a5b; font-size:12px;">
                                {{ $event['created_at']?->format('Y-m-d H:i') }}
                            </div>
                            @if(!empty($event['note']))
                                <div style="color:#5e4a3b; margin-top:6px;">
                                    {{ $event['note'] }}
                                </div>
                            @endif
                        </div>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <span class="customer-status customer-status--{{ $event['status'] }}">
                                {{ ucfirst($event['status']) }}
                            </span>
                            @if(!empty($event['url']))
                                <a class="btn btn-outline" href="{{ $event['url'] }}">View</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

