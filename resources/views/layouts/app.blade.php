<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sawiit Online Sales System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Add CSS later --}}
</head>
<body>

     {{-- Header --}}
    @include('partials.header')

    <div style="display: flex;">

        {{-- Sidebar --}}
        @include('partials.sidebar')

        {{-- Main Content --}}
        <main style="padding: 20px; width: 100%;">
            @yield('content')
        </main>


    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display =
                dropdown.style.display === 'block' ? 'none' : 'block';
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('button')) {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown && dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>
