<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register | SOSS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <h2>Create Account</h2>
        <p style="text-align:center; color:#ccc; margin-bottom:20px;">
        Create your account in seconds
        </p>

        @if ($errors->any())
            <ul style="color:red; margin-bottom:10px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <label>Name</label>
            <input type="text" name="name" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" required>

            <button class="btn btn-primary" type="submit"   onclick="this.classList.add('loading')">Register</button>
        </form>

        <div class="auth-footer">
            <p>Already have an account?
                <a href="{{ route('login') }}">Login</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
