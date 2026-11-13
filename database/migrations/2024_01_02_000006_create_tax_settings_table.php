<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tax_name')->default('Pajak Resto (PB1)');
            $table->decimal('tax_rate', 5, 2)->default(10.00); // 10.00%
            $table->boolean('is_enabled')->default(true);
            $table->enum('tax_type', ['added_at_checkout', 'included_in_price'])->default('added_at_checkout');
            
            $table->string('service_charge_name')->default('Service Charge');
            $table->decimal('service_charge_rate', 5, 2)->default(0.00);
            $table->boolean('is_service_charge_enabled')->default(false);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Insert default initial row
        DB::table('tax_settings')->insert([
            'tax_name' => 'Pajak Resto (PB1)',
            'tax_rate' => 10.00,
            'is_enabled' => true,
            'tax_type' => 'added_at_checkout',
            'service_charge_name' => 'Service Charge',
            'service_charge_rate' => 0.00,
            'is_service_charge_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
