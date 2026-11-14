<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('expenses', 'frequency')) {
                $table->enum('frequency', ['daily', 'weekly', 'monthly'])->nullable()->after('amount');
            }
            if (!Schema::hasColumn('expenses', 'reminder_day')) {
                $table->tinyInteger('reminder_day')->nullable()->after('frequency');
            }
            if (!Schema::hasColumn('expenses', 'reminder_date')) {
                $table->tinyInteger('reminder_date')->nullable()->after('reminder_day');
            }
            if (!Schema::hasColumn('expenses', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('reminder_date');
            }
            if (!Schema::hasColumn('expenses', 'bank_account')) {
                $table->string('bank_account')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('expenses', 'account_holder')) {
                $table->string('account_holder')->nullable()->after('bank_account');
            }
            if (!Schema::hasColumn('expenses', 'is_revenue_sharing')) {
                $table->boolean('is_revenue_sharing')->default(false)->after('account_holder');
            }
            if (!Schema::hasColumn('expenses', 'notes')) {
                $table->text('notes')->nullable()->after('is_revenue_sharing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['name', 'frequency', 'reminder_day', 'reminder_date', 'bank_name', 'bank_account', 'account_holder', 'is_revenue_sharing'] as $col) {
                if (Schema::hasColumn('expenses', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }
};
