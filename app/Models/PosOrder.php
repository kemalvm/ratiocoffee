<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosOrder extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'completed_at' => 'datetime',
        'voided_at'    => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(PosPayment::class, 'order_id')->latestOfMany();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PosRefund::class, 'order_id');
    }
}