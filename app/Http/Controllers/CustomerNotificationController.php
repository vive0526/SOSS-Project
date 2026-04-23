<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Redirect;

class CustomerNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $user->notifications()->latest();
        if ($request->get('filter') === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(15)->withQueryString();

        return view('customer.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, string $notificationId)
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        if ($notification instanceof DatabaseNotification) {
            $notification->markAsRead();
        }

        return Redirect::back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return Redirect::back()->with('success', 'All notifications marked as read.');
    }

    public function dismissProfilePrompt(Request $request)
    {
        $request->session()->put('profile_prompt_dismissed', true);

        return response()->json(['ok' => true]);
    }
}

