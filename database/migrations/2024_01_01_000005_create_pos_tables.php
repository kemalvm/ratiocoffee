<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Customers ────────────────────────────────────────────────────
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable()->unique();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── Discounts / Vouchers / Promos ────────────────────────────────
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete(); // null = all outlets
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['discount_pct', 'discount_amount', 'free_item', 'buy_x_get_y'])->default('discount_pct');
            $table->decimal('value', 15, 4)->default(0);        // % or amount
            $table->decimal('min_purchase', 15, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->integer('usage_limit')->nullable();         // null = unlimited
            $table->integer('usage_count')->default(0);
            $table->integer('per_customer_limit')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── POS Orders ───────────────────────────────────────────────────
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();  // A001, A002 …
            $table->string('queue_number', 10)->nullable();
            $table->enum('order_type', ['dine_in', 'takeaway', 'delivery'])->default('dine_in');
            $table->string('table_number')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);   // total HPP for this order
            $table->decimal('gross_profit', 15, 2)->default(0); // total - cost
            $table->enum('status', [
                'pending',      // created but not confirmed
                'confirmed',    // payment confirmed
                'preparing',    // kitchen is preparing
                'ready',        // ready for pickup
                'completed',    // delivered/served
                'void',         // voided
                'refunded',     // refunded
                'held',         // on hold
            ])->default('pending');
            $table->text('notes')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->integer('loyalty_points_earned')->default(0);
            $table->integer('loyalty_points_used')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['outlet_id', 'status']);
            $table->index(['cashier_id', 'created_at']);
            $table->index('created_at');
        });

        // ─── POS Order Lines ──────────────────────────────────────────────
        Schema::create('pos_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');           // snapshot at time of sale
            $table->string('variant_name')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 4);
            $table->integer('qty');
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);       // (unit_price - discount) * qty
            $table->json('modifiers')->nullable();    // [{"name":"Sugar","option":"25%","price":0}]
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });

        // ─── POS Payments ─────────────────────────────────────────────────
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->enum('method', ['cash', 'qris', 'bank_transfer', 'debit_card', 'credit_card', 'loyalty_points', 'other']);
            $table->decimal('amount', 15, 2);
            $table->decimal('tendered', 15, 2)->default(0);   // cash given by customer
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->timestamps();

            $table->index('order_id');
        });

        // ─── POS Refunds ──────────────────────────────────────────────────
        Schema::create('pos_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->string('refund_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->enum('reason', ['wrong_item', 'quality_issue', 'customer_request', 'other']);
            $table->text('notes')->nullable();
            $table->enum('refund_method', ['cash', 'original_method', 'store_credit'])->default('cash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_refunds');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_lines');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('customers');
    }
};
