@extends('layouts.admin')

@section('title', 'Order Summary Report')
@section('page_title', 'Order Summary')
@section('page_subtitle', 'Totals, trends, and status breakdowns')

@section('content')
    <div class="admin-card">
        <form method="GET" action="{{ route('orders.reports.summary') }}"
              style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label for="date_from">From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="date_to">To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a class="btn" href="{{ route('orders.reports.summary') }}">Reset</a>
            <a class="btn btn-outline" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'daily_csv'])) }}">Export Daily CSV</a>
            <a class="btn btn-outline" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'status_csv'])) }}">Export Status CSV</a>
            <a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'pdf'])) }}">Export PDF</a>
            <a class="btn btn-outline" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'excel'])) }}">Export Excel</a>
        </form>
    </div>

    <div class="admin-card">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Total Orders</div>
                <div style="font-size:24px; font-weight:700;">{{ $totalOrders }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Total Order Value</div>
                <div style="font-size:24px; font-weight:700;">
                    RM {{ number_format((float) $totalValue, 2) }}
                </div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Payments Verified</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentVerifiedCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Payments Unverified</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentUnverifiedCount }}</div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Status Breakdown</h3>
        <table>
            <thead>
            <tr>
                <th>Status</th>
                <th>Count</th>
            </tr>
            </thead>
            <tbody>
            @foreach($statuses as $status)
                <tr>
                    <td>{{ ucfirst($status) }}</td>
                    <td>{{ $statusCounts[$status] ?? 0 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 12px;">Daily Summary</h3>
        @if($dailySummary->isEmpty())
            <p>No orders found for the selected period.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Orders</th>
                    <th>Total Value</th>
                </tr>
                </thead>
                <tbody>
                @foreach($dailySummary as $day)
                    <tr>
                        <td>{{ $day->report_date }}</td>
                        <td>{{ $day->total_orders }}</td>
                        <td>RM {{ number_format((float) $day->total_value, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
