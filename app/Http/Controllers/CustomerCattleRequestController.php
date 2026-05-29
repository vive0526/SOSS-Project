<?php

namespace App\Http\Controllers;

use App\Models\CattleRequest;
use App\Models\CattleRequestStatusHistory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCattleRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = CattleRequest::with(['product'])
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get();

        $statusCounts = CattleRequest::where('user_id', $request->user()->getKey())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('customer.cattle_requests.index', [
            'requests' => $requests,
            'statuses' => CattleRequest::STATUSES,
            'statusCounts' => $statusCounts,
            'filters' => $request->only(['status']),
        ]);
    }

    public function show(Request $request, CattleRequest $cattleRequest)
    {
        if ($cattleRequest->user_id !== $request->user()->getKey()) {
            abort(403);
        }

        $cattleRequest->load(['product', 'statusHistories.changedBy']);

        return view('customer.cattle_requests.show', [
            'request' => $cattleRequest,
        ]);
    }

    public function create(Product $product)
    {
        if (($product->product_type ?? 'normal') !== 'cattle') {
            abort(404);
        }

        return view('customer.cattle_requests.create', [
            'product' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|string|exists:products,product_id',
            'phone' => 'required|string|max:60',
            'quantity' => 'required|integer|min:1',
            'purpose' => 'required|in:breeding,slaughter,others',
            'preferred_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:2000',
        ]);

        $product = Product::query()
            ->where('product_id', $data['product_id'])
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return back()->withErrors([
                'product_id' => 'This product is no longer available.',
            ])->withInput();
        }

        if (($product->product_type ?? 'normal') !== 'cattle') {
            return back()->withErrors([
                'product_id' => 'This product does not accept purchase requests.',
            ])->withInput();
        }

        $cattleRequest = CattleRequest::create([
            'product_id' => $product->getKey(),
            'user_id' => $request->user()->getKey(),
            'phone' => $data['phone'],
            'quantity' => (int) $data['quantity'],
            'purpose' => $data['purpose'],
            'preferred_date' => $data['preferred_date'],
            'status' => 'pending',
            'customer_note' => $data['notes'] ?? null,
        ]);

        CattleRequestStatusHistory::create([
            'cattle_request_id' => $cattleRequest->id,
            'status' => 'pending',
            'note' => 'Request submitted by customer.',
            'changed_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Purchase request submitted. Our staff will review it.');
    }
}
