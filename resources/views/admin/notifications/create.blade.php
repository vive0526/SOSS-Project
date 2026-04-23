@extends('layouts.admin')

@section('title', 'Send Notification')

@section('content')
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">Send Notification</h1>
                <p class="admin-subtitle">Broadcast an in-app message to all customers.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="admin-alert admin-alert--success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="admin-alert admin-alert--danger">
                <div style="font-weight:800; margin-bottom:8px;">Please check the form.</div>
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="admin-card">
            <form method="POST" action="{{ route('admin.notifications.store') }}">
                @csrf

                <div class="admin-field">
                    <label for="title">Title</label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" required>
                </div>

                <div class="admin-field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" required>{{ old('message') }}</textarea>
                </div>

                <div class="admin-field">
                    <label for="action_url">Action URL (optional)</label>
                    <input id="action_url" type="text" name="action_url" value="{{ old('action_url') }}" placeholder="{{ url('/customer/products') }}">
                </div>

                <button type="submit" class="btn btn-primary">Send to customers</button>
            </form>
        </div>
    </div>
@endsection

