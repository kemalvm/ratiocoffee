<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->string('type', 30)->default('main')->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->enum('type', ['main', 'packaging', 'garnish'])->default('main')->change();
        });
    }
};
