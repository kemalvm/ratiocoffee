<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'active'           => 'boolean',
        'selling_price'    => 'decimal:2',
        'hpp'              => 'decimal:4',
        'additional_price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class, 'variant_id');
    }
}
