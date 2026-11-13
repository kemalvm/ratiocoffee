<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'category', 'products_summary', 'contact_person', 'phone', 'email', 'website',
        'address', 'tax_id', 'bank_name', 'bank_account', 'bank_account_name',
        'payment_term_days', 'next_payment_due_date', 'payment_due_dates', 'payment_method', 'credit_limit', 'status', 'notes',
    ];

    protected $casts = [
        'next_payment_due_date' => 'date:Y-m-d',
        'payment_due_dates'     => 'array',
    ];

    // ── Supplier Categories (inventory categories) ────────────────────────
    public static function categories(): array
    {
        return [
            'sirup'      => 'Sirup & Flavoring',
            'topping'    => 'Topping & Add-ons',
            'cup'        => 'Cup, Lid & Gelas',
            'kopi_susu'  => 'Biji Kopi & Susu (Dairy)',
            'packaging'  => 'Packaging & Consumables',
            'peralatan'  => 'Peralatan & Mesin',
            'lainnya'    => 'Lainnya',
            'bahan_baku' => 'Supplier Bahan Baku Utama',
            'importir'   => 'Importir / Specialty',
            'distributor'=> 'Distributor / Agen Besar',
        ];
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'default_supplier_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
