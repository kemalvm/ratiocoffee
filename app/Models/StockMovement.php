<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'ingredient_id', 'warehouse_id', 'user_id',
        'type', 'reference_type', 'reference_id',
        'qty', 'qty_before', 'qty_after',
        'unit_cost', 'total_cost',
        'batch_number', 'expiry_date',
        'notes', 'movement_at',
    ];

    protected $casts = [
        'qty'        => 'float',
        'qty_before' => 'float',
        'qty_after'  => 'float',
        'unit_cost'  => 'float',
        'total_cost' => 'float',
        'expiry_date'=> 'date',
        'movement_at'=> 'datetime',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo('reference');
    }

    /**
     * Label map for movement types
     */
    public static function typeLabel(string $type): string
    {
        return match($type) {
            'stock_in'      => 'Stock In',
            'stock_out'     => 'Stock Out',
            'adjustment'    => 'Adjustment',
            'opname'        => 'Stock Opname',
            'transfer_in'   => 'Transfer Masuk',
            'transfer_out'  => 'Transfer Keluar',
            'waste'         => 'Waste',
            'return'        => 'Retur Supplier',
            default         => ucfirst($type),
        };
    }

    public function scopeByIngredient($query, int $ingredientId)
    {
        return $query->where('ingredient_id', $ingredientId);
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('movement_at', '>=', now()->subDays($days));
    }
}
