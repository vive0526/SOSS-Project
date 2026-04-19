<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 24px; }
        h1 { margin: 0 0 12px; font-size: 20px; }
        h2 { margin: 18px 0 8px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 8px; font-size: 12px; vertical-align: top; }
        th { background: #f2f2f2; text-align: left; }
        .meta { margin: 0 0 12px; font-size: 12px; color: #444; }
        @media print {
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
<h1>{{ $title }}</h1>
<p class="meta">Generated: {{ now()->format('Y-m-d H:i') }}</p>

@foreach($tables as $table)
    @if(!empty($table['title']))
        <h2>{{ $table['title'] }}</h2>
    @endif
    <table>
        <thead>
        <tr>
            @foreach($table['headers'] as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($table['rows'] as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($table['headers']) }}">No data.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endforeach

<script>
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
