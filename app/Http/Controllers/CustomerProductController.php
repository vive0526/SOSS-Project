<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CustomerProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('min_price') && is_numeric($request->input('min_price'))) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price') && is_numeric($request->input('max_price'))) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price');
                break;
            case 'price_high':
                $query->orderByDesc('price');
                break;
            case 'name':
                $query->orderBy('name');
                break;
            case 'newest':
            default:
                $query->orderByDesc('created_at');
                break;
        }

        $products = $query->get();
        $categories = Category::orderBy('name')->get();

        return view('customer.products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        return view('customer.products.show', compact('product'));
    }

    public function stock(Request $request, Product $product)
    {
        $product->refresh();

        $maintenanceYear = null;
        if ($request->filled('maintenance_year') && is_numeric($request->input('maintenance_year'))) {
            $maintenanceYear = (int) $request->input('maintenance_year');
            if ($maintenanceYear < 1 || $maintenanceYear > 5) {
                $maintenanceYear = null;
            }
        }

        $available = (int) $product->availableStock();
        $yearAvailable = null;
        if ($maintenanceYear && (bool) ($product->requires_maintenance ?? false)) {
            $yearAvailable = (int) $product->availableMaintenanceStock($maintenanceYear);
            $available = min($available, $yearAvailable);
        }

        return response()->json([
            'ok' => true,
            'product_id' => (string) $product->getKey(),
            'stock_quantity' => (int) $product->stock_quantity,
            'reserved_quantity' => (int) ($product->reserved_quantity ?? 0),
            'maintenance_year' => $maintenanceYear,
            'maintenance_available_stock' => $yearAvailable,
            'available_stock' => $available,
        ]);
    }
}
