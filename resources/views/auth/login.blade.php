<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login | SOSS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <h2>Login</h2>
        <p style="text-align:center; color:#ccc; margin-bottom:20px;">
        Secure access to your dashboard
        </p>

        @if (session('warning'))
            <p style="color:#d6c7b8; margin-bottom:10px; text-align:center;">
                {{ session('warning') }}
            </p>
        @endif

        @if ($errors->any())
            <p style="color:red; margin-bottom:10px;">
                {{ $errors->first() }}
            </p>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                <a href="{{ route('password.request') }}" style="font-size:12px; color:#d6c7b8; text-decoration:underline;">
                    Forgot password?
                </a>
            </div>

            <button class="btn btn-primary" type="submit" onclick="this.classList.add('loading')">Sign In</button>
        </form>

        <div class="auth-footer">
            <p>Don’t have an account?
                <a href="{{ route('register') }}">Register</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
