@extends('layouts.customer')

@section('title', 'Notifications')
@section('page_title', 'Notifications')
@section('page_subtitle', 'Important updates and announcements')

@section('content')
    @if(session('success'))
        <div class="customer-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="customer-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <section class="customer-section">
        <div class="customer-section__head" style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <a class="btn btn-outline" href="{{ route('customer.notifications.index') }}">
                    All
                </a>
                <a class="btn btn-outline" href="{{ route('customer.notifications.index', ['filter' => 'unread']) }}">
                    Unread
                </a>
                @if(auth()->user()?->unreadNotifications?->count())
                    <form method="POST" action="{{ route('customer.notifications.read-all') }}">
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
                        $level = $data['level'] ?? 'info';
                        $unread = $notification->read_at === null;
                    @endphp
                    <div class="customer-card" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <div style="font-size:12px; color:#9b7f63; text-transform:uppercase; letter-spacing:0.14em;">
                                {{ ucfirst($category) }}
                                @if($unread)
                                    <span style="margin-left:8px; color:#c0161a; font-weight:900;">Unread</span>
                                @endif
                            </div>
                            <div style="font-size:16px; font-weight:900; color:#4c2f1c;">
                                {{ $title }}
                            </div>
                            @if($message)
                                <div style="color:#5e4a3b; margin-top:6px; line-height:1.6;">
                                    {{ $message }}
                                </div>
                            @endif
                            <div style="color:#7b6a5b; font-size:12px; margin-top:8px;">
                                {{ $notification->created_at?->format('Y-m-d H:i') }}
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            @if($actionUrl)
                                <a class="btn btn-outline" href="{{ $actionUrl }}">Open</a>
                            @endif
                            @if($unread)
                                <form method="POST" action="{{ route('customer.notifications.read', $notification->id) }}">
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

