<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password | SOSS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <h2>Reset Password</h2>
        <p style="text-align:center; color:#ccc; margin-bottom:20px;">
            Create a new password for your account
        </p>

        @if (session('status'))
            <p style="color:#8ad39a; margin-bottom:10px;">
                {{ session('status') }}
            </p>
        @endif

        @if ($errors->any())
            <p style="color:red; margin-bottom:10px;">
                {{ $errors->first() }}
            </p>
        @endif

        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <label>Email</label>
            <input
                type="email"
                name="email"
                value="{{ $request->email }}"
                required
                readonly
                autocomplete="username"
            >

            <label>New Password</label>
            <div style="position:relative;">
                <input
                    type="password"
                    name="password"
                    id="resetPassword"
                    autocomplete="new-password"
                    autofocus
                    aria-describedby="resetPasswordRules"
                    data-show-rules="{{ $errors->has('password') ? '1' : '0' }}"
                    required
                >

                <div id="resetPasswordRules"
                     class="pw-rules"
                     role="status"
                     aria-live="polite"
                     style="display:none;">
                    <div class="pw-rules__title">Password must contain:</div>
                    <ul class="pw-rules__list">
                        <li data-rule="length">At least 8 characters</li>
                        <li data-rule="upper">At least 1 uppercase letter (A-Z)</li>
                        <li data-rule="lower">At least 1 lowercase letter (a-z)</li>
                        <li data-rule="number">At least 1 number (0-9)</li>
                        <li data-rule="symbol">At least 1 symbol (e.g. ! @ # $)</li>
                    </ul>
                </div>
            </div>

            <label>Confirm New Password</label>
            <input
                type="password"
                name="password_confirmation"
                autocomplete="new-password"
                required
            >

            <button class="btn btn-primary" type="submit" onclick="this.classList.add('loading')">
                Reset Password
            </button>
        </form>

        <div class="auth-footer">
            <p>Remembered your password?
                <a href="{{ route('login') }}">Back to Login</a>
            </p>
        </div>
    </div>
</div>

<style>
    .pw-rules {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 8px);
        z-index: 20;
        padding: 12px 12px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(24, 19, 15, 0.98);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
        color: #d9cfc6;
        font-size: 12px;
        line-height: 1.6;
    }
    .pw-rules__title {
        font-weight: 800;
        color: #efe3d8;
        margin-bottom: 6px;
    }
    .pw-rules__list {
        margin: 0;
        padding-left: 18px;
        display: grid;
        gap: 4px;
    }
    .pw-rules__list li {
        color: #bfb0a3;
    }
    .pw-rules__list li.is-ok {
        color: #8ad39a;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('resetPassword');
        const rulesBox = document.getElementById('resetPasswordRules');

        if (!passwordInput || !rulesBox) return;

        const rules = {
            length: (value) => value.length >= 8,
            upper: (value) => /[A-Z]/.test(value),
            lower: (value) => /[a-z]/.test(value),
            number: (value) => /[0-9]/.test(value),
            symbol: (value) => /[^A-Za-z0-9]/.test(value),
        };

        const ruleElements = {};
        rulesBox.querySelectorAll('[data-rule]').forEach((el) => {
            const key = el.getAttribute('data-rule');
            if (key) ruleElements[key] = el;
        });

        const update = () => {
            const value = passwordInput.value || '';
            Object.entries(rules).forEach(([key, fn]) => {
                const ok = fn(value);
                const el = ruleElements[key];
                if (!el) return;
                el.classList.toggle('is-ok', ok);
            });
        };

        const show = () => {
            rulesBox.style.display = 'block';
            update();
        };

        const hide = () => {
            rulesBox.style.display = 'none';
        };

        passwordInput.addEventListener('focus', show);
        passwordInput.addEventListener('input', update);
        passwordInput.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (document.activeElement !== passwordInput) {
                    hide();
                }
            }, 120);
        });

        if (passwordInput.dataset.showRules === '1') {
            show();
        }
    });
</script>

</body>
</html>
