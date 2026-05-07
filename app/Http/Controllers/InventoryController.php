<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->orderBy('name')->get();
        $lowStockProducts = Product::with('category')
            ->whereRaw('(stock_quantity - reserved_quantity) > 0')
            ->whereRaw('(stock_quantity - reserved_quantity) <= reorder_level')
            ->orderByRaw('(stock_quantity - reserved_quantity) asc')
            ->get();
        $outOfStockProducts = Product::with('category')
            ->whereRaw('(stock_quantity - reserved_quantity) <= 0')
            ->orderBy('name')
            ->get();

        $totalProducts = $products->count();
        $lowStockCount = $lowStockProducts->count();
        $outOfStockCount = $outOfStockProducts->count();

        return view('inventory.index', compact(
            'products',
            'lowStockProducts',
            'outOfStockProducts',
            'totalProducts',
            'lowStockCount',
            'outOfStockCount'
        ));
    }

    public function edit(Product $product)
    {
        return view('inventory.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'reorder_level' => 'required|integer|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $product->reorder_level = (int) $data['reorder_level'];

        $previousStock = $product->stock_quantity;
        if ($request->filled('stock_quantity')) {
            $newStock = (int) $data['stock_quantity'];
            if ($newStock !== $previousStock) {
                $product->stock_quantity = $newStock;

                InventoryMovement::create([
                    'product_id' => $product->getKey(),
                    'user_id' => $request->user()->getKey(),
                    'type' => 'set',
                    'quantity' => abs($newStock - $previousStock),
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => $data['reason'] ?? 'Inventory settings updated.',
                ]);
            }
        }

        $product->save();

        return redirect()
            ->route('products.inventory')
            ->with('success', 'Inventory settings updated successfully.');
    }

    public function adjust(Product $product)
    {
        return view('inventory.adjust', compact('product'));
    }

    public function storeAdjustment(Request $request, Product $product)
    {
        $data = $request->validate([
            'type' => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $quantity = (int) $data['quantity'];
        $previousStock = $product->stock_quantity;
        $newStock = $data['type'] === 'in'
            ? $previousStock + $quantity
            : $previousStock - $quantity;

        if ($newStock < 0) {
            return back()
                ->withErrors(['quantity' => 'Not enough stock to remove that quantity.'])
                ->withInput();
        }

        $product->stock_quantity = $newStock;
        $product->save();

        InventoryMovement::create([
            'product_id' => $product->getKey(),
            'user_id' => $request->user()->getKey(),
            'type' => $data['type'],
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'reason' => $data['reason'],
        ]);

        return redirect()
            ->route('products.inventory')
            ->with('success', 'Stock adjustment saved.');
    }

    public function history(Request $request)
    {
        $query = InventoryMovement::with(['product', 'user'])
            ->orderByDesc('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $movements = $query->get();
        $products = Product::orderBy('name')->get();

        return view('inventory.history', compact('movements', 'products'));
    }

    public function reportLevels(Request $request)
    {
        if ($request->query('export') === 'csv') {
            $products = Product::with('category')->orderBy('name')->cursor();

            $rows = (function () use ($products) {
                $index = 1;
                foreach ($products as $product) {
                    $status = 'Normal';
                    if ($product->stock_quantity <= 0) {
                        $status = 'Out of Stock';
                    } elseif ($product->stock_quantity <= $product->reorder_level) {
                        $status = 'Low';
                    }

                    yield [
                        $index++,
                        $product->name,
                        $product->category?->name ?? 'Uncategorized',
                        $product->stock_quantity,
                        $product->reorder_level,
                        $status,
                    ];
                }
            })();

            return $this->streamCsvDownload(
                'inventory-level-report-' . now()->format('Y-m-d_His') . '.csv',
                ['No', 'Product', 'Category', 'Stock Quantity', 'Reorder Level', 'Status'],
                $rows
            );
        }

        if ($request->query('export') === 'excel') {
            $products = Product::with('category')->orderBy('name')->cursor();

            $rows = (function () use ($products) {
                $index = 1;
                foreach ($products as $product) {
                    $status = 'Normal';
                    if ($product->stock_quantity <= 0) {
                        $status = 'Out of Stock';
                    } elseif ($product->stock_quantity <= $product->reorder_level) {
                        $status = 'Low';
                    }

                    yield [
                        $index++,
                        $product->name,
                        $product->category?->name ?? 'Uncategorized',
                        $product->stock_quantity,
                        $product->reorder_level,
                        $status,
                    ];
                }
            })();

            return $this->streamExcelTablesDownload(
                'inventory-level-report-' . now()->format('Y-m-d_His') . '.xls',
                'Inventory Level Report',
                [
                    [
                        'headers' => ['No', 'Product', 'Category', 'Stock Quantity', 'Reorder Level', 'Status'],
                        'rows' => $rows,
                    ],
                ]
            );
        }

        if ($request->query('export') === 'pdf') {
            $products = Product::with('category')->orderBy('name')->get();

            $rows = $products->map(function (Product $product, int $index) {
                $status = 'Normal';
                if ($product->stock_quantity <= 0) {
                    $status = 'Out of Stock';
                } elseif ($product->stock_quantity <= $product->reorder_level) {
                    $status = 'Low';
                }

                return [
                    $index + 1,
                    $product->name,
                    $product->category?->name ?? 'Uncategorized',
                    $product->stock_quantity,
                    $product->reorder_level,
                    $status,
                ];
            });

            return response()->view('reports.print', [
                'title' => 'Inventory Level Report',
                'tables' => [
                    [
                        'headers' => ['No', 'Product', 'Category', 'Stock Quantity', 'Reorder Level', 'Status'],
                        'rows' => $rows,
                    ],
                ],
            ]);
        }

        $products = Product::with('category')->orderBy('name')->get();

        return view('inventory.reports.levels', compact('products'));
    }

    public function reportLowStock(Request $request)
    {
        if ($request->query('export') === 'csv') {
            $products = Product::with('category')
                ->whereColumn('stock_quantity', '<=', 'reorder_level')
                ->orderBy('stock_quantity')
                ->cursor();

            $rows = (function () use ($products) {
                $index = 1;
                foreach ($products as $product) {
                    $status = $product->stock_quantity <= 0 ? 'Out of Stock' : 'Low';

                    yield [
                        $index++,
                        $product->name,
                        $product->category?->name ?? 'Uncategorized',
                        $product->stock_quantity,
                        $product->reorder_level,
                        $status,
                    ];
                }
            })();

            return $this->streamCsvDownload(
                'low-stock-report-' . now()->format('Y-m-d_His') . '.csv',
                ['No', 'Product', 'Category', 'Stock Quantity', 'Reorder Level', 'Status'],
                $rows
            );
        }

        if ($request->query('export') === 'excel') {
            $products = Product::with('category')
                ->whereColumn('stock_quantity', '<=', 'reorder_level')
                ->orderBy('stock_quantity')
                ->cursor();

            $rows = (function () use ($products) {
                $index = 1;
                foreach ($products as $product) {
                    $status = $product->stock_quantity <= 0 ? 'Out of Stock' : 'Low';

                    yield [
                        $index++,
                        $product->name,
                        $product->category?->name ?? 'Uncategorized',
                        $product->stock_quantity,
                        $product->reorder_level,
                        $status,
                    ];
                }
            })();

            return $this->streamExcelTablesDownload(
                'low-stock-report-' . now()->format('Y-m-d_His') . '.xls',
                'Low Stock Report',
                [
                    [
                        'headers' => ['No', 'Product', 'Category', 'Stock Quantity', 'Reorder Level', 'Status'],
                        'rows' => $rows,
                    ],
                ]
            );
        }

        if ($request->query('export') === 'pdf') {
            $products = Product::with('category')
                ->whereColumn('stock_quantity', '<=', 'reorder_level')
                ->orderBy('stock_quantity')
                ->get();

            $rows = $products->map(function (Product $product, int $index) {
                $status = $product->stock_quantity <= 0 ? 'Out of Stock' : 'Low';

                return [
                    $index + 1,
                    $product->name,
                    $product->category?->name ?? 'Uncategorized',
                    $product->stock_quantity,
                    $product->reorder_level,
                    $status,
                ];
            });

            return response()->view('reports.print', [
                'title' => 'Low Stock Report',
                'tables' => [
                    [
                        'headers' => ['No', 'Product', 'Category', 'Stock Quantity', 'Reorder Level', 'Status'],
                        'rows' => $rows,
                    ],
                ],
            ]);
        }

        $products = Product::with('category')
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->orderBy('stock_quantity')
            ->get();

        return view('inventory.reports.low-stock', compact('products'));
    }

    public function reportMovements(Request $request)
    {
        if ($request->query('export') === 'csv') {
            $movements = InventoryMovement::with(['product', 'user'])
                ->orderByDesc('created_at')
                ->cursor();

            $rows = (function () use ($movements) {
                $index = 1;
                foreach ($movements as $movement) {
                    yield [
                        $index++,
                        $movement->product?->name ?? 'N/A',
                        strtoupper((string) $movement->type),
                        $movement->quantity,
                        $movement->previous_stock ?? '-',
                        $movement->new_stock ?? '-',
                        $movement->user?->name ?? 'N/A',
                        $movement->reason ?? '-',
                        $movement->created_at?->format('Y-m-d H:i'),
                    ];
                }
            })();

            return $this->streamCsvDownload(
                'stock-movement-report-' . now()->format('Y-m-d_His') . '.csv',
                ['No', 'Product', 'Type', 'Quantity', 'Previous', 'New', 'Updated By', 'Reason', 'Date'],
                $rows
            );
        }

        if ($request->query('export') === 'excel') {
            $movements = InventoryMovement::with(['product', 'user'])
                ->orderByDesc('created_at')
                ->cursor();

            $rows = (function () use ($movements) {
                $index = 1;
                foreach ($movements as $movement) {
                    yield [
                        $index++,
                        $movement->product?->name ?? 'N/A',
                        strtoupper((string) $movement->type),
                        $movement->quantity,
                        $movement->previous_stock ?? '-',
                        $movement->new_stock ?? '-',
                        $movement->user?->name ?? 'N/A',
                        $movement->reason ?? '-',
                        $movement->created_at?->format('Y-m-d H:i'),
                    ];
                }
            })();

            return $this->streamExcelTablesDownload(
                'stock-movement-report-' . now()->format('Y-m-d_His') . '.xls',
                'Stock Movement Report',
                [
                    [
                        'headers' => ['No', 'Product', 'Type', 'Quantity', 'Previous', 'New', 'Updated By', 'Reason', 'Date'],
                        'rows' => $rows,
                    ],
                ]
            );
        }

        if ($request->query('export') === 'pdf') {
            $movements = InventoryMovement::with(['product', 'user'])
                ->orderByDesc('created_at')
                ->get();

            $rows = $movements->map(function (InventoryMovement $movement, int $index) {
                return [
                    $index + 1,
                    $movement->product?->name ?? 'N/A',
                    strtoupper((string) $movement->type),
                    $movement->quantity,
                    $movement->previous_stock ?? '-',
                    $movement->new_stock ?? '-',
                    $movement->user?->name ?? 'N/A',
                    $movement->reason ?? '-',
                    $movement->created_at?->format('Y-m-d H:i'),
                ];
            });

            return response()->view('reports.print', [
                'title' => 'Stock Movement Report',
                'tables' => [
                    [
                        'headers' => ['No', 'Product', 'Type', 'Quantity', 'Previous', 'New', 'Updated By', 'Reason', 'Date'],
                        'rows' => $rows,
                    ],
                ],
            ]);
        }

        $movements = InventoryMovement::with(['product', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return view('inventory.reports.movements', compact('movements'));
    }
}
