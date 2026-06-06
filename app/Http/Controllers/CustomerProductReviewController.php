<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerProductReviewController extends Controller
{
    public function store(Request $request, OrderItem $orderItem)
    {
        $orderItem->load(['order', 'product', 'review']);
        $order = $orderItem->order;

        if (!$order || $order->user_id !== $request->user()->getKey()) {
            abort(403);
        }

        if ($order->status !== 'delivered' || $order->shipment_status !== 'delivered') {
            return back()->withErrors(['review' => 'You can review products only after the order is delivered.']);
        }

        if ($orderItem->review) {
            return back()->withErrors(['review' => 'You have already reviewed this item.']);
        }

        if (!$orderItem->product) {
            return back()->withErrors(['review' => 'This product is no longer available for review.']);
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $orderItem, $order, $data) {
            ProductReview::create([
                'product_id' => $orderItem->product_id,
                'order_id' => $order->getKey(),
                'order_item_id' => $orderItem->getKey(),
                'user_id' => $request->user()->getKey(),
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
                'status' => 'approved',
                'is_dummy' => false,
            ]);
        });

        return back()->with('success', 'Review submitted. Thank you for your feedback.');
    }
}
