<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductReview::query()
            ->with(['product', 'customer', 'order'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('rating') && is_numeric($request->input('rating'))) {
            $query->where('rating', (int) $request->input('rating'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', '%' . $search . '%')
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', '%' . $search . '%');
                    });
            });
        }

        $reviews = $query->paginate(20)->withQueryString();

        return view('product_reviews.index', [
            'reviews' => $reviews,
            'statuses' => ProductReview::STATUSES,
            'statusCounts' => ProductReview::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'filters' => $request->only(['status', 'rating', 'search']),
        ]);
    }

    public function updateStatus(Request $request, ProductReview $productReview)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ProductReview::STATUSES)],
        ]);

        $productReview->status = $data['status'];
        $productReview->moderated_at = now();
        $productReview->moderated_by = $request->user()?->getKey();
        $productReview->save();

        return back()->with('success', 'Review status updated.');
    }
}
