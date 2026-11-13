<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientStock extends Model
{
    protected $fillable = ['ingredient_id', 'warehouse_id', 'qty', 'average_cost'];

    protected $casts = [
        'qty'          => 'float',
        'average_cost' => 'float',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getStockValueAttribute(): float
    {
        return (float) $this->qty * (float) $this->average_cost;
    }
}
