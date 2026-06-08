<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images'])
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            if ($search !== '') {
                $candidateSlug = Str::slug($search);

                $exact = Product::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($search, $candidateSlug) {
                        $q->where('slug', $candidateSlug)
                            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($search)]);
                    })
                    ->select(['slug'])
                    ->limit(2)
                    ->get();

                if ($exact->count() === 1) {
                    return redirect()->route('customer.products.show', $exact->first()->slug);
                }

                $categoryExact = Category::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($search)])
                    ->select(['id'])
                    ->limit(2)
                    ->get();

                if ($categoryExact->count() === 1) {
                    return redirect()->route('customer.products.index', ['category_id' => $categoryExact->first()->id]);
                }
            }

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

        $products = $query->paginate(15)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('customer.products.index', compact('products', 'categories'));
    }

    public function show(string $productSlug)
    {
        $product = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where('slug', $productSlug)
            ->firstOrFail();

        $reviews = ProductReview::query()
            ->with('customer')
            ->where('product_id', $product->getKey())
            ->where('status', 'approved')
            ->latest()
            ->take(10)
            ->get();

        $reviewCount = ProductReview::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'approved')
            ->count();

        $averageRating = (float) ProductReview::query()
            ->where('product_id', $product->getKey())
            ->where('status', 'approved')
            ->avg('rating');

        return view('customer.products.show', compact('product', 'reviews', 'reviewCount', 'averageRating'));
    }

    public function stock(Request $request, Product $product)
    {
        $product->refresh();

        if (!(bool) ($product->is_active ?? false)) {
            abort(404);
        }

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
