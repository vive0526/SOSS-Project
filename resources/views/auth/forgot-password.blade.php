<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password | SOSS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon">🔑</div>
        <h2>Forgot Password?</h2>
        <p class="auth-subtitle">
            No problem. Enter your email and we'll send you a reset link.
        </p>

        @if (session('status'))
            <div class="auth-alert auth-alert--success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="auth-alert auth-alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <button class="btn btn-primary" type="submit">
                Send Reset Link
            </button>
        </form>

        <div class="auth-footer">
            <p>Remember your password? <a href="{{ route('login') }}">Back to Login</a></p>
        </div>
    </div>
</div>

</body>
</html>
