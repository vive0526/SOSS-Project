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
                <input type="text" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                       class="js-flatpickr-date" placeholder="YYYY-MM-DD" autocomplete="off">
            </div>
            <div>
                <label for="date_to">To</label>
                <input type="text" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                       class="js-flatpickr-date" placeholder="YYYY-MM-DD" autocomplete="off">
            </div>
            <div class="admin-export-actions">
                <a class="btn btn-outline" target="_blank" rel="noopener" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'pdf'])) }}">Export PDF</a>
                <a class="btn btn-outline" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'excel'])) }}">Export Excel</a>
                <a class="btn btn-outline" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'daily_csv'])) }}">Export Daily CSV</a>
                <a class="btn btn-outline" href="{{ route('orders.reports.summary', array_merge(request()->query(), ['export' => 'status_csv'])) }}">Export Status CSV</a>
            </div>
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
                <div style="color:#bfbfbf; font-size:12px;">Payments Paid</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentPaidCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Payments Pending</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentPendingCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Payments Unpaid</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentUnpaidCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Refund Pending</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentRefundPendingCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Partial Refund</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentPartialRefundCount }}</div>
            </div>
            <div>
                <div style="color:#bfbfbf; font-size:12px;">Refunded</div>
                <div style="font-size:24px; font-weight:700;">{{ $paymentRefundedCount }}</div>
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

    <script>
        (function () {
            const form = document.querySelector('form[action="{{ route('orders.reports.summary') }}"]');
            if (!form) return;

            const fields = form.querySelectorAll('input[name="date_from"], input[name="date_to"]');
            if (!fields.length) return;

            let t = null;
            const submitSoon = () => {
                if (t) window.clearTimeout(t);
                t = window.setTimeout(() => {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }, 200);
            };

            fields.forEach((el) => {
                el.addEventListener('change', submitSoon);
                el.addEventListener('blur', submitSoon);
            });
        })();
    </script>
@endsection
