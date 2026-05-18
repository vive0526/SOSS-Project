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
            <input type="text" name="name" value="{{ old('name') }}" required>

            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required>

            <label>Phone Number (Malaysia)</label>
            <input
                type="tel"
                name="phone"
                value="{{ old('phone') }}"
                placeholder="e.g. 012-3456789 or +6012-3456789"
                required
            >

            <label>Password</label>
            <div style="position:relative;">
                <input
                    type="password"
                    name="password"
                    id="registerPassword"
                    autocomplete="new-password"
                    aria-describedby="passwordRules"
                    data-show-rules="{{ $errors->has('password') ? '1' : '0' }}"
                    required
                >

                <div id="passwordRules"
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

            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" required>

            <button class="btn btn-primary" type="submit"   onclick="this.classList.add('loading')">Register</button>
        </form>

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

        <div class="auth-footer">
            <p>Already have an account?
                <a href="{{ route('login') }}">Login</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
