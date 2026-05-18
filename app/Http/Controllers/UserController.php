<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ADMIN & STAFF: view users
    public function index()
    {
        if (auth()->user()->role === 'admin') {
            // Admin sees all users
            $users = User::orderBy('user_id')->get();
        } else {
            // Staff sees all customer users
            $users = User::where('role', 'customer')->orderBy('user_id')->get();
        }

        return view('users.index', compact('users'));
    }

    // ADMIN & STAFF: create user (Staff can only create customers)
    public function create()
    {
        return view('users.create');
    }

    // ADMIN & STAFF: store a newly created user
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:customer',  // Staff can only create customers
            'status' => 'required|in:active,inactive',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'shipping_address' => 'nullable|string',
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'phone' => $validated['phone'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
        ]);
        $user->created_by = auth()->id();  // Store the staff who created the user
        $user->save();

        $indexRoute = auth()->user()->role === 'staff' ? 'staff.users.index' : 'users.index';

        return redirect()->route($indexRoute)
            ->with('success', 'User created successfully.');
    }

    // ADMIN & STAFF: edit user (Staff can edit only customers and cannot change role)
    public function edit(User $user)
    {
        $this->authorizeStaffUserAccess($user);

        return view('users.edit', compact('user'));
    }

    // ADMIN & STAFF: update user
    public function update(Request $request, User $user)
    {
        $this->authorizeStaffUserAccess($user);

        $previousStatus = (string) ($user->status ?? '');

        $roleRule = auth()->user()->role === 'admin'
            ? 'required|in:admin,staff,customer'
            : 'required|in:customer';

        $validated = $request->validate([
            'name' => 'required|string',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName()),
            ],
            'role' => $roleRule,  // Staff cannot change role (only customer)
            'status' => 'required|in:active,inactive',
            'phone' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (auth()->user()->role !== 'admin') {
            $validated['role'] = 'customer';
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'phone' => $validated['phone'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        $user->update($updateData);

        $newStatus = (string) ($user->status ?? '');
        if ($newStatus !== $previousStatus && Schema::hasTable('notifications')) {
            $user->notify(new AccountStatusChangedNotification(
                newStatus: $newStatus,
                previousStatus: $previousStatus !== '' ? $previousStatus : null,
            ));
        }

        $indexRoute = auth()->user()->role === 'staff' ? 'staff.users.index' : 'users.index';

        return redirect()->route($indexRoute)
            ->with('success', 'User updated successfully.');
    }

    // ADMIN & STAFF: deactivate user
    public function deactivate(User $user)
    {
        if (auth()->user()->role !== 'admin') {
            $this->authorizeStaffUserAccess($user);
        }

        $previousStatus = (string) ($user->status ?? '');
        $user->update(['status' => 'inactive']);

        if (Schema::hasTable('notifications') && $previousStatus !== 'inactive') {
            $user->notify(new AccountStatusChangedNotification(
                newStatus: 'inactive',
                previousStatus: $previousStatus !== '' ? $previousStatus : null,
            ));
        }

        $indexRoute = auth()->user()->role === 'staff' ? 'staff.users.index' : 'users.index';

        return redirect()->route($indexRoute)
            ->with('success', 'User has been deactivated.');
    }

    // ADMIN & STAFF: activate user
    public function activate(User $user)
    {
        if (auth()->user()->role !== 'admin') {
            $this->authorizeStaffUserAccess($user);
        }

        $previousStatus = (string) ($user->status ?? '');
        $user->update(['status' => 'active']);

        if (Schema::hasTable('notifications') && $previousStatus !== 'active') {
            $user->notify(new AccountStatusChangedNotification(
                newStatus: 'active',
                previousStatus: $previousStatus !== '' ? $previousStatus : null,
            ));
        }

        $indexRoute = auth()->user()->role === 'staff' ? 'staff.users.index' : 'users.index';

        return redirect()->route($indexRoute)
            ->with('success', 'User has been activated.');
    }

    private function authorizeStaffUserAccess(User $user): void
    {
        if (auth()->user()->role === 'admin') {
            return;
        }

        if ($user->role !== 'customer') {
            abort(403);
        }

        // Staff can manage any customer user
    }
}
