<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Product Categories ────────────────────────────────────────────
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Products ─────────────────────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('hpp', 15, 4)->default(0);            // latest computed HPP
            $table->decimal('gross_margin', 8, 4)->default(0);    // (price - hpp) / price
            $table->decimal('markup_pct', 8, 4)->default(0);      // (price - hpp) / hpp
            $table->boolean('has_variant')->default(false);
            $table->boolean('available_for_sale')->default(true);
            $table->boolean('available_for_pos')->default(true);
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index('available_for_pos');
        });

        // ─── Product Variants ──────────────────────────────────────────────
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                   // "Large", "Hot", "Iced" …
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('hpp', 15, 4)->default(0);
            $table->decimal('additional_price', 15, 2)->default(0); // add‑on to base price
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Product Modifiers (Add‑ons) ──────────────────────────────────
        Schema::create('product_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // "Sugar Level", "Ice Level", "Extra Shot"
            $table->boolean('required')->default(false);
            $table->boolean('multiple')->default(false); // can select multiple options
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_id')->constrained('product_modifiers')->cascadeOnDelete();
            $table->string('name');                   // "0%", "25%", "Full Sugar"
            $table->decimal('additional_price', 15, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── Product ↔ Modifier pivot ──────────────────────────────────────
        Schema::create('product_modifier_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->constrained('product_modifiers')->cascadeOnDelete();
            $table->unique(['product_id', 'modifier_id']);
        });

        // ─── Recipes ──────────────────────────────────────────────────────
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('yield_qty', 10, 3)->default(1);   // how many servings this recipe makes
            $table->decimal('ingredient_cost', 15, 4)->default(0);
            $table->decimal('packaging_cost', 15, 4)->default(0);
            $table->decimal('operational_cost', 15, 4)->default(0);
            $table->decimal('total_hpp', 15, 4)->default(0);
            $table->decimal('suggested_price', 15, 2)->default(0);
            $table->decimal('target_margin_pct', 8, 4)->default(0.35); // default 35% margin
            $table->boolean('active')->default(true);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
        });

        // ─── Recipe Ingredients ────────────────────────────────────────────
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 4);            // qty used per recipe yield
            $table->decimal('unit_cost', 15, 4)->default(0);   // snapshotted at last calc
            $table->decimal('cost_contribution', 15, 4)->default(0); // qty × unit_cost
            $table->enum('type', ['main', 'packaging', 'garnish'])->default('main');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
        });

        // ─── Operational Cost Configs ──────────────────────────────────────
        Schema::create('operational_cost_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('name');                   // Electricity, Labor per cup, Gas
            $table->decimal('amount_per_unit', 15, 4);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_cost_configs');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('product_modifier_assignments');
        Schema::dropIfExists('product_modifier_options');
        Schema::dropIfExists('product_modifiers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
