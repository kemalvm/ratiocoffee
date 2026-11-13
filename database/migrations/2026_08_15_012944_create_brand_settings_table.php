<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default values
        DB::table('brand_settings')->insert([
            ['key' => 'brand_name',    'value' => 'Ratio Coffee', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'brand_tagline', 'value' => 'ERP System',   'created_at' => now(), 'updated_at' => now()],
            ['key' => 'brand_logo',    'value' => '☕',            'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_settings');
    }
};
