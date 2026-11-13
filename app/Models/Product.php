<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'has_variant'         => 'boolean',
        'is_bundling'          => 'boolean',
        'bundle_data'         => 'array',
        'available_for_sale'  => 'boolean',
        'available_for_pos'   => 'boolean',
        'selling_price'       => 'decimal:2',
        'hpp'                 => 'decimal:4',
        'gross_margin'        => 'decimal:4',
    ];

    protected $appends = ['cogs'];

    public function getCogsAttribute(): float
    {
        if ((float)($this->hpp ?? 0) > 0) {
            return (float) $this->hpp;
        }

        // Dynamic fallback: calculate from recipe ingredients & ingredient recipe_unit_cost
        if ($this->relationLoaded('recipe') && $this->recipe && $this->recipe->ingredients) {
            $totalCost = 0;
            foreach ($this->recipe->ingredients as $rIng) {
                $ing = $rIng->ingredient;
                if ($ing) {
                    $totalCost += ((float)$rIng->qty * (float)$ing->recipe_unit_cost);
                }
            }
            if ($totalCost > 0) {
                $yield = max(1, (float)$this->recipe->yield_qty);
                return round(($totalCost + (float)$this->recipe->packaging_cost + (float)$this->recipe->operational_cost) / $yield, 2);
            }
        }

        return 0.0;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(ProductModifier::class, 'product_modifier_assignments');
    }

    public static function generateNextSku(): string
    {
        $activeSkus = static::whereNull('deleted_at')->pluck('sku');
        $maxNum = 0;

        foreach ($activeSkus as $sku) {
            if (preg_match('/SKU-(\d+)/i', $sku, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = $maxNum + 1;
        return 'SKU-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::deleting(function (Product $product) {
            if (!$product->isForceDeleting() && !str_contains($product->sku, '-TRASHED-')) {
                $product->sku = $product->sku . '-TRASHED-' . $product->id;
                $product->saveQuietly();
            }
        });

        static::restoring(function (Product $product) {
            $originalBaseSku = preg_replace('/-TRASHED-\d+$/', '', $product->sku);
            $conflict = static::where('id', '!=', $product->id)->where('sku', $originalBaseSku)->exists();
            if ($conflict) {
                $product->sku = static::generateNextSku();
            } else {
                $product->sku = $originalBaseSku;
            }
        });
    }
}
