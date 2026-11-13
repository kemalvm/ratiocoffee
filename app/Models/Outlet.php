<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'address', 'phone', 'email', 'active', 'timezone'];

    protected $casts = ['active' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function defaultWarehouse()
    {
        return $this->hasOne(Warehouse::class)->where('is_default', true);
    }

    public function posOrders()
    {
        return $this->hasMany(PosOrder::class);
    }
}
