<?php

namespace App\Http\Controllers;

use App\Models\CattleRequest;
use App\Models\CattleRequestStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CattleRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = CattleRequest::query()
            ->with(['product', 'customer', 'handledBy'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        $requests = $query->get();

        return view('cattle_requests.index', [
            'requests' => $requests,
            'statuses' => CattleRequest::STATUSES,
            'statusCounts' => CattleRequest::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(CattleRequest $cattleRequest)
    {
        $cattleRequest->load(['product', 'customer', 'handledBy']);

        return view('cattle_requests.show', [
            'request' => $cattleRequest,
            'statuses' => CattleRequest::STATUSES,
        ]);
    }

    public function approve(Request $request, CattleRequest $cattleRequest)
    {
        if ($cattleRequest->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending requests can be approved.']);
        }

        $data = $request->validate([
            'staff_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($cattleRequest, $data) {
            $cattleRequest->status = 'approved';
            $cattleRequest->staff_note = $data['staff_note'] ?? null;
            $cattleRequest->rejection_reason = null;
            $cattleRequest->handled_by = auth()->user()?->getKey();
            $cattleRequest->handled_at = now();
            $cattleRequest->save();

            CattleRequestStatusHistory::create([
                'cattle_request_id' => $cattleRequest->id,
                'status' => 'approved',
                'note' => 'Approved by staff.',
                'changed_by' => auth()->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Request approved.');
    }

    public function reject(Request $request, CattleRequest $cattleRequest)
    {
        if ($cattleRequest->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending requests can be rejected.']);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:255',
            'staff_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($cattleRequest, $data) {
            $cattleRequest->status = 'rejected';
            $cattleRequest->rejection_reason = $data['rejection_reason'];
            $cattleRequest->staff_note = $data['staff_note'] ?? null;
            $cattleRequest->handled_by = auth()->user()?->getKey();
            $cattleRequest->handled_at = now();
            $cattleRequest->save();

            CattleRequestStatusHistory::create([
                'cattle_request_id' => $cattleRequest->id,
                'status' => 'rejected',
                'note' => 'Rejected: ' . $data['rejection_reason'],
                'changed_by' => auth()->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Request rejected.');
    }

    public function complete(Request $request, CattleRequest $cattleRequest)
    {
        if ($cattleRequest->status !== 'approved') {
            return back()->withErrors(['status' => 'Only approved requests can be completed.']);
        }

        $data = $request->validate([
            'staff_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($cattleRequest, $data) {
            $cattleRequest->status = 'completed';
            if (!empty($data['staff_note'])) {
                $cattleRequest->staff_note = $data['staff_note'];
            }

            if (!$cattleRequest->handled_by) {
                $cattleRequest->handled_by = auth()->user()?->getKey();
                $cattleRequest->handled_at = now();
            }

            $cattleRequest->completed_at = now();
            $cattleRequest->save();

            CattleRequestStatusHistory::create([
                'cattle_request_id' => $cattleRequest->id,
                'status' => 'completed',
                'note' => 'Marked completed by staff.',
                'changed_by' => auth()->user()?->getKey(),
            ]);
        });

        return back()->with('success', 'Request marked as completed.');
    }
}
