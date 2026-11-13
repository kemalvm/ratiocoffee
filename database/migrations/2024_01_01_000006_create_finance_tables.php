<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Chart of Accounts ────────────────────────────────────────────
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', [
                'asset',            // 1xxx
                'liability',        // 2xxx
                'equity',           // 3xxx
                'revenue',          // 4xxx
                'cost_of_goods',    // 5xxx
                'expense',          // 6xxx
            ]);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_header')->default(false);  // header (summary) accounts
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'active']);
        });

        // ─── Journal Entries ──────────────────────────────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('journal_number')->unique();
            $table->date('entry_date');
            $table->enum('type', [
                'manual',
                'pos_sale',
                'purchase',
                'purchase_payment',
                'adjustment',
                'payroll',
                'tax',
                'closing',
            ])->default('manual');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['outlet_id', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        // ─── Journal Entry Lines ───────────────────────────────────────────
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('account_id');
        });

        // ─── Expense Categories ────────────────────────────────────────────
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ─── Expenses ─────────────────────────────────────────────────────
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_number')->unique();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->string('attachment')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'paid'])->default('draft');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card', 'other'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['outlet_id', 'expense_date']);
        });

        // ─── Daily Closing ─────────────────────────────────────────────────
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->date('closing_date');
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_refunds', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->default(0);
            $table->decimal('cash_difference', 15, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->json('payment_breakdown')->nullable(); // {cash:xxx, qris:xxx, …}
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->timestamps();

            $table->unique(['outlet_id', 'closing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
