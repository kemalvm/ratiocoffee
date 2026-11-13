<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Warehouses ───────────────────────────────────────────────────
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('outlet_id');
        });

        // ─── Ingredient Categories ─────────────────────────────────────────
        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#6366f1'); // for UI badge
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Units of Measure ─────────────────────────────────────────────
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // Milliliter, Gram, Piece …
            $table->string('symbol');                 // ml, g, pcs …
            $table->enum('type', ['volume', 'weight', 'piece', 'length', 'other'])->default('other');
            $table->timestamps();
        });

        // ─── Suppliers ────────────────────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();      // NPWP
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->integer('payment_term_days')->default(0); // NET 30
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Ingredients (Master) ─────────────────────────────────────────
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('ingredient_categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('barcode')->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('min_stock', 12, 3)->default(0);   // minimum stock threshold
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->decimal('reorder_qty', 12, 3)->default(0);
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('average_cost', 15, 4)->default(0); // moving average cost
            $table->decimal('last_purchase_price', 15, 4)->default(0);
            $table->decimal('standard_cost', 15, 4)->default(0); // budget/standard cost
            $table->enum('costing_method', ['fifo', 'moving_average'])->default('moving_average');
            $table->boolean('track_batch')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index('unit_id');
        });

        // ─── Ingredient Stock per Warehouse ────────────────────────────────
        Schema::create('ingredient_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 3)->default(0);
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'warehouse_id']);
        });

        // ─── Stock Batches (FIFO) ─────────────────────────────────────────
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty_in', 12, 3)->default(0);
            $table->decimal('qty_remaining', 12, 3)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['ingredient_id', 'warehouse_id']);
            $table->index('expiry_date');
        });

        // ─── Stock Movements ──────────────────────────────────────────────
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'stock_in',        // purchase receiving
                'stock_out',       // production/POS usage
                'adjustment',      // manual adjustment
                'opname',          // stock opname
                'transfer_in',     // received from other warehouse
                'transfer_out',    // sent to other warehouse
                'waste',           // waste/disposal
                'return',          // supplier return
            ]);
            $table->string('reference_type')->nullable();  // App\Models\PurchaseOrderLine
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty', 12, 3);               // positive = in, negative = out
            $table->decimal('qty_before', 12, 3)->default(0);
            $table->decimal('qty_after', 12, 3)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('movement_at')->useCurrent();
            $table->timestamps();

            $table->index(['ingredient_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('ingredient_stocks');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('units');
        Schema::dropIfExists('ingredient_categories');
        Schema::dropIfExists('warehouses');
    }
};
