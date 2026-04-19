@extends('layouts.admin')

@section('title', 'Cattle Request Details')
@section('page_title', 'Cattle Request Details')
@section('page_subtitle', 'Review and update this request')

@section('content')
    @if(session('success'))
        <div class="admin-card">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="admin-card">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="admin-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 8px;">Request</h3>
            <p><strong>ID:</strong> #{{ $request->id }}</p>
            <p><strong>Status:</strong> {{ ucfirst($request->status) }}</p>
            <p><strong>Created:</strong> {{ $request->created_at?->format('Y-m-d H:i') }}</p>
            <p><strong>Preferred Date:</strong> {{ $request->preferred_date?->format('Y-m-d') ?? '-' }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Customer</h3>
            <p><strong>Name:</strong> {{ $request->customer?->name ?? '-' }}</p>
            <p><strong>Email:</strong> {{ $request->customer?->email ?? '-' }}</p>
            <p><strong>Phone:</strong> {{ $request->phone ?? '-' }}</p>
        </div>
        <div>
            <h3 style="margin-bottom: 8px;">Product</h3>
            <p><strong>Name:</strong> {{ $request->product?->name ?? '-' }}</p>
            <p><strong>Quantity:</strong> {{ $request->quantity }}</p>
            <p><strong>Purpose:</strong> {{ $request->purpose ? ucfirst($request->purpose) : '-' }}</p>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 8px;">Customer Notes</h3>
        <p>{{ $request->customer_note ?: '-' }}</p>
    </div>

    <div class="admin-card" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
        <div>
            <h3 style="margin-bottom: 12px;">Approve</h3>
            <form method="POST" action="{{ route('cattle-requests.approve', $request) }}">
                @csrf
                @method('PATCH')
                <label for="approve_staff_note">Staff Note (optional)</label>
                <textarea id="approve_staff_note" name="staff_note" rows="3"></textarea>
                <button type="submit" class="btn btn-primary" {{ $request->status !== 'pending' ? 'disabled' : '' }}>
                    Approve
                </button>
            </form>
        </div>
        <div>
            <h3 style="margin-bottom: 12px;">Reject</h3>
            <form method="POST" action="{{ route('cattle-requests.reject', $request) }}">
                @csrf
                @method('PATCH')
                <label for="rejection_reason">Rejection Reason</label>
                <input id="rejection_reason" type="text" name="rejection_reason" required>
                <label for="reject_staff_note">Staff Note (optional)</label>
                <textarea id="reject_staff_note" name="staff_note" rows="3"></textarea>
                <button type="submit" class="btn btn-outline" {{ $request->status !== 'pending' ? 'disabled' : '' }}>
                    Reject
                </button>
            </form>
        </div>
        <div>
            <h3 style="margin-bottom: 12px;">Complete</h3>
            <form method="POST" action="{{ route('cattle-requests.complete', $request) }}">
                @csrf
                @method('PATCH')
                <label for="complete_staff_note">Staff Note (optional)</label>
                <textarea id="complete_staff_note" name="staff_note" rows="3"></textarea>
                <button type="submit" class="btn btn-outline" {{ $request->status !== 'approved' ? 'disabled' : '' }}>
                    Mark Completed
                </button>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <h3 style="margin-bottom: 8px;">Handling</h3>
        <p><strong>Handled By:</strong> {{ $request->handledBy?->name ?? '-' }}</p>
        <p><strong>Handled At:</strong> {{ $request->handled_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>Completed At:</strong> {{ $request->completed_at?->format('Y-m-d H:i') ?? '-' }}</p>
        <p><strong>Staff Note:</strong> {{ $request->staff_note ?: '-' }}</p>
        <p><strong>Rejection Reason:</strong> {{ $request->rejection_reason ?: '-' }}</p>
    </div>

    <div class="admin-card">
        <a class="btn btn-outline" href="{{ route('cattle-requests.index') }}">Back to list</a>
    </div>
@endsection

