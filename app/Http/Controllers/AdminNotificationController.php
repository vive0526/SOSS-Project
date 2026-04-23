<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class AdminNotificationController extends Controller
{
    public function create()
    {
        return view('admin.notifications.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'message' => 'required|string|max:500',
            'action_url' => 'nullable|string|max:500',
        ]);

        $notification = new AdminAnnouncementNotification(
            title: $data['title'],
            message: $data['message'],
            actionUrl: $data['action_url'] ?? null,
            category: 'promotion',
            level: 'info',
        );

        User::query()
            ->where('role', 'customer')
            ->where('status', '!=', 'inactive')
            ->orderBy('user_id')
            ->chunk(200, function ($users) use ($notification) {
                foreach ($users as $user) {
                    $user->notify($notification);
                }
            });

        return Redirect::back()->with('success', 'Notification sent to customers.');
    }
}
