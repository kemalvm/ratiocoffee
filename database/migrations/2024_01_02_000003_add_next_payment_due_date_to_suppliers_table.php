<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'next_payment_due_date')) {
                $table->date('next_payment_due_date')->nullable()->after('payment_term_days')
                      ->comment('Tanggal perkiraan/jadwal jatuh tempo pembayaran berikutnya untuk supplier ini');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'next_payment_due_date')) {
                $table->dropColumn('next_payment_due_date');
            }
        });
    }
};
