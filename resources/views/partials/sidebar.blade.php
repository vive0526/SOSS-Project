@auth
<aside style="width: 250px; background: #f8f9fa; min-height: 100vh; padding: 15px;">

    <h4>Menu</h4>
    <hr>

    {{-- ================= ADMIN SIDEBAR ================= --}}
    @if(Auth::user()->role === 'admin')

        <ul style="list-style: none; padding-left: 0;">

            <li>
                <a href="/admin/dashboard">Dashboard</a>
            </li>

            <li>
                <strong>User Management</strong>
                <ul>
                    <a href="{{ route('users.index') }}">Users</a>
                    <li><a href="#">Roles</a></li>
                </ul>
            </li>

            <li>
                <strong>Reports</strong>
                <ul>
                    <li><a href="#">Inventory Updates</a></li>
                </ul>
            </li>

            <li>
                <strong>General Settings</strong>
                <ul>
                    <a href="{{ route('regions.index') }}">Regions</a>
                    <li><a href="{{ route('operating-units.index') }}">Operating Units</a></li>
                    <li><a href="{{ route('companies.index') }}">Company</a></li>
                    <li><a href="{{ route('codes.index') }}">Codes</a></li>
                </ul>
            </li>

            <li>
                <a href="#">Monitor & Verify Payments</a>
            </li>

        </ul>

    {{-- ================= STAFF SIDEBAR ================= --}}
    @elseif(Auth::user()->role === 'staff')
        <ul style="list-style: none; padding-left: 0;">

            <li>
                <a href="/staff/dashboard">Dashboard</a>
            </li>

            <li>
                <strong>User Management</strong>
                <ul>
                    @if(Route::has('staff.users.index'))
                        <li><a href="{{ route('staff.users.index') }}">Users</a></li>
                    @else
                        <li>Users</li>
                    @endif
                    @if(Route::has('staff.users.create'))
                        <li><a href="{{ route('staff.users.create') }}">Create User</a></li>
                    @else
                        <li>Create User</li>
                    @endif
                </ul>
            </li>

            <li>
                <strong>Payment Process</strong>
                <ul>
                    <li><a href="#">Payment Verification</a></li>
                    <li><a href="#">Shipment Process</a></li>
                </ul>
            </li>

            <li>
                <strong>Products</strong>
                <ul>
                    <li><a href="{{ route('products.index') }}">Product List</a></li>
                </ul>
            </li>

            <li>
                <strong>Report</strong>
                <ul>
                    <li><a href="#">Inventory Updates</a></li>
                </ul>
            </li>

        </ul>

    {{-- ================= CUSTOMER SIDEBAR ================= --}}
    @elseif(Auth::user()->role === 'customer')

        <ul style="list-style: none; padding-left: 0;">

            <li>
                <a href="/customer/dashboard">Dashboard</a>
            </li>

            <li>
                <a href="#">Products</a>
            </li>

            <li>
                <a href="#">Add to Cart</a>
            </li>

            <li>
                <strong>Notifications</strong>
                <ul>
                    <li><a href="#">Promotions</a></li>
                    <li><a href="#">Order Updates</a></li>
                </ul>
            </li>

        </ul>

    @endif

</aside>
@endauth
