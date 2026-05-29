@extends('layouts.admin')

@section('title', 'Notifications')
@section('page_title', 'Notifications')
@section('page_subtitle', 'System alerts and updates')

@section('content')
    @if($errors->any())
        <div class="admin-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <section class="customer-section">
        <div class="customer-section__head" style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <a class="btn btn-outline" href="{{ route('system-notifications.index') }}">
                    All
                </a>
                <a class="btn btn-outline" href="{{ route('system-notifications.index', ['filter' => 'unread']) }}">
                    Unread
                </a>
                @if(auth()->user()?->unreadNotifications?->count())
                    <form method="POST" action="{{ route('system-notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Mark all read</button>
                    </form>
                @endif
            </div>
        </div>

        @if($notifications->isEmpty())
            <div class="customer-empty">No notifications yet.</div>
        @else
            <div style="display:grid; gap:12px;">
                @foreach($notifications as $notification)
                    @php
                        $data = $notification->data ?? [];
                        $title = $data['title'] ?? 'Notification';
                        $message = $data['message'] ?? '';
                        $actionUrl = $data['action_url'] ?? null;
                        $category = $data['category'] ?? 'info';
                        $unread = $notification->read_at === null;
                    @endphp
                    <div class="admin-card" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <div style="font-size:12px; color:#bfbfbf; text-transform:uppercase; letter-spacing:0.14em;">
                                {{ ucfirst($category) }}
                                @if($unread)
                                    <span style="margin-left:8px; color:#c0161a; font-weight:900;">Unread</span>
                                @endif
                            </div>
                            <div style="font-size:16px; font-weight:900;">
                                {{ $title }}
                            </div>
                            @if($message)
                                <div style="color:#bfbfbf; margin-top:6px; line-height:1.6;">
                                    {{ $message }}
                                </div>
                            @endif
                            <div style="color:#bfbfbf; font-size:12px; margin-top:8px;">
                                {{ $notification->created_at?->format('Y-m-d H:i') }}
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            @if($actionUrl)
                                <a class="btn btn-outline" href="{{ $actionUrl }}">Open</a>
                            @endif
                            @if($unread)
                                <form method="POST" action="{{ route('system-notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline">Mark read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:14px;">
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
@endsection

