<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            if (!Schema::hasColumn('ingredients', 'net_content')) {
                $table->decimal('net_content', 12, 3)->default(1)->after('unit_id')
                      ->comment('Isi net / kapasitas volume per satuan (misal 1000 untuk 1 Liter = 1000 ml, 750 untuk botol sirup 750 ml)');
            }
            if (!Schema::hasColumn('ingredients', 'recipe_unit_symbol')) {
                $table->string('recipe_unit_symbol', 30)->nullable()->after('net_content')
                      ->comment('Satuan dosis takaran resep (misal: ml, gram, pcs, slice)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            if (Schema::hasColumn('ingredients', 'net_content')) {
                $table->dropColumn(['net_content', 'recipe_unit_symbol']);
            }
        });
    }
};
