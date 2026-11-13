<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Expenses (CEO payment reminders) ──────────────────────────────
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->string('name');                                   // e.g. "Gaji Karyawan"
                $table->decimal('amount', 14, 2)->default(0);            // nominal
                $table->enum('frequency', ['daily','weekly','monthly']); // tempo
                $table->tinyInteger('reminder_day')->nullable();         // 0=Sun..6=Sat for weekly
                $table->tinyInteger('reminder_date')->nullable();        // 1-31 for monthly
                $table->string('bank_name')->nullable();                 // e.g. "BCA"
                $table->string('bank_account')->nullable();              // e.g. "1234567890"
                $table->string('account_holder')->nullable();            // Nama pemilik rekening
                $table->text('notes')->nullable();
                $table->boolean('is_revenue_sharing')->default(false);   // flag for operator link
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Expense Payments (per-period payment tracking) ─────────────
        if (!Schema::hasTable('expense_payments')) {
            Schema::create('expense_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
                $table->date('due_date');                                 // kapan jatuh tempo
                $table->decimal('amount', 14, 2)->default(0);
                $table->enum('status', ['unpaid','paid'])->default('unpaid');
                $table->timestamp('paid_at')->nullable();
                $table->string('proof')->nullable();                      // bukti transfer / note
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                // Operator verification (for revenue sharing payments)
                $table->enum('operator_status', ['pending','verified','disputed'])->nullable();
                $table->timestamp('operator_verified_at')->nullable();
                $table->text('operator_notes')->nullable();
                $table->foreignId('operator_verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expenses');
    }
};
