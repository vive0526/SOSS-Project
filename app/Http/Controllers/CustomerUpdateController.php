<?php

namespace App\Http\Controllers;

use App\Models\CattleRequestStatusHistory;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;

class CustomerUpdateController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->getKey();

        $orderEvents = OrderStatusHistory::query()
            ->with(['order'])
            ->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (OrderStatusHistory $history) {
                return [
                    'type' => 'order',
                    'created_at' => $history->created_at,
                    'title' => $history->order?->order_number ?? 'Order update',
                    'status' => $history->status,
                    'note' => $history->note,
                    'url' => $history->order ? route('customer.orders.show', $history->order) : null,
                ];
            });

        $cattleEvents = CattleRequestStatusHistory::query()
            ->with(['cattleRequest.product'])
            ->whereHas('cattleRequest', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (CattleRequestStatusHistory $history) {
                $req = $history->cattleRequest;

                return [
                    'type' => 'cattle',
                    'created_at' => $history->created_at,
                    'title' => $req?->product?->name ?? 'Cattle request update',
                    'status' => $history->status,
                    'note' => $history->note,
                    'url' => $req ? route('customer.cattle-requests.show', $req) : null,
                ];
            });

        $events = $orderEvents
            ->concat($cattleEvents)
            ->sortByDesc('created_at')
            ->take(50)
            ->values();

        return view('customer.updates.index', [
            'events' => $events,
        ]);
    }
}

