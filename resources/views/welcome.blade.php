<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sawit Online Sales System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<header class="guest-header">
    <h2>SOSS</h2>
    <nav>
        <a href="#">About</a>
        <a href="#">Contact</a>
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                Dashboard
            </a>
        @else
            <a href="{{ route('login') }}">Login</a>
            <a href="{{ route('register') }}" class="btn btn-outline">
                Register
            </a>
        @endauth
    </nav>
</header>

<section class="hero">
    <div>
        <h1>Sawit Online Sales & Management System</h1>
        <p>
            A secure and efficient platform for managing products, orders,
            inventory, and operations - built for enterprise and SMEs.
        </p>
        <div class="hero-actions" style="animation: fadeUp 1s ease-out;">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">
                Go to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">
                Login
                </a>
                <a href="{{ route('register') }}" class="btn btn-outline">
                 Register
                </a>
            @endauth
        </div>
    </div>

    <div>
        <div class="product-grid">
            <div class="product-card">
                <h3>Fast Ordering</h3>
                <p>Streamlined ordering process for customers and staff.</p>
            </div>
            <div class="product-card">
                <h3>Secure Access</h3>
                <p>Role-based authentication for admin, staff, and customers.</p>
            </div>
            <div class="product-card">
                <h3>Real-time Control</h3>
                <p>Monitor inventory, payments, and operations in real time.</p>
            </div>
        </div>
    </div>
</section>

</body>
</html>

