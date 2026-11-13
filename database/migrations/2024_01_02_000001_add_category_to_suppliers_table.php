<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Add category to suppliers ─────────────────────────────────────
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('category')->nullable()->after('code')
                  ->comment('Kategori supplier: bahan_baku, packaging, peralatan, dll');
            $table->string('website')->nullable()->after('email');
            $table->enum('payment_method', ['cash', 'transfer', 'credit', 'mixed'])
                  ->default('transfer')->after('payment_term_days');
            $table->decimal('credit_limit', 15, 2)->default(0)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['category', 'website', 'payment_method', 'credit_limit']);
        });
    }
};
