<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    protected $fillable = [
        'ingredient_id', 'warehouse_id', 'batch_number',
        'manufacture_date', 'expiry_date',
        'qty_in', 'qty_remaining', 'unit_cost', 'received_at',
    ];

    protected $casts = [
        'manufacture_date' => 'date',
        'expiry_date'      => 'date',
        'received_at'      => 'datetime',
        'qty_in'           => 'float',
        'qty_remaining'    => 'float',
        'unit_cost'        => 'float',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->expiry_date &&
               !$this->isExpired() &&
               $this->expiry_date->diffInDays(now()) <= $days;
    }
}
