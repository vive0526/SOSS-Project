<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | SOSS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="auth-wrapper forgot-password-page">
    <div class="auth-card forgot-password-card">
        <div class="auth-icon" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                <path d="M7.5 10V7.5C7.5 5 9.5 3 12 3s4.5 2 4.5 4.5V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M6.5 10h11A2.5 2.5 0 0 1 20 12.5v5A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-5A2.5 2.5 0 0 1 6.5 10Z" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 14v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        </div>

        <h2>Reset your password</h2>
        <p class="auth-subtitle">
            Enter your account email and we will send you a secure link to create a new password.
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

            <label for="email">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="you@example.com"
                autocomplete="email"
                required
                autofocus
            >

            <button class="btn btn-primary" type="submit" onclick="this.classList.add('loading')">
                Send Reset Link
            </button>
        </form>

        <div class="forgot-password-note">
            <span>Check your inbox and spam folder after submitting.</span>
        </div>

        <div class="auth-footer">
            <p>Remembered your password?
                <a href="{{ route('login') }}">Back to Login</a>
            </p>
        </div>
    </div>
</div>

<style>
    .forgot-password-page {
        padding: 24px;
        background:
            radial-gradient(720px 420px at 50% 0%, rgba(212, 175, 55, 0.18), transparent 62%),
            linear-gradient(145deg, #080808 0%, #14110d 45%, #0f0f0f 100%);
    }

    .forgot-password-card {
        width: min(100%, 430px);
        padding: 38px;
        border-radius: 18px;
        border: 1px solid rgba(212, 175, 55, 0.18);
        background: rgba(26, 26, 26, 0.94);
        box-shadow: 0 28px 80px rgba(0, 0, 0, 0.55);
    }

    .forgot-password-card h2 {
        margin-bottom: 10px;
        font-size: 1.75rem;
        letter-spacing: -0.02em;
    }

    .forgot-password-card label {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        color: #efe3d8;
    }

    .forgot-password-card input {
        padding: 13px 14px;
        margin-top: 0;
        margin-bottom: 18px;
        border-radius: 10px;
        border-color: rgba(255, 255, 255, 0.12);
        background: rgba(10, 10, 10, 0.78);
    }

    .forgot-password-card input::placeholder {
        color: rgba(204, 204, 204, 0.55);
    }

    .forgot-password-card .btn {
        border: 0;
        cursor: pointer;
        border-radius: 10px;
        padding: 13px 18px;
        font-size: 0.95rem;
    }

    .forgot-password-note {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid rgba(212, 175, 55, 0.18);
        background: rgba(212, 175, 55, 0.08);
        color: #d9cfc6;
        font-size: 0.88rem;
        line-height: 1.5;
        text-align: center;
    }

    .forgot-password-card .auth-footer {
        color: #cccccc;
        font-size: 0.92rem;
    }

    @media (max-width: 520px) {
        .forgot-password-card {
            padding: 28px 22px;
        }
    }
</style>

</body>
</html>
