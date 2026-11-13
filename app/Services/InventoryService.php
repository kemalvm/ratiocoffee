<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * InventoryService
 * ─────────────────────────────────────────────────────────────────────
 * Centralises all inventory mutations. All methods run inside a DB
 * transaction and return the resulting StockMovement record.
 *
 * Supports both FIFO and Moving-Average (MAVG) costing per ingredient.
 */
class InventoryService
{
    // ─── Stock In ─────────────────────────────────────────────────────────

    /**
     * Record inbound stock (manual top-up or purchase receipt).
     *
     * @param  array{
     *   ingredient_id: int,
     *   warehouse_id: int,
     *   qty: float,
     *   unit_cost: float,
     *   batch_number?: string,
     *   expiry_date?: string,
     *   notes?: string,
     *   reference_type?: string,
     *   reference_id?: int,
     * } $data
     */
    public function stockIn(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $ingredient = Ingredient::findOrFail($data['ingredient_id']);
            $stock      = $this->getOrCreateStock($data['ingredient_id'], $data['warehouse_id']);
            $qtyBefore  = (float) $stock->qty;

            $unitSym   = strtolower($ingredient->unit?->symbol ?? '');
            $net        = (float) ($ingredient->net_content > 0 ? $ingredient->net_content : 1.0);
            $isBaseQty = isset($data['is_base_qty'])
                ? (bool) $data['is_base_qty']
                : ($net <= 1.0 && in_array($unitSym, ['g', 'gr', 'gram', 'ml', 'pcs', 'lbr', 'scht']));

            $qty        = $isBaseQty ? (float) $data['qty'] : (float) $data['qty'] * $net;
            $unitCost   = $isBaseQty ? (float) $data['unit_cost'] : (float) $data['unit_cost'] / $net;

            // ── Update Moving Average Cost ─────────────────────────────
            if ($ingredient->costing_method === 'moving_average') {
                $newAvgCost = $qtyBefore > 0
                    ? (($qtyBefore * (float) $stock->average_cost) + ($qty * $unitCost)) / ($qtyBefore + $qty)
                    : $unitCost;
                $stock->update([
                    'qty'          => $qtyBefore + $qty,
                    'average_cost' => round($newAvgCost, 4),
                ]);
                $ingredient->update([
                    'current_stock'       => DB::table('ingredient_stocks')->where('ingredient_id', $ingredient->id)->sum('qty'),
                    'average_cost'        => round($newAvgCost, 4),
                    'last_purchase_price' => $unitCost,
                ]);
            }

            // ── FIFO: create batch ────────────────────────────────────
            if ($ingredient->costing_method === 'fifo') {
                StockBatch::create([
                    'ingredient_id' => $ingredient->id,
                    'warehouse_id'  => $data['warehouse_id'],
                    'batch_number'  => $data['batch_number'] ?? null,
                    'expiry_date'   => $data['expiry_date'] ?? null,
                    'qty_in'        => $qty,
                    'qty_remaining' => $qty,
                    'unit_cost'     => $unitCost,
                    'received_at'   => now(),
                ]);
                $stock->update(['qty' => $qtyBefore + $qty]);
                $ingredient->update([
                    'current_stock'       => DB::table('ingredient_stocks')->where('ingredient_id', $ingredient->id)->sum('qty'),
                    'last_purchase_price' => $unitCost,
                ]);
            }

            if (!empty($data['supplier_id']) && !$ingredient->default_supplier_id) {
                $ingredient->update(['default_supplier_id' => $data['supplier_id']]);
            }

            $movement = $this->recordMovement(array_merge($data, [
                'type'       => 'stock_in',
                'qty'        => $qty,
                'qty_before' => $qtyBefore,
                'qty_after'  => $qtyBefore + $qty,
                'unit_cost'  => $unitCost,
                'total_cost' => $qty * $unitCost,
            ]));

            ActivityLog::record(
                action:      'stock_in',
                module:      'inventory',
                entity:      'Ingredient',
                entityId:    $ingredient->id,
                description: "Stock In {$qty} {$ingredient->unit?->symbol} of {$ingredient->name}",
            );

            return $movement;
        });
    }

    // ─── Stock Out ────────────────────────────────────────────────────────

    /**
     * Consume stock (POS usage, production, manual issue).
     * Uses FIFO depletion if costing_method === 'fifo'.
     */
    public function stockOut(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $ingredient = Ingredient::findOrFail($data['ingredient_id']);
            $stock      = $this->getOrCreateStock($data['ingredient_id'], $data['warehouse_id']);

            $qtyBefore = (float) $stock->qty;
            $qty       = (float) $data['qty'];

            if ($qtyBefore < $qty) {
                throw new \RuntimeException("Stok tidak cukup. Tersedia: {$qtyBefore}, Dibutuhkan: {$qty}");
            }

            $unitCost = 0.0;

            // ── FIFO depletion ────────────────────────────────────────
            if ($ingredient->costing_method === 'fifo') {
                $unitCost  = $this->depleteFifo($ingredient->id, $data['warehouse_id'], $qty);
            } else {
                $unitCost = (float) $stock->average_cost;
            }

            $stock->update(['qty' => $qtyBefore - $qty]);
            $ingredient->update([
                'current_stock' => DB::table('ingredient_stocks')->where('ingredient_id', $ingredient->id)->sum('qty'),
            ]);

            $movement = $this->recordMovement(array_merge($data, [
                'type'       => $data['type'] ?? 'stock_out',
                'qty'        => -$qty,
                'qty_before' => $qtyBefore,
                'qty_after'  => $qtyBefore - $qty,
                'unit_cost'  => $unitCost,
                'total_cost' => $qty * $unitCost,
            ]));

            ActivityLog::record(
                action:      'stock_out',
                module:      'inventory',
                entity:      'Ingredient',
                entityId:    $ingredient->id,
                description: "Stock Out {$qty} {$ingredient->unit?->symbol} of {$ingredient->name}",
            );

            return $movement;
        });
    }

    // ─── Stock Adjustment ─────────────────────────────────────────────────

    /**
     * Force-set stock to a new quantity (manual correction).
     */
    public function adjustment(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $ingredient = Ingredient::findOrFail($data['ingredient_id']);
            $stock      = $this->getOrCreateStock($data['ingredient_id'], $data['warehouse_id']);

            $qtyBefore  = (float) $stock->qty;
            $newQty     = (float) $data['new_qty'];
            $diff       = $newQty - $qtyBefore;

            $stock->update(['qty' => $newQty]);
            $ingredient->update([
                'current_stock' => DB::table('ingredient_stocks')->where('ingredient_id', $ingredient->id)->sum('qty'),
            ]);

            $movement = $this->recordMovement(array_merge($data, [
                'type'       => 'adjustment',
                'qty'        => $diff,
                'qty_before' => $qtyBefore,
                'qty_after'  => $newQty,
                'unit_cost'  => (float) $stock->average_cost,
                'total_cost' => abs($diff) * (float) $stock->average_cost,
            ]));

            ActivityLog::record(
                action:      'stock_adjustment',
                module:      'inventory',
                entity:      'Ingredient',
                entityId:    $ingredient->id,
                description: "Adjustment {$ingredient->name}: {$qtyBefore} → {$newQty}",
            );

            return $movement;
        });
    }

    // ─── Waste ────────────────────────────────────────────────────────────

    public function waste(array $data): StockMovement
    {
        return $this->stockOut(array_merge($data, ['type' => 'waste']));
    }

    // ─── Stock Transfer ───────────────────────────────────────────────────

    /**
     * Move stock from one warehouse to another.
     */
    public function transfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $out = $this->stockOut([
                'ingredient_id' => $data['ingredient_id'],
                'warehouse_id'  => $data['from_warehouse_id'],
                'qty'           => $data['qty'],
                'type'          => 'transfer_out',
                'notes'         => $data['notes'] ?? null,
                'reference_type'=> $data['reference_type'] ?? null,
                'reference_id'  => $data['reference_id'] ?? null,
            ]);

            $ingredient = Ingredient::findOrFail($data['ingredient_id']);
            $in = $this->stockIn([
                'ingredient_id' => $data['ingredient_id'],
                'warehouse_id'  => $data['to_warehouse_id'],
                'qty'           => $data['qty'],
                'unit_cost'     => abs($out->unit_cost),
                'notes'         => $data['notes'] ?? null,
                'type'          => 'transfer_in',
                'is_base_qty'   => true,
            ]);

            ActivityLog::record(
                action:      'stock_transfer',
                module:      'inventory',
                entity:      'Ingredient',
                entityId:    $ingredient->id,
                description: "Transfer {$data['qty']} {$ingredient->unit?->symbol} of {$ingredient->name} from WH#{$data['from_warehouse_id']} to WH#{$data['to_warehouse_id']}",
            );

            return ['out' => $out, 'in' => $in];
        });
    }

    // ─── Stock Opname ─────────────────────────────────────────────────────

    /**
     * Process a stock opname (physical count) result.
     * Each line in $lines: ['ingredient_id', 'warehouse_id', 'physical_qty', 'notes']
     */
    public function opname(array $lines, int $doneByUserId): array
    {
        $movements = [];
        foreach ($lines as $line) {
            $stock = $this->getOrCreateStock($line['ingredient_id'], $line['warehouse_id']);
            $diff  = (float) $line['physical_qty'] - (float) $stock->qty;
            if ($diff == 0) continue;

            $movements[] = $this->adjustment([
                'ingredient_id' => $line['ingredient_id'],
                'warehouse_id'  => $line['warehouse_id'],
                'new_qty'       => $line['physical_qty'],
                'type'          => 'opname',
                'notes'         => $line['notes'] ?? 'Stock opname',
                'user_id'       => $doneByUserId,
            ]);
        }
        return $movements;
    }

    // ─── Private Helpers ──────────────────────────────────────────────────

    private function getOrCreateStock(int $ingredientId, int $warehouseId): IngredientStock
    {
        return IngredientStock::firstOrCreate(
            ['ingredient_id' => $ingredientId, 'warehouse_id' => $warehouseId],
            ['qty' => 0, 'average_cost' => 0]
        );
    }

    /**
     * Depletes FIFO batches oldest-first.
     * Returns the weighted-average unit cost consumed.
     */
    private function depleteFifo(int $ingredientId, int $warehouseId, float $qtyNeeded): float
    {
        $batches = StockBatch::where('ingredient_id', $ingredientId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->get();

        $remaining    = $qtyNeeded;
        $totalCost    = 0.0;
        $totalConsumed = 0.0;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $consume = min((float) $batch->qty_remaining, $remaining);
            $totalCost    += $consume * (float) $batch->unit_cost;
            $totalConsumed += $consume;
            $remaining    -= $consume;
            $batch->update(['qty_remaining' => (float) $batch->qty_remaining - $consume]);
        }

        return $totalConsumed > 0 ? $totalCost / $totalConsumed : 0;
    }

    private function recordMovement(array $data): StockMovement
    {
        return StockMovement::create([
            'ingredient_id'  => $data['ingredient_id'],
            'warehouse_id'   => $data['warehouse_id'],
            'user_id'        => $data['user_id'] ?? auth()->id(),
            'type'           => $data['type'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id'   => $data['reference_id'] ?? null,
            'qty'            => $data['qty'],
            'qty_before'     => $data['qty_before'],
            'qty_after'      => $data['qty_after'],
            'unit_cost'      => $data['unit_cost'],
            'total_cost'     => $data['total_cost'],
            'batch_number'   => $data['batch_number'] ?? null,
            'expiry_date'    => $data['expiry_date'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'movement_at'    => now(),
        ]);
    }
}
