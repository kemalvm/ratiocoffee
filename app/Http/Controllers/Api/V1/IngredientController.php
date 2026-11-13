<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IngredientController extends Controller
{
    // ─── GET /api/v1/inventory/ingredients ────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Ingredient::with(['category', 'unit', 'defaultSupplier'])
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->supplier_id || $request->default_supplier_id, fn($q) => $q->where('default_supplier_id', $request->supplier_id ?? $request->default_supplier_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when(
                $request->boolean('critical_only') || $request->critical_only == 1 || $request->critical === 'true' || $request->boolean('critical'),
                fn($q) => $q->critical()
            )
            ->orderBy($request->sort_by ?? 'name', $request->sort_dir ?? 'asc');

        if ($request->has('per_page') && (int)$request->per_page > 0) {
            $ingredients = $query->paginate((int)$request->per_page);
            $collection  = $ingredients->getCollection();
        } else {
            $ingredients = $query->get();
            $collection  = $ingredients;
        }

        // Append computed attributes
        $collection->transform(function ($item) {
            $item->append(['stock_value', 'is_critical', 'cost_per_unit', 'purchase_price']);
            return $item;
        });

        return response()->json($ingredients);
    }

    // ─── POST /api/v1/inventory/ingredients ───────────────────────────────

    public function store(Request $request, \App\Services\InventoryService $inventoryService): JsonResponse
    {
        if (!empty($request->warehouse_id) && !\App\Models\Warehouse::where('id', $request->warehouse_id)->exists()) {
            $firstWarehouse = \App\Models\Warehouse::first();
            $request->merge(['warehouse_id' => $firstWarehouse?->id]);
        }

        $data = $request->validate([
            'category_id'         => ['required', 'exists:ingredient_categories,id'],
            'unit_id'             => ['required', 'exists:units,id'],
            'net_content'         => ['nullable', 'numeric', 'min:0.001'],
            'recipe_unit_symbol'  => ['nullable', 'string', 'max:30'],
            'default_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name'                => ['required', 'string', 'max:200'],
            'barcode'             => ['nullable', 'string', 'unique:ingredients,barcode'],
            'description'         => ['nullable', 'string'],
            'min_stock'           => ['required', 'numeric', 'min:0'],
            'reorder_point'       => ['nullable', 'numeric', 'min:0'],
            'reorder_qty'         => ['nullable', 'numeric', 'min:0'],
            'standard_cost'       => ['nullable', 'numeric', 'min:0'],
            'costing_method'      => ['sometimes', Rule::in(['fifo', 'moving_average'])],
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'status'              => ['sometimes', Rule::in(['active', 'inactive'])],
            // Initial Stock options
            'purchase_price'      => ['nullable', 'numeric', 'min:0'],
            'initial_stock'       => ['nullable', 'numeric', 'min:0'],
            'warehouse_id'        => ['nullable', 'exists:warehouses,id'],
            'batch_number'        => ['nullable', 'string'],
            'expiry_date'         => ['nullable', 'date'],
            'payment_method'      => ['nullable', 'string'],
            'record_expense'      => ['nullable', 'boolean'],
        ]);

        $data['code'] = $this->generateCode();
        $data['net_content']    = $data['net_content'] ?? 1;
        $data['costing_method'] = $data['costing_method'] ?? 'moving_average';

        $unit = \App\Models\Unit::find($data['unit_id'] ?? null);
        $sym  = strtolower($unit?->symbol ?? '');
        $net  = (float) $data['net_content'];
        if ($net <= 0) $net = 1.0;

        $rawPrice = (float) ($data['purchase_price'] ?? $data['standard_cost'] ?? 0);
        $net      = (float) ($data['net_content'] ?? 1);
        if ($net <= 0) $net = 1.0;

        $unitCost = $rawPrice > 0 ? ($rawPrice / $net) : 0;

        $data['average_cost']        = round($unitCost, 4);
        $data['last_purchase_price'] = round($rawPrice, 4);

        $ingredient = Ingredient::create($data);

        // If initial stock is provided or purchase_price > 0, record Stock In movement via InventoryService
        $initialStockInput = isset($data['initial_stock']) && $data['initial_stock'] !== null ? (float) $data['initial_stock'] : null;

        // Default initial stock to 1 package if purchase price > 0 and initial_stock was not explicitly set to 0
        if (($initialStockInput === null || (!array_key_exists('initial_stock', $data) && $initialStockInput <= 0)) && $rawPrice > 0) {
            $initialStockInput = 1.0;
        }

        $warehouseId = $data['warehouse_id'] ?? \App\Models\Warehouse::first()?->id;

        if ($initialStockInput !== null && $initialStockInput > 0 && $warehouseId) {
            // Convert package count to base units if initialStockInput < net (e.g. 1 pack of 2500g = 2500g)
            $baseQty = ($net > 1 && $initialStockInput < $net) ? ($initialStockInput * $net) : $initialStockInput;

            try {
                $inventoryService->stockIn([
                    'ingredient_id'  => $ingredient->id,
                    'warehouse_id'   => $warehouseId,
                    'qty'            => $baseQty,
                    'unit_cost'      => $unitCost,
                    'batch_number'   => $data['batch_number'] ?? null,
                    'expiry_date'    => $data['expiry_date'] ?? null,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'supplier_id'    => $data['default_supplier_id'] ?? null,
                    'notes'          => 'Stok Awal (Tambah Inventory Baru)',
                    'is_base_qty'    => true,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Initial stockIn failed: " . $e->getMessage());
            }

            $packageCount = ($net > 1 && $baseQty >= $net) ? ($baseQty / $net) : 1;
            $totalCost    = $packageCount * $rawPrice;
            if (($request->get('record_expense', true)) && $totalCost > 0) {
                try {
                    $cat = \App\Models\ExpenseCategory::firstOrCreate(
                        ['name' => 'Pembelian Bahan Baku & Inventory'],
                        ['active' => true]
                    );

                    \App\Models\Expense::create([
                        'category_id'      => $cat->id,
                        'expense_date'     => now()->toDateString(),
                        'amount'           => $totalCost,
                        'description'      => "Pembelian Inventory Baru: {$ingredient->name} ({$packageCount} kemasan @ Rp " . number_format($rawPrice, 0, ',', '.') . ")",
                        'payment_method'   => $data['payment_method'] ?? 'cash',
                        'notes'            => 'Auto-generated dari Tambah Inventory Baru',
                        'status'           => 'paid',
                        'outlet_id'        => auth()->user()?->outlet_id ?? \Illuminate\Support\Facades\DB::table('outlets')->value('id') ?? 1,
                        'created_by'       => auth()->id() ?? \Illuminate\Support\Facades\DB::table('users')->value('id') ?? 1,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Initial expense creation failed: " . $e->getMessage());
                }
            }
        }

        try {
            ActivityLog::record(
                action:      'create_ingredient',
                module:      'inventory',
                entity:      'Ingredient',
                entityId:    $ingredient->id,
                newData:     $data,
                description: "Created ingredient: {$ingredient->name}",
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Activity log failed: " . $e->getMessage());
        }

        return response()->json([
            'message'    => 'Bahan berhasil dibuat.',
            'ingredient' => $ingredient->fresh()->load('category', 'unit', 'defaultSupplier', 'stocks.warehouse'),
        ], 201);
    }

    // ─── GET /api/v1/inventory/ingredients/{ingredient} ───────────────────

    public function show(Ingredient $ingredient): JsonResponse
    {
        $ingredient->load(['category', 'unit', 'defaultSupplier', 'stocks.warehouse', 'batches.warehouse']);
        $ingredient->append(['stock_value', 'is_critical', 'cost_per_unit']);

        return response()->json(['ingredient' => $ingredient]);
    }

    // ─── PATCH /api/v1/inventory/ingredients/{ingredient} ─────────────────

    public function update(Request $request, Ingredient $ingredient): JsonResponse
    {
        $old  = $ingredient->toArray();
        $data = $request->validate([
            'category_id'         => ['sometimes', 'exists:ingredient_categories,id'],
            'unit_id'             => ['sometimes', 'exists:units,id'],
            'net_content'         => ['nullable', 'numeric', 'min:0.001'],
            'recipe_unit_symbol'  => ['nullable', 'string', 'max:30'],
            'default_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name'                => ['sometimes', 'string', 'max:200'],
            'barcode'             => ['nullable', 'string', Rule::unique('ingredients', 'barcode')->ignore($ingredient->id)],
            'description'         => ['nullable', 'string'],
            'min_stock'           => ['sometimes', 'numeric', 'min:0'],
            'reorder_point'       => ['nullable', 'numeric', 'min:0'],
            'reorder_qty'         => ['nullable', 'numeric', 'min:0'],
            'standard_cost'       => ['nullable', 'numeric', 'min:0'],
            'purchase_price'      => ['nullable', 'numeric', 'min:0'],
            'track_batch'         => ['boolean'],
            'track_expiry'        => ['boolean'],
            'status'              => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if (isset($data['purchase_price']) && (float)$data['purchase_price'] > 0) {
            $rawPrice = (float) $data['purchase_price'];
            $net = isset($data['net_content']) && (float)$data['net_content'] > 0 
                ? (float)$data['net_content'] 
                : (float)($ingredient->net_content ?: 1);
            if ($net <= 0) $net = 1.0;

            $unitCost = $rawPrice / $net;

            $data['average_cost']        = round($unitCost, 4);
            $data['last_purchase_price'] = round($rawPrice, 4);

            // Synchronize average_cost across all warehouse stocks for this ingredient
            \App\Models\IngredientStock::where('ingredient_id', $ingredient->id)
                ->update(['average_cost' => round($unitCost, 4)]);
        }

        $ingredient->update($data);

        ActivityLog::record(
            action:      'update_ingredient',
            module:      'inventory',
            entity:      'Ingredient',
            entityId:    $ingredient->id,
            oldData:     $old,
            newData:     $data,
            description: "Updated ingredient: {$ingredient->name}",
        );

        return response()->json([
            'message'    => 'Bahan berhasil diperbarui.',
            'ingredient' => $ingredient->fresh()->load('category', 'unit', 'defaultSupplier'),
        ]);
    }

    // ─── DELETE /api/v1/inventory/ingredients/{ingredient} ────────────────

    public function destroy(Request $request, Ingredient $ingredient): JsonResponse
    {
        $name = $ingredient->name;
        $currentStock = (float) $ingredient->current_stock;
        $warehouseId  = \App\Models\Warehouse::first()?->id ?? 1;

        // 1. Record stock movement audit entry for deletion if there was stock or to log removal
        \App\Models\StockMovement::create([
            'ingredient_id'  => $ingredient->id,
            'warehouse_id'   => $warehouseId,
            'user_id'        => auth()->id(),
            'type'           => 'waste',
            'qty'            => -$currentStock,
            'qty_before'     => $currentStock,
            'qty_after'      => 0,
            'unit_cost'      => (float) $ingredient->average_cost,
            'total_cost'     => round($currentStock * (float)$ingredient->average_cost, 4),
            'notes'          => "Penghapusan item inventory dari sistem: {$name} ({$ingredient->code})",
            'movement_at'    => now(),
        ]);

        // 2. Clear stocks per warehouse & recipe references (PRESERVE stock_movements for audit history)
        $ingredient->stocks()->delete();
        \Illuminate\Support\Facades\DB::table('recipe_ingredients')->where('ingredient_id', $ingredient->id)->delete();

        // NOTE: Historical purchase expenses are accounting records and MUST NOT be deleted
        // when a master ingredient is removed. They remain linked via description for audit trail.

        ActivityLog::record(
            action:      'delete_ingredient',
            module:      'inventory',
            entity:      'Ingredient',
            entityId:    $ingredient->id,
            oldData:     $ingredient->toArray(),
            description: "Deleted ingredient {$name} ({$ingredient->code})",
        );

        // 4. Soft delete ingredient so stock_movements history remains linked & visible
        $ingredient->delete();

        return response()->json(['message' => "Bahan baku {$name} berhasil dihapus dari sistem dan mutasi stoknya telah dicatat."]);
    }

    // ─── GET /api/v1/inventory/categories ─────────────────────────────────

    public function categories(): JsonResponse
    {
        $categories = IngredientCategory::withCount('ingredients')->orderBy('name')->get();
        return response()->json([
            'categories' => $categories,
            'data'       => $categories
        ]);
    }

    // ─── POST /api/v1/inventory/categories ────────────────────────────────

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'unique:ingredient_categories,name'],
            'color'       => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $category = IngredientCategory::create($data);
        return response()->json(['message' => 'Kategori dibuat.', 'category' => $category->loadCount('ingredients')], 201);
    }

    // ─── PUT /api/v1/inventory/categories/{category} ──────────────────────

    public function updateCategory(Request $request, IngredientCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', Rule::unique('ingredient_categories', 'name')->ignore($category->id)],
            'color'       => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $category->update($data);

        return response()->json([
            'message'  => 'Kategori berhasil diperbarui.',
            'category' => $category->fresh()->loadCount('ingredients')
        ]);
    }

    // ─── DELETE /api/v1/inventory/categories/{category} ───────────────────

    public function destroyCategory(IngredientCategory $category): JsonResponse
    {
        $count = $category->ingredients()->count();
        if ($count > 0) {
            return response()->json([
                'message' => "Kategori '{$category->name}' tidak dapat dihapus karena masih digunakan oleh {$count} item bahan baku."
            ], 422);
        }

        $name = $category->name;
        $category->delete();

        return response()->json(['message' => "Kategori '{$name}' berhasil dihapus."]);
    }

    // ─── GET /api/v1/inventory/units ──────────────────────────────────────

    public function units(): JsonResponse
    {
        $units = Unit::orderBy('name')->get();
        return response()->json([
            'units' => $units,
            'data'  => $units
        ]);
    }

    // ─── POST /api/v1/inventory/units ─────────────────────────────────────

    public function storeUnit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'unique:units,name'],
            'symbol' => ['required', 'string', 'max:10'],
            'type'   => ['required', Rule::in(['volume', 'weight', 'piece', 'length', 'other'])],
        ]);
        $unit = Unit::create($data);
        return response()->json(['message' => 'Satuan dibuat.', 'unit' => $unit], 201);
    }

    // ─── PUT /api/v1/inventory/units/{unit} ────────────────────────────────

    public function updateUnit(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100', 'unique:units,name,' . $unit->id],
            'symbol' => ['required', 'string', 'max:10'],
            'type'   => ['required', Rule::in(['volume', 'weight', 'piece', 'length', 'other'])],
        ]);

        $unit->update($data);
        return response()->json(['message' => 'Satuan berhasil diperbarui.', 'unit' => $unit]);
    }

    // ─── DELETE /api/v1/inventory/units/{unit} ─────────────────────────────

    public function destroyUnit(Unit $unit): JsonResponse
    {
        $count = Ingredient::where('unit_id', $unit->id)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "Satuan '{$unit->name}' tidak dapat dihapus karena sedang digunakan oleh {$count} bahan baku."
            ], 422);
        }

        $name = $unit->name;
        $unit->delete();

        return response()->json(['message' => "Satuan '{$name}' berhasil dihapus."]);
    }

    // ─── Private ──────────────────────────────────────────────────────────

    private function generateCode(): string
    {
        return Ingredient::generateNextCode();
    }
}
