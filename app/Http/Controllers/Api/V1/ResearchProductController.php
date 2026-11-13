<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResearchProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('research_products')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $paginated = $query->paginate(20);

        // Attach ingredients to each research product item
        $rpIds = collect($paginated->items())->pluck('id');
        $ingredientsMap = DB::table('research_product_ingredients as rpi')
            ->join('ingredients as i', 'rpi.ingredient_id', '=', 'i.id')
            ->select('rpi.*', 'i.name as ingredient_name', 'i.code as ingredient_code')
            ->whereIn('rpi.research_product_id', $rpIds)
            ->get()
            ->groupBy('research_product_id');

        foreach ($paginated->items() as $item) {
            $item->ingredients = $ingredientsMap->get($item->id, collect([]))->values();
        }

        return response()->json($paginated);
    }

    public function show(int $id): JsonResponse
    {
        $item = DB::table('research_products')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$item) return response()->json(['message' => 'Research product tidak ditemukan.'], 404);

        $item->ingredients = DB::table('research_product_ingredients as rpi')
            ->join('ingredients as i', 'rpi.ingredient_id', '=', 'i.id')
            ->select('rpi.*', 'i.name as ingredient_name', 'i.code as ingredient_code')
            ->where('rpi.research_product_id', $id)
            ->get();

        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:200',
            'category'                 => 'nullable|string|max:100',
            'description'              => 'nullable|string',
            'target_price'             => 'nullable|numeric|min:0',
            'status'                   => 'nullable|in:draft,testing,approved,rejected',
            'notes'                    => 'nullable|string',
            'ingredients'              => 'nullable|array',
            'ingredients.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'ingredients.*.quantity'      => 'required|numeric|min:0.001',
            'ingredients.*.unit_symbol'   => 'nullable|string',
            'ingredients.*.unit_cost'     => 'nullable|numeric|min:0',
        ]);

        $ingredients = $request->input('ingredients', []);
        $totalHpp = 0;

        foreach ($ingredients as $ing) {
            $unitCost = (float)($ing['unit_cost'] ?? 0);
            $qty = (float)($ing['quantity'] ?? 0);
            $totalHpp += ($qty * $unitCost);
        }

        $rpData = [
            'name'           => $data['name'],
            'category'       => $data['category'] ?? null,
            'description'    => $data['description'] ?? null,
            'estimated_cost' => $totalHpp,
            'target_price'   => $data['target_price'] ?? 0,
            'status'         => $data['status'] ?? 'draft',
            'notes'          => $data['notes'] ?? null,
            'created_by'     => auth()->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        $id = DB::table('research_products')->insertGetId($rpData);

        // Insert ingredients
        foreach ($ingredients as $ing) {
            $qty = (float)$ing['quantity'];
            $unitCost = (float)($ing['unit_cost'] ?? 0);
            DB::table('research_product_ingredients')->insert([
                'research_product_id' => $id,
                'ingredient_id'       => $ing['ingredient_id'],
                'quantity'            => $qty,
                'unit_symbol'         => $ing['unit_symbol'] ?? '',
                'unit_cost'           => $unitCost,
                'subtotal'            => round($qty * $unitCost, 2),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        ActivityLog::record(action: 'create', module: 'research', entity: 'ResearchProduct', entityId: $id, description: "Membuat Research Product: {$data['name']}");

        return response()->json(['message' => 'Research product berhasil dibuat.', 'id' => $id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rp = DB::table('research_products')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$rp) return response()->json(['message' => 'Tidak ditemukan.'], 404);

        $data = $request->validate([
            'name'                     => 'sometimes|string|max:200',
            'category'                 => 'nullable|string|max:100',
            'description'              => 'nullable|string',
            'target_price'             => 'nullable|numeric|min:0',
            'status'                   => 'nullable|in:draft,testing,approved,rejected',
            'notes'                    => 'nullable|string',
            'ingredients'              => 'nullable|array',
            'ingredients.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'ingredients.*.quantity'      => 'required|numeric|min:0.001',
            'ingredients.*.unit_symbol'   => 'nullable|string',
            'ingredients.*.unit_cost'     => 'nullable|numeric|min:0',
        ]);

        $ingredients = $request->input('ingredients', null);
        $rpData = [];

        if (array_key_exists('name', $data))        $rpData['name'] = $data['name'];
        if (array_key_exists('category', $data))    $rpData['category'] = $data['category'];
        if (array_key_exists('description', $data)) $rpData['description'] = $data['description'];
        if (array_key_exists('target_price', $data)) $rpData['target_price'] = $data['target_price'];
        if (array_key_exists('status', $data))      $rpData['status'] = $data['status'];
        if (array_key_exists('notes', $data))       $rpData['notes'] = $data['notes'];

        if ($ingredients !== null) {
            $totalHpp = 0;
            DB::table('research_product_ingredients')->where('research_product_id', $id)->delete();
            foreach ($ingredients as $ing) {
                $qty = (float)$ing['quantity'];
                $unitCost = (float)($ing['unit_cost'] ?? 0);
                $subtotal = round($qty * $unitCost, 2);
                $totalHpp += $subtotal;

                DB::table('research_product_ingredients')->insert([
                    'research_product_id' => $id,
                    'ingredient_id'       => $ing['ingredient_id'],
                    'quantity'            => $qty,
                    'unit_symbol'         => $ing['unit_symbol'] ?? '',
                    'unit_cost'           => $unitCost,
                    'subtotal'            => $subtotal,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
            $rpData['estimated_cost'] = $totalHpp;
        }

        $rpData['updated_at'] = now();
        DB::table('research_products')->where('id', $id)->update($rpData);

        ActivityLog::record(action: 'update', module: 'research', entity: 'ResearchProduct', entityId: $id, description: "Memperbarui Research Product ID #{$id}");

        return response()->json(['message' => 'Research product berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('research_products')->where('id', $id)->update(['deleted_at' => now()]);
        return response()->json(['message' => 'Research product berhasil dihapus.']);
    }

    // POST /v1/research-products/{id}/deduct-stock  — Deduct inventory for research testing
    public function deductStock(Request $request, int $id, InventoryService $inventoryService): JsonResponse
    {
        $rp = DB::table('research_products')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$rp) return response()->json(['message' => 'Research product tidak ditemukan.'], 404);

        $ingredients = DB::table('research_product_ingredients as rpi')
            ->join('ingredients as i', 'rpi.ingredient_id', '=', 'i.id')
            ->select('rpi.*', 'i.name as ingredient_name')
            ->where('rpi.research_product_id', $id)
            ->get();

        if ($ingredients->isEmpty()) {
            return response()->json(['message' => 'Produk riset belum memiliki daftar ingredients!'], 422);
        }

        $warehouseId = $request->input('warehouse_id');
        if (!$warehouseId) {
            // Column in `warehouses` table is `active`, not `is_active`
            $mainWh = Warehouse::where('active', true)->first() ?? Warehouse::first();
            $warehouseId = $mainWh?->id ?? 1;
        }

        $testRuns = max(1, (int)$request->input('test_runs', 1));
        $deducted = [];

        DB::transaction(function () use ($ingredients, $testRuns, $warehouseId, $rp, $inventoryService, &$deducted) {
            foreach ($ingredients as $ingItem) {
                $ingredient = Ingredient::find($ingItem->ingredient_id);
                if (!$ingredient) continue;

                // Convert recipe quantity to main stock unit
                $recipeQty = (float)$ingItem->quantity * $testRuns;
                $mainStockQty = $ingredient->convertRecipeQtyToMainStockQty($recipeQty);

                // Deduct stock via InventoryService
                $inventoryService->stockOut([
                    'ingredient_id'  => $ingredient->id,
                    'warehouse_id'   => $warehouseId,
                    'qty'            => $mainStockQty,
                    'type'           => 'stock_out',
                    'reference_type' => 'research_product',
                    'reference_id'   => $rp->id,
                    'notes'          => "Pemakaian Research Product: {$rp->name} ({$testRuns}x test)",
                ]);

                $deducted[] = [
                    'name' => $ingredient->name,
                    'qty'  => $mainStockQty,
                    'unit' => $ingredient->unit?->symbol ?? 'unit',
                ];
            }

            // Update status to testing if it was draft
            if ($rp->status === 'draft') {
                DB::table('research_products')->where('id', $rp->id)->update([
                    'status'     => 'testing',
                    'updated_at' => now(),
                ]);
            }
        });

        ActivityLog::record(action: 'deduct_stock', module: 'research', entity: 'ResearchProduct', entityId: $rp->id, description: "Melakukan pemotongan stok inventory untuk uji coba research: {$rp->name} ({$testRuns}x run)");

        return response()->json([
            'message'  => "Berhasil memotong stok inventory untuk {$testRuns}x uji coba research!",
            'deducted' => $deducted,
        ]);
    }

    // POST /v1/research-products/{id}/convert-to-product — Convert research item to active Menu Product
    public function convertToProduct(int $id, \App\Services\RecipeService $recipeService): JsonResponse
    {
        $rp = DB::table('research_products')->whereNull('deleted_at')->where('id', $id)->first();
        if (!$rp) return response()->json(['message' => 'Research product tidak ditemukan.'], 404);

        // Prevent double-conversion: only draft or testing status can be converted
        if (in_array($rp->status, ['approved', 'converted'])) {
            return response()->json([
                'message' => "Research product ini sudah pernah dikonversi (status: {$rp->status}). Tidak dapat dikonversi ulang.",
            ], 422);
        }

        $ingredients = DB::table('research_product_ingredients')
            ->where('research_product_id', $id)
            ->get();

        // Resolve category: match by name, create if missing, or fall back to first active category
        $catId = null;
        if (!empty($rp->category)) {
            $cat = \App\Models\ProductCategory::where('name', $rp->category)->first();
            if (!$cat) {
                $cat = \App\Models\ProductCategory::create([
                    'name' => $rp->category,
                    'slug' => \Illuminate\Support\Str::slug($rp->category),
                ]);
            }
            $catId = $cat->id;
        }

        // Fallback: use first valid active category from the database (never hardcode 1)
        if (!$catId) {
            $firstCat = \App\Models\ProductCategory::whereNull('deleted_at')->orderBy('id')->first();
            if (!$firstCat) {
                return response()->json([
                    'message' => 'Tidak ada kategori produk yang tersedia. Tambahkan minimal satu kategori produk terlebih dahulu.',
                ], 422);
            }
            $catId = $firstCat->id;
        }

        // Create product
        $product = \App\Models\Product::create([
            'category_id'       => $catId,
            'name'              => $rp->name,
            'selling_price'     => $rp->target_price ?? 0,
            'description'       => $rp->description ?? "Produk hasil research (HPP: Rp {$rp->estimated_cost})",
            'available_for_pos' => true,
            'sku'               => \App\Models\Product::generateNextSku(),
            'slug'              => \Illuminate\Support\Str::slug($rp->name) . '-' . time(),
        ]);

        // Build recipe items
        if ($ingredients->isNotEmpty()) {
            $recipeItems = [];
            foreach ($ingredients as $ing) {
                $recipeItems[] = [
                    'ingredient_id' => $ing->ingredient_id,
                    'qty'           => $ing->quantity,
                    'type'          => 'main',
                    'notes'         => 'Imported from Research Product',
                ];
            }
            $recipeService->saveProductRecipe($product, [
                'name'  => "Resep {$rp->name}",
                'items' => $recipeItems,
            ]);
        }

        // Mark research product as converted to prevent duplicate conversions
        DB::table('research_products')->where('id', $rp->id)->update([
            'status'     => 'converted',
            'updated_at' => now(),
        ]);

        ActivityLog::record(action: 'convert_product', module: 'research', entity: 'Product', entityId: $product->id, description: "Mengubah Research Product '{$rp->name}' menjadi produk menu baru.");

        return response()->json([
            'message' => "Berhasil menambahkan '{$rp->name}' ke daftar Menu Produk!",
            'product' => $product->load('category', 'recipe.ingredients.ingredient.unit'),
        ], 201);
    }
}
