<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{

    /**
     * List products with search, category filter, stock filter.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('stock')) {
            match ($request->input('stock')) {
                'low' => $query->lowStock(),
                'out' => $query->where('stock_quantity', 0),
                'in' => $query->where('stock_quantity', '>', 0),
                default => null,
            };
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        $categories = Product::whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('products.index', compact('products', 'categories'));
    }

    /**
     * Show create product form.
     */
    public function create()
    {
        $categories = Product::whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('products.create', compact('categories'));
    }

    /**
     * Store a new product with validation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'description' => 'nullable|string|max:2000',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        try {
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('products', 'public');
            }

            $validated['is_active'] = $request->boolean('is_active', true);
            $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 5;

            $product = Product::create($validated);

            // Record initial stock movement if stock > 0
            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $product->stock_quantity,
                    'reference' => 'Initial stock',
                    'notes' => 'Product created with initial stock',
                    'user_id' => Auth::id(),
                ]);
            }

            return redirect()->route('products.show', $product->id)
                ->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create product: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show product details with stock history.
     */
    public function show(int $id)
    {
        $product = Product::findOrFail($id);

        $stockMovements = StockMovement::with('user:id,name')
            ->where('product_id', $id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $salesCount = $product->saleItems()->sum('quantity');
        $salesRevenue = $product->saleItems()->sum('total');

        return view('products.show', compact('product', 'stockMovements', 'salesCount', 'salesRevenue'));
    }

    /**
     * Show edit product form.
     */
    public function edit(int $id)
    {
        $product = Product::findOrFail($id);

        $categories = Product::whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('products.edit', compact('product', 'categories'));
    }

    /**
     * Update a product.
     */
    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product->id)],
            'description' => 'nullable|string|max:2000',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        try {
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('products', 'public');
            }

            $validated['is_active'] = $request->boolean('is_active', true);

            $product->update($validated);

            return redirect()->route('products.show', $product->id)
                ->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update product: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Soft delete a product.
     */
    public function destroy(int $id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return redirect()->route('products.index')
                ->with('success', 'Product deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete product: ' . $e->getMessage()]);
        }
    }

    /**
     * Manual stock adjustment.
     */
    public function adjustStock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $product = Product::lockForUpdate()->findOrFail($id);

            $type = $request->input('type');
            $quantity = $request->input('quantity');

            if ($type === 'out' && $product->stock_quantity < $quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock. Available: {$product->stock_quantity}",
                ], 422);
            }

            if ($type === 'in') {
                $product->addStock($quantity);
            } else {
                $product->deductStock($quantity);
            }

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'reference' => 'Manual adjustment',
                'notes' => $request->input('notes', 'Manual stock adjustment'),
                'user_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully.',
                'data' => [
                    'stock_quantity' => $product->fresh()->stock_quantity,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * CSV import of products.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        DB::beginTransaction();

        try {
            $file = $request->file('csv_file');
            $handle = fopen($file->getRealPath(), 'r');

            if ($handle === false) {
                return back()->withErrors(['csv_file' => 'Unable to read the uploaded file.']);
            }

            // Read header row
            $header = fgetcsv($handle);
            if ($header === false) {
                fclose($handle);
                return back()->withErrors(['csv_file' => 'CSV file is empty or invalid.']);
            }

            $header = array_map('strtolower', array_map('trim', $header));

            $requiredColumns = ['name', 'sku', 'cost_price', 'selling_price'];
            $missingColumns = array_diff($requiredColumns, $header);
            if (!empty($missingColumns)) {
                fclose($handle);
                return back()->withErrors([
                    'csv_file' => 'Missing required columns: ' . implode(', ', $missingColumns),
                ]);
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];
            $row = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                $record = array_combine($header, $data);

                // Skip rows with missing required fields
                if (empty($record['name']) || empty($record['sku'])) {
                    $skipped++;
                    $errors[] = "Row {$row}: Missing name or SKU.";
                    continue;
                }

                // Check for duplicate SKU
                $existingProduct = Product::withTrashed()->where('sku', $record['sku'])->first();
                if ($existingProduct) {
                    $skipped++;
                    $errors[] = "Row {$row}: SKU '{$record['sku']}' already exists.";
                    continue;
                }

                $product = Product::create([
                    'name' => $record['name'],
                    'sku' => $record['sku'],
                    'barcode' => $record['barcode'] ?? null,
                    'description' => $record['description'] ?? null,
                    'cost_price' => (float) ($record['cost_price'] ?? 0),
                    'selling_price' => (float) ($record['selling_price'] ?? 0),
                    'stock_quantity' => (int) ($record['stock_quantity'] ?? 0),
                    'low_stock_threshold' => (int) ($record['low_stock_threshold'] ?? 5),
                    'category' => $record['category'] ?? null,
                    'is_active' => true,
                ]);

                if ($product->stock_quantity > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'in',
                        'quantity' => $product->stock_quantity,
                        'reference' => 'CSV import',
                        'notes' => 'Imported via CSV',
                        'user_id' => Auth::id(),
                    ]);
                }

                $imported++;
            }

            fclose($handle);
            DB::commit();

            $message = "{$imported} products imported successfully.";
            if ($skipped > 0) {
                $message .= " {$skipped} rows skipped.";
            }

            return redirect()->route('products.index')
                ->with('success', $message)
                ->with('import_errors', $errors);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
