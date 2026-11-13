<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    protected $fillable = [
        'tax_name',
        'tax_rate',
        'is_enabled',
        'tax_type',
        'service_charge_name',
        'service_charge_rate',
        'is_service_charge_enabled',
        'updated_by',
    ];

    protected $casts = [
        'tax_rate' => 'float',
        'is_enabled' => 'boolean',
        'service_charge_rate' => 'float',
        'is_service_charge_enabled' => 'boolean',
    ];

    public static function getSettings()
    {
        $setting = static::firstOrCreate([], [
            'tax_name' => 'Pajak Resto (PB1)',
            'tax_rate' => 10.00,
            'is_enabled' => true,
            'tax_type' => 'charged_to_hpp',
            'service_charge_name' => 'Service Charge',
            'service_charge_rate' => 0.00,
            'is_service_charge_enabled' => false,
        ]);

        if ($setting->tax_type !== 'charged_to_hpp') {
            $setting->update(['tax_type' => 'charged_to_hpp']);
        }

        return $setting;
    }
}
