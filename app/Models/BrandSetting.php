<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

if (!class_exists(\App\Models\BrandSetting::class)) {
    class BrandSetting extends Model
    {
        protected $fillable = ['key', 'value'];

        /**
         * Get a brand setting value by key.
         */
        public static function get(string $key, ?string $default = null): ?string
        {
            return static::where('key', $key)->value('value') ?? $default;
        }

        /**
         * Set (upsert) a brand setting value.
         */
        public static function set(string $key, ?string $value): void
        {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        /**
         * Return all brand settings as a key→value array with default fallbacks.
         */
        public static function allAsArray(): array
        {
            $defaultIcons = [
                'dashboard'       => '📊',
                'pos'             => '💳',
                'queue'           => '⏱️',
                'inventory'       => '📦',
                'suppliers'       => '🏢',
                'products'        => '🍵',
                'research'        => '🔬',
                'finance'         => '💰',
                'expenses'        => '💸',
                'reports'         => '📈',
                'revenue-sharing' => '💼',
                'tax'             => '🏛️',
                'admin-fee'       => '⚙️',
                'users'           => '👥',
                'security'        => '🛡️',
                'hq'              => '🏢',
                'logs'            => '📋',
                'logout'          => '🚪',
            ];

            $defaults = [
                'brand_name'            => 'RATIO COFFEE',
                'name'                  => 'RATIO COFFEE',
                'brand_tagline'         => 'ERP Control Center',
                'tagline'               => 'ERP Control Center',
                'operating_hours_open'  => '10:00',
                'operating_hours_close' => '22:00',
                'brand_logo'            => '☕',
                'address'               => '',
                'module_icons'          => json_encode($defaultIcons),
            ];
            
            $stored = static::all()->pluck('value', 'key')->toArray();
            $filtered = array_filter($stored, fn($v) => $v !== null && $v !== '');
            $merged = array_merge($defaults, $filtered);

            if (isset($merged['brand_name'])) {
                $merged['name'] = $merged['brand_name'];
            }
            if (isset($merged['brand_tagline'])) {
                $merged['tagline'] = $merged['brand_tagline'];
            }
            if (isset($merged['brand_logo'])) {
                $merged['logo']     = $merged['brand_logo'];
                $merged['logo_url'] = $merged['brand_logo'];
            }
            $merged['operatingHoursOpen']  = $merged['operating_hours_open'];
            $merged['operatingHoursClose'] = $merged['operating_hours_close'];

            return $merged;
        }
    }
}
