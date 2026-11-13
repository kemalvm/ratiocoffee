<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['outlet_id', 'code', 'name', 'address', 'is_default', 'active'];

    protected $casts = ['is_default' => 'boolean', 'active' => 'boolean'];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function ingredientStocks()
    {
        return $this->hasMany(IngredientStock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
