<header style="padding: 15px; border-bottom: 1px solid #ddd;">
    <nav style="display: flex; justify-content: space-between; align-items: center;">

        {{-- LOGO --}}
        <div>
            <a href="/" style="text-decoration: none; font-weight: bold;">
                SOSS
            </a>
        </div>

        {{-- RIGHT MENU --}}
        <div>
            @guest
                {{-- Before login --}}
                <a href="{{ route('login') }}" style="margin-right: 15px;">Login</a>
                <a href="{{ route('register') }}">Sign Up</a>
            @endguest

            @auth
                <div style="position: relative; display: inline-block;">

                    {{-- Username (clickable) --}}
                    <button onclick="toggleDropdown()"
                        style="background: none; border: none; cursor: pointer; font-weight: bold;">
                        {{ Auth::user()->name }} ▼
                    </button>

                    {{-- Dropdown --}}
                    <div id="userDropdown"
                        style="display: none; position: absolute; right: 0; background: #fff;
                            border: 1px solid #ddd; min-width: 150px; margin-top: 10px;">

                        <a href="{{ route('profile.edit') }}"
                        style="display: block; padding: 10px; text-decoration: none;">
                            Profile
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                style="width: 100%; padding: 10px; border: none; background: none; text-align: left; cursor: pointer;">
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>

    </nav>
</header>
