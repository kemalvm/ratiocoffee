<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'modifiers' => 'array',
    ];

    protected $appends = ['temperature', 'order_type'];

    public function getTemperatureAttribute(): string
    {
        $mods = is_array($this->modifiers) 
            ? $this->modifiers 
            : (is_string($this->modifiers) ? json_decode($this->modifiers, true) : []);
        
        if (!empty($mods['temperature'])) {
            return in_array(strtolower($mods['temperature']), ['iced', 'cold', 'ice', 'dingin']) ? 'iced' : 'hot';
        }
        
        $notes = strtolower($this->notes ?? '');
        if (str_contains($notes, '[iced]') || str_contains($notes, '[cold]')) {
            return 'iced';
        }
        if (str_contains($notes, '[hot]')) {
            return 'hot';
        }

        $prodName = strtolower($this->product_name ?? '');
        if (str_contains($prodName, 'ice') || str_contains($prodName, 'iced') || str_contains($prodName, 'cold') || str_contains($prodName, 'dingin')) {
            return 'iced';
        }

        return 'hot';
    }

    public function getOrderTypeAttribute(): string
    {
        $mods = is_array($this->modifiers) 
            ? $this->modifiers 
            : (is_string($this->modifiers) ? json_decode($this->modifiers, true) : []);
        
        if (!empty($mods['order_type'])) {
            return $mods['order_type'];
        }
        
        $notes = strtolower($this->notes ?? '');
        if (str_contains($notes, '[takeaway]')) {
            return 'takeaway';
        }
        if (str_contains($notes, '[dine in]')) {
            return 'dine_in';
        }
        
        return $this->order?->order_type ?? 'dine_in';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
