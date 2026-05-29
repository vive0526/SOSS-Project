<!DOCTYPE html>
<html lang="en">
<head>
    <title>Verify Email | SOSS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 7.5a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-9Z" stroke="currentColor" stroke-width="1.8"/>
                <path d="M6.2 7.4 12 12.1l5.8-4.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <h2>Verify your email</h2>
        <p class="auth-subtitle">
            We sent a verification link to <span class="auth-highlight">{{ auth()->user()?->email }}</span>.
            Please check your inbox and click the link to activate your account.
        </p>

        @if (session('warning'))
            <div class="auth-alert auth-alert--warning">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="auth-alert auth-alert--error">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status') === 'verification-link-sent')
            <div class="auth-alert auth-alert--success">
                A new verification link has been sent. Please check your email again.
            </div>
        @endif

        <div class="auth-tips">
            <div class="auth-tips__title">Didn’t receive it?</div>
            <ul class="auth-tips__list">
                <li>Check your Spam / Junk folder</li>
                <li>Make sure the email address above is correct</li>
                <li>Wait 1–2 minutes (delivery can be delayed)</li>
            </ul>
        </div>

        <div class="auth-actions">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-primary" type="submit" onclick="this.classList.add('loading')">
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline" type="submit">
                    Log out
                </button>
            </form>
        </div>

        <div class="auth-footer">
            <p style="color:#ccc;">
                Need help? Contact support from the home page.
            </p>
        </div>
    </div>
</div>

</body>
</html>
