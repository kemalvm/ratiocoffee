<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'payment_due_dates')) {
                $table->json('payment_due_dates')->nullable()->after('next_payment_due_date')
                      ->comment('Daftar tanggal jatuh tempo pembayaran / angsuran (multi-tahap)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'payment_due_dates')) {
                $table->dropColumn('payment_due_dates');
            }
        });
    }
};
