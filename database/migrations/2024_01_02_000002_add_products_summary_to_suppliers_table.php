<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'products_summary')) {
                $table->text('products_summary')->nullable()->after('name')
                      ->comment('Ringkasan daftar produk / bahan baku yang dibeli dari supplier ini');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'products_summary')) {
                $table->dropColumn('products_summary');
            }
        });
    }
};
