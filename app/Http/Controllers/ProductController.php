<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    // Display all products (Admin & Staff can see the full catalog)
    public function index(Request $request)
    {
        $query = Product::with('category')->orderByDesc('created_at');

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

        $products = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('products.index', compact('products', 'categories'));
    }

    // Inventory overview (Admin only)
    public function inventoryOverview(Request $request)
    {
        $threshold = (int) $request->input('low_stock_threshold', 5);
        if ($threshold < 0) {
            $threshold = 0;
        }

        $totalProducts = Product::count();

        $lowStockProductsQuery = Product::with('category')
            ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) > 0')
            ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) <= ?', [$threshold])
            ->orderByRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) asc');
        $lowStockCount = (clone $lowStockProductsQuery)->count();

        $outOfStockProductsQuery = Product::with('category')
            ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) <= 0')
            ->orderBy('name');
        $outOfStockCount = (clone $outOfStockProductsQuery)->count();

        $products = Product::with('category')
            ->orderBy('name')
            ->paginate(20, ['*'], 'products_page')
            ->withQueryString();

        $lowStockProducts = $lowStockProductsQuery
            ->paginate(20, ['*'], 'low_stock_page')
            ->withQueryString();

        $outOfStockProducts = $outOfStockProductsQuery
            ->paginate(20, ['*'], 'out_of_stock_page')
            ->withQueryString();

        return view('products.inventory', compact(
            'products',
            'lowStockProducts',
            'outOfStockProducts',
            'totalProducts',
            'lowStockCount',
            'outOfStockCount',
            'threshold'
        ));
    }

    // Show the form for creating a new product
    public function create()
    {
        $categories = Category::orderBy('name')->get();  // Get categories for dropdown
        return view('products.create', compact('categories'));
    }

    // Store a newly created product
    public function store(Request $request)
    {
        $data = $this->validatedProductData($request);

        // Create the new product
        $product = new Product($data);
        $product->user_id = auth()->id();  // Associate product with the current user (Staff)

        // Handle image upload
        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('products', 'public');
        }

        $product->save();

        return redirect()->route('products.index')->with('success', 'Product added successfully.');
    }

    // Show the form for editing a product
    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();  // Get categories for the dropdown
        return view('products.edit', compact('product', 'categories'));
    }

    // Update the product
    public function update(Request $request, Product $product)
    {
        $data = $this->validatedProductData($request);
        $product->update($data);

        // Handle image update
        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($product->image && file_exists(storage_path('app/public/' . $product->image))) {
                unlink(storage_path('app/public/' . $product->image));
            }

            $product->image = $request->file('image')->store('products', 'public');
            $product->save();
        }

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    private function validatedProductData(Request $request): array
    {
        $treePlantingCategoryId = 5;
        $forceMaintenance = (int) $request->input('category_id') === $treePlantingCategoryId;

        $rules = [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'maintenance_years' => 'nullable|integer|min:1|max:5',
            'maintenance_prices' => 'nullable|array',
            'maintenance_prices.*' => 'nullable|numeric|min:0',
            'maintenance_stocks' => 'nullable|array',
            'maintenance_stocks.*' => 'nullable|integer|min:0',
        ];

        if (Schema::hasColumn('products', 'product_type')) {
            $rules['product_type'] = 'required|in:normal,cattle';
        }

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $forceMaintenance) {
            if (!$forceMaintenance) {
                $price = $request->input('price');
                if ($price === null || $price === '') {
                    $validator->errors()->add('price', 'Enter a price for the product.');
                }

                $stock = $request->input('stock_quantity');
                if ($stock === null || $stock === '') {
                    $validator->errors()->add('stock_quantity', 'Enter a stock quantity for the product.');
                }
                return;
            }

            $years = (int) $request->input('maintenance_years');
            if ($years < 1 || $years > 5) {
                $validator->errors()->add('maintenance_years', 'Select 1 to 5 years of maintenance support.');
                return;
            }

            for ($year = 1; $year <= $years; $year++) {
                $price = $request->input("maintenance_prices.$year");
                if ($price === null || $price === '') {
                    $validator->errors()->add("maintenance_prices.$year", "Enter a price for year {$year}.");
                }

                $stock = $request->input("maintenance_stocks.$year");
                if ($stock === null || $stock === '') {
                    $validator->errors()->add("maintenance_stocks.$year", "Enter a stock quantity for year {$year}.");
                }
            }
        });

        $data = $validator->validate();
        unset($data['image']);

        $data['requires_maintenance'] = $forceMaintenance;

        if (!$data['requires_maintenance']) {
            $data['maintenance_years'] = null;
            $data['maintenance_prices'] = null;
            $data['maintenance_stocks'] = null;
            return $data;
        }

        $years = (int) ($data['maintenance_years'] ?? 0);
        $prices = [];
        $stocks = [];
        $sumStock = 0;
        for ($year = 1; $year <= $years; $year++) {
            $priceValue = $request->input("maintenance_prices.$year");
            if ($priceValue !== null && $priceValue !== '') {
                $prices[$year] = (float) $priceValue;
            }

            $stockValue = $request->input("maintenance_stocks.$year");
            if ($stockValue !== null && $stockValue !== '') {
                $stocks[$year] = (int) $stockValue;
                $sumStock += (int) $stockValue;
            }
        }
        $data['maintenance_prices'] = $prices;
        $data['maintenance_stocks'] = $stocks;
        $data['stock_quantity'] = $sumStock;

        if (!empty($prices)) {
            $data['price'] = (float) min($prices);
        }

        return $data;
    }

    // Delete the product
    public function destroy(Product $product)
    {
        // Delete the product image
        if ($product->image && file_exists(storage_path('app/public/' . $product->image))) {
            unlink(storage_path('app/public/' . $product->image));
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }
}

