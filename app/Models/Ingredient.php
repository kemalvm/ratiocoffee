<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'unit_id', 'net_content', 'recipe_unit_symbol', 'default_supplier_id',
        'code', 'name', 'barcode', 'description',
        'min_stock', 'reorder_point', 'reorder_qty',
        'current_stock', 'average_cost', 'last_purchase_price', 'standard_cost',
        'costing_method', 'track_batch', 'track_expiry',
        'status', 'image',
    ];

    protected $casts = [
        'net_content'         => 'float',
        'min_stock'           => 'float',
        'reorder_point'       => 'float',
        'reorder_qty'         => 'float',
        'current_stock'       => 'float',
        'average_cost'        => 'float',
        'last_purchase_price' => 'float',
        'standard_cost'       => 'float',
        'track_batch'         => 'boolean',
        'track_expiry'        => 'boolean',
    ];

    protected $appends = [
        'stock_value',
        'is_critical',
        'cost_per_unit',
        'recipe_unit_cost',
        'purchase_price',
    ];

    // ─── Relations ────────────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function defaultSupplier()
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function stocks()
    {
        return $this->hasMany(IngredientStock::class);
    }

    public function batches()
    {
        return $this->hasMany(StockBatch::class)->orderBy('received_at');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('movement_at');
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    // ─── Computed Helpers ─────────────────────────────────────────────────

    /**
     * Total stock value = current_stock × average_cost
     */
    public function getStockValueAttribute(): float
    {
        $stock = (float) $this->current_stock;
        $net   = (float) ($this->net_content > 0 ? $this->net_content : 1);
        $cost  = (float) $this->average_cost;
        $pkgPrice = (float) $this->purchase_price;

        if ($pkgPrice > 0 && $net > 0) {
            $val = ($stock / $net) * $pkgPrice;
            return (float) (abs($val - round($val)) < 0.99 ? round($val) : round($val, 2));
        }

        if ($cost <= 0) {
            $cost = (float) ($this->last_purchase_price > 0
                ? ($this->net_content > 0 ? $this->last_purchase_price / $this->net_content : $this->last_purchase_price)
                : $this->standard_cost);
        }

        $val = $stock * $cost;
        return (float) (abs($val - round($val)) < 0.99 ? round($val) : round($val, 2));
    }

    /**
     * Estimated package purchase price
     */
    public function getPurchasePriceAttribute(): float
    {
        $net       = (float) ($this->net_content > 0 ? $this->net_content : 1);
        $lastPrice = (float) $this->last_purchase_price;
        $avgCost   = (float) $this->average_cost;

        if ($lastPrice >= ($avgCost * $net * 0.8) && $lastPrice > 100) {
            $lp = round($lastPrice, 2);
            return (float) (abs($lp - round($lp)) < 0.99 ? round($lp) : $lp);
        }

        $calc = $avgCost * $net;
        if ($calc > 0) {
            return (float) (abs($calc - round($calc)) < 0.99 ? round($calc) : round($calc, 2));
        }

        return (float) (abs($lastPrice - round($lastPrice)) < 0.99 ? round($lastPrice) : round($lastPrice, 2));
    }

    /**
     * True if stock is at or below the minimum threshold
     */
    public function getIsCriticalAttribute(): bool
    {
        return (float) $this->current_stock <= (float) $this->min_stock;
    }

    /**
     * Cost per 1 unit (used by HPP engine)
     */
    public function getCostPerUnitAttribute(): float
    {
        return (float) ($this->costing_method === 'fifo'
            ? $this->nextFifoCost()
            : $this->average_cost);
    }

    public function getRecipeUnitCostAttribute(): float
    {
        $baseCost = $this->cost_per_unit;
        if ($baseCost <= 0) {
            $baseCost = (float) $this->standard_cost;
        }

        $sym = strtolower($this->unit?->symbol ?? '');

        if ($sym === 'kg' || $sym === 'kilogram') {
            return round($baseCost / 1000.0, 4);
        }
        if ($sym === 'l' || $sym === 'ltr' || $sym === 'liter') {
            return round($baseCost / 1000.0, 4);
        }
        if ($sym === 'cl' || $sym === 'centiliter') {
            return round($baseCost / 10.0, 4);
        }
        if ($sym === 'ons') {
            return round($baseCost / 100.0, 4);
        }

        return round($baseCost, 4);
    }

    public function convertRecipeQtyToMainStockQty(float $recipeQty): float
    {
        $sym = strtolower($this->unit?->symbol ?? '');

        if ($sym === 'kg' || $sym === 'kilogram') {
            return $recipeQty / 1000.0;
        }
        if ($sym === 'l' || $sym === 'ltr' || $sym === 'liter') {
            return $recipeQty / 1000.0;
        }
        if ($sym === 'cl' || $sym === 'centiliter') {
            return $recipeQty / 10.0;
        }
        if ($sym === 'ons') {
            return $recipeQty / 100.0;
        }

        return $recipeQty;
    }

    /**
     * Get cost of the oldest available FIFO batch
     */
    public function nextFifoCost(?int $warehouseId = null): float
    {
        $query = $this->batches()->where('qty_remaining', '>', 0);
        if ($warehouseId) $query->where('warehouse_id', $warehouseId);
        $batch = $query->first();
        return $batch ? (float) $batch->unit_cost : (float) $this->average_cost;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCritical($query)
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    public static function generateNextCode(): string
    {
        $activeCodes = static::whereNull('deleted_at')->pluck('code');
        $maxNum = 0;

        foreach ($activeCodes as $code) {
            if (preg_match('/ING-(\d+)/i', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = $maxNum + 1;
        return 'ING-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::deleting(function (Ingredient $ingredient) {
            if (!$ingredient->isForceDeleting() && !str_contains($ingredient->code, '-TRASHED-')) {
                $ingredient->code = $ingredient->code . '-TRASHED-' . $ingredient->id;
                $ingredient->saveQuietly();
            }
        });

        static::restoring(function (Ingredient $ingredient) {
            $originalBaseCode = preg_replace('/-TRASHED-\d+$/', '', $ingredient->code);
            $conflict = static::where('id', '!=', $ingredient->id)->where('code', $originalBaseCode)->exists();
            if ($conflict) {
                $ingredient->code = static::generateNextCode();
            } else {
                $ingredient->code = $originalBaseCode;
            }
        });
    }
}
