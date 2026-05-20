<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 | Access denied</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-body">
    @php
        $user = auth()->user();
        $homeUrl = url('/');
        $recommendedUrl = $user
            ? match ($user->role) {
                'admin' => url('/admin/dashboard'),
                'staff' => url('/staff/dashboard'),
                'customer' => url('/customer/dashboard'),
                default => $homeUrl,
            }
            : route('login');
    @endphp

    <main style="min-height: 100vh; display:flex; align-items:center; justify-content:center; padding: 32px;">
        <div style="max-width: 640px; width: 100%;">
            <div class="customer-alert customer-alert--error">
                <div class="customer-alert__title">403 — No access</div>
                <div style="margin-bottom: 12px;">
                    You don’t have permission to view this page.
                </div>
                <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                    <a class="btn btn-primary" href="{{ $recommendedUrl }}">
                        Go to your dashboard
                    </a>
                    <a class="btn btn-outline" href="{{ $homeUrl }}">
                        Back to home
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

