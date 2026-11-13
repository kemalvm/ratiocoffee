<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('research_product_ingredients')) {
            Schema::create('research_product_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('research_product_id')->constrained('research_products')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
                $table->decimal('quantity', 10, 3)->default(0);
                $table->string('unit_symbol', 50)->nullable();
                $table->decimal('unit_cost', 14, 4)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_product_ingredients');
    }
};
