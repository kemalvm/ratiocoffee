<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    // ─── GET /api/v1/inventory/stock-movements ────────────────────────────

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with([
                'ingredient' => fn($q) => $q->withTrashed()->select('id', 'name', 'code', 'deleted_at'),
                'warehouse:id,name,code',
                'user:id,name,username',
            ])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('ingredient', fn($i) => $i->withTrashed()->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($request->ingredient_id, fn($q) => $q->byIngredient($request->ingredient_id))
            ->when($request->warehouse_id,  fn($q) => $q->byWarehouse($request->warehouse_id))
            ->when($request->type,          fn($q) => $q->byType($request->type))
            ->when($request->date_from, fn($q) => $q->where('movement_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('movement_at', '<=', $request->date_to . ' 23:59:59'))
            ->orderByDesc('movement_at');

        return response()->json($query->paginate($request->per_page ?? 25));
    }

    // ─── GET /api/v1/inventory/alerts ─────────────────────────────────────

    public function alerts(): JsonResponse
    {
        $critical = Ingredient::with(['unit', 'category'])
            ->active()
            ->critical()
            ->orderBy('current_stock')
            ->get()
            ->map(fn($i) => [
                'id'            => $i->id,
                'code'          => $i->code,
                'name'          => $i->name,
                'current_stock' => $i->current_stock,
                'min_stock'     => $i->min_stock,
                'unit'          => $i->unit?->symbol,
                'category'      => $i->category?->name,
                'shortage'      => max(0, (float)$i->min_stock - (float)$i->current_stock),
            ]);

        return response()->json(['critical' => $critical, 'count' => $critical->count()]);
    }

    // ─── GET /api/v1/inventory/dashboard ──────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $totalIngredients = Ingredient::active()->count();
        $criticalCount    = Ingredient::active()->critical()->count();
        // Calculate asset value using the centralised stock_value accessor which correctly
        // applies net_content division: ($stock / $net) × $pkgPrice — avoiding "gram × pack-price" inflation.
        $totalValue = (float) Ingredient::whereNull('deleted_at')
            ->where('status', 'active')
            ->get()
            ->sum(fn($ing) => max(0, (float) $ing->stock_value));
        $todayMovements   = StockMovement::recent(1)->count();
        $lowStock         = Ingredient::active()
            ->whereColumn('current_stock', '<=', DB::raw('min_stock * 1.5'))
            ->count();

        $movementsByType = StockMovement::recent(30)
            ->selectRaw('type, COUNT(*) as count, SUM(ABS(qty * unit_cost)) as total_value')
            ->groupBy('type')
            ->get();

        $topMovers = DB::table('stock_movements')
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->where('stock_movements.movement_at', '>=', now()->subDays(30))
            ->where('stock_movements.type', 'stock_out')
            ->selectRaw('ingredients.id, ingredients.name, SUM(ABS(stock_movements.qty)) as total_out')
            ->groupBy('ingredients.id', 'ingredients.name')
            ->orderByDesc('total_out')
            ->limit(5)
            ->get();

        $expiringSoon = \App\Models\StockBatch::with(['ingredient:id,name,code', 'warehouse:id,name'])
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(14))
            ->where('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->limit(10)
            ->get();

        return response()->json([
            'total_ingredients'  => $totalIngredients,
            'critical_count'     => $criticalCount,
            'low_stock_count'    => $lowStock,
            'total_value'        => round($totalValue, 2),
            'today_movements'    => $todayMovements,
            'movements_by_type'  => $movementsByType,
            'top_movers'         => $topMovers,
            'expiring_soon'      => $expiringSoon,
        ]);
    }

    // ─── POST /api/v1/inventory/stock-in ──────────────────────────────────

    public function stockIn(Request $request, $ingredient = null): JsonResponse
    {
        if ($ingredient) {
            $ingId = is_object($ingredient) ? $ingredient->id : $ingredient;
            $request->merge(['ingredient_id' => $ingId]);
        }

        if (!$request->has('warehouse_id') || !$request->warehouse_id) {
            $defaultWhId = \App\Models\Warehouse::first()?->id;
            if ($defaultWhId) {
                $request->merge(['warehouse_id' => $defaultWhId]);
            }
        }

        $data = $request->validate([
            'ingredient_id'   => ['required', 'exists:ingredients,id'],
            'warehouse_id'    => ['required', 'exists:warehouses,id'],
            'qty'             => ['required', 'numeric', 'min:0.001'],
            'unit_cost'       => ['required', 'numeric', 'min:0'],
            'payment_method'  => ['nullable', 'string'],
            'supplier_id'     => ['nullable', 'exists:suppliers,id'],
            'batch_number'    => ['nullable', 'string'],
            'expiry_date'     => ['nullable', 'date'],
            'notes'           => ['nullable', 'string'],
            'record_expense'  => ['nullable', 'boolean'],
            'is_base_qty'     => ['nullable', 'boolean'],
        ]);

        $movement = $this->service->stockIn($data);

        $totalCost = (float) $data['qty'] * (float) $data['unit_cost'];
        if (($request->get('record_expense', true)) && $totalCost > 0) {
            try {
                $ingredient = Ingredient::find($data['ingredient_id']);
                $cat = \App\Models\ExpenseCategory::firstOrCreate(
                    ['name' => 'Pembelian Bahan Baku & Inventory'],
                    ['active' => true]
                );

                \App\Models\Expense::create([
                    'category_id'      => $cat->id,
                    'expense_date'     => now()->toDateString(),
                    'amount'           => $totalCost,
                    'description'      => "Kulakan / Restock Inventory: {$ingredient?->name} ({$data['qty']} {$ingredient?->unit?->symbol} @ Rp " . number_format($data['unit_cost'], 0, ',', '.') . ")",
                    'payment_method'   => $data['payment_method'] ?? 'cash',
                    'notes'            => $data['notes'] ?? 'Auto-generated from Stock In / Kulakan Ulang',
                    'status'           => 'paid',
                    'outlet_id'        => auth()->user()?->outlet_id ?? \Illuminate\Support\Facades\DB::table('outlets')->value('id') ?? 1,
                    'created_by'       => auth()->id() ?? \Illuminate\Support\Facades\DB::table('users')->value('id') ?? 1,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Expense creation in stockIn failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'message'  => 'Kulakan Ulang berhasil dicatat. Stok bertambah & pengeluaran keuangan tercatat!',
            'movement' => $movement->load('ingredient', 'warehouse'),
        ], 201);
    }

    // ─── POST /api/v1/inventory/stock-out ─────────────────────────────────

    public function stockOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'warehouse_id'  => ['required', 'exists:warehouses,id'],
            'qty'           => ['required', 'numeric', 'min:0.001'],
            'notes'         => ['nullable', 'string'],
        ]);

        try {
            $movement = $this->service->stockOut($data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'  => 'Stock Out berhasil dicatat.',
            'movement' => $movement->load('ingredient', 'warehouse'),
        ], 201);
    }

    // ─── POST /api/v1/inventory/adjustment ────────────────────────────────

    public function adjustment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'warehouse_id'  => ['required', 'exists:warehouses,id'],
            'new_qty'       => ['required', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string'],
        ]);

        $movement = $this->service->adjustment($data);

        return response()->json([
            'message'  => 'Penyesuaian stok berhasil.',
            'movement' => $movement->load('ingredient', 'warehouse'),
        ], 201);
    }

    // ─── POST /api/v1/inventory/waste ─────────────────────────────────────

    public function waste(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'warehouse_id'  => ['required', 'exists:warehouses,id'],
            'qty'           => ['required', 'numeric', 'min:0.001'],
            'notes'         => ['required', 'string'],
        ]);

        try {
            $movement = $this->service->waste($data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'  => 'Waste berhasil dicatat.',
            'movement' => $movement->load('ingredient', 'warehouse'),
        ], 201);
    }

    // ─── POST /api/v1/inventory/transfer ──────────────────────────────────

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ingredient_id'    => ['required', 'exists:ingredients,id'],
            'from_warehouse_id'=> ['required', 'exists:warehouses,id'],
            'to_warehouse_id'  => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'qty'              => ['required', 'numeric', 'min:0.001'],
            'notes'            => ['nullable', 'string'],
        ]);

        try {
            $result = $this->service->transfer($data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Transfer stok berhasil.',
            'out'     => $result['out'],
            'in'      => $result['in'],
        ], 201);
    }

    // ─── POST /api/v1/inventory/opname ────────────────────────────────────

    public function opname(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lines'                   => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id'   => ['required', 'exists:ingredients,id'],
            'lines.*.warehouse_id'    => ['required', 'exists:warehouses,id'],
            'lines.*.physical_qty'    => ['required', 'numeric', 'min:0'],
            'lines.*.notes'           => ['nullable', 'string'],
        ]);

        $movements = $this->service->opname($data['lines'], auth()->id());

        ActivityLog::record(
            action:      'stock_opname',
            module:      'inventory',
            description: "Stock opname processed: " . count($movements) . " adjustments",
        );

        return response()->json([
            'message'   => 'Stock opname berhasil diproses.',
            'movements' => count($movements),
        ]);
    }

    // ─── PUT /api/v1/inventory/stock-movements/{id} ───────────────────────

    public function updateMovement(Request $request, int $id): JsonResponse
    {
        $movement = StockMovement::findOrFail($id);

        $data = $request->validate([
            'qty'       => ['required', 'numeric'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'notes'     => ['nullable', 'string'],
        ]);

        $ingredient = Ingredient::withTrashed()->find($movement->ingredient_id);

        DB::transaction(function () use ($movement, $ingredient, $data) {
            $oldQty = (float) $movement->qty;
            $newQty = (float) $data['qty'];
            $qtyDiff = $newQty - $oldQty;

            $oldUnitCost = (float) $movement->unit_cost;
            $newUnitCost = (float) $data['unit_cost'];

            // Update ingredient stock if active
            if ($ingredient && !$ingredient->deleted_at) {
                $ingredient->current_stock = max(0, (float)$ingredient->current_stock + $qtyDiff);

                // Recalculate average_cost for stock_in type or unit_cost change
                if ($movement->type === 'stock_in' || $newUnitCost != $oldUnitCost) {
                    if ($ingredient->current_stock > 0) {
                        $totalValuation = ($ingredient->current_stock * $ingredient->average_cost) + ($qtyDiff * $newUnitCost);
                        $ingredient->average_cost = max(0, round($totalValuation / max(1, $ingredient->current_stock), 2));
                    } else {
                        $ingredient->average_cost = $newUnitCost;
                    }
                    $ingredient->last_purchase_price = $newUnitCost;
                }

                $ingredient->save();
            }

            // Update stock movement record
            $movement->qty = $newQty;
            $movement->unit_cost = $newUnitCost;
            $movement->total_cost = round(abs($newQty) * $newUnitCost, 2);
            $movement->qty_after = (float)$movement->qty_before + $newQty;
            if (isset($data['notes'])) {
                $movement->notes = $data['notes'];
            }
            $movement->save();

            ActivityLog::record(
                action:      'update_stock_movement',
                module:      'inventory',
                entity:      'StockMovement',
                entityId:    $movement->id,
                description: "Memperbarui Riwayat Pergerakan Stok #{$movement->id} (Qty: {$oldQty} -> {$newQty}, HPP: Rp {$oldUnitCost} -> Rp {$newUnitCost})",
            );
        });

        return response()->json([
            'message'  => 'Riwayat pergerakan stok berhasil diperbarui & nilai HPP/stok disesuaikan!',
            'movement' => $movement->fresh()->load('ingredient', 'warehouse', 'user'),
        ]);
    }

    // ─── DELETE /api/v1/inventory/stock-movements/{id} ────────────────────

    public function deleteMovement(int $id): JsonResponse
    {
        $movement = StockMovement::findOrFail($id);

        DB::transaction(function () use ($movement) {
            $ingredient = Ingredient::withTrashed()->find($movement->ingredient_id);

            // Revert stock quantity impact on ingredient if active
            if ($ingredient && !$ingredient->deleted_at) {
                $qtyToReverse = (float) $movement->qty;
                $ingredient->current_stock = max(0, (float)$ingredient->current_stock - $qtyToReverse);
                $ingredient->save();
            }

            // Clean up corresponding auto-generated expense in Finance if this was a stock_in or purchase
            if ($ingredient && ($movement->type === 'stock_in' || str_contains($movement->notes, 'Tambah Inventory Baru') || str_contains($movement->notes, 'Stok Awal'))) {
                $rawName = preg_replace('/-TRASHED-\d+$/', '', $ingredient->name);
                \App\Models\Expense::whereNull('deleted_at')
                    ->where(function ($q) use ($ingredient, $rawName) {
                        $q->where('description', 'like', "%{$ingredient->name}%")
                          ->orWhere('description', 'like', "%{$rawName}%");
                    })
                    ->where(function ($q) use ($movement) {
                        $q->where('notes', 'like', '%Auto-generated%')
                          ->orWhere('amount', '=', $movement->total_cost);
                    })
                    ->limit(1)
                    ->update(['deleted_at' => now()]);
            }

            ActivityLog::record(
                action:      'delete_stock_movement',
                module:      'inventory',
                entity:      'StockMovement',
                entityId:    $movement->id,
                description: "Menghapus permanen Riwayat Pergerakan Stok #{$movement->id} (Bahan: " . ($ingredient->name ?? 'N/A') . ", Qty: {$movement->qty})",
            );

            // Delete permanently from stock_movements table
            $movement->delete();
        });

        return response()->json([
            'message' => 'Riwayat pergerakan stok berhasil dihapus permanen & catatan pengeluaran di Keuangan disesuaikan!',
        ]);
    }
}
