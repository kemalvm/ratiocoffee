<?php
require 'c:/xampp/htdocs/ratio_coffee/vendor/autoload.php';
$app = require_once 'c:/xampp/htdocs/ratio_coffee/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CLEANING TRASHED / SOFT-DELETED RECORDS & DUMMY TRANSACTIONS ===\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

// 1. Force delete trashed ingredients
if (Schema::hasTable('ingredients')) {
    $count = DB::table('ingredients')->whereNotNull('deleted_at')->count();
    DB::table('ingredients')->whereNotNull('deleted_at')->delete();
    echo "• Deleted {$count} trashed ingredients.\n";
}

// 2. Force delete trashed products
if (Schema::hasTable('products')) {
    $count = DB::table('products')->whereNotNull('deleted_at')->count();
    DB::table('products')->whereNotNull('deleted_at')->delete();
    echo "• Deleted {$count} trashed products.\n";
}

// 3. Force delete trashed users
if (Schema::hasTable('users')) {
    $count = DB::table('users')->whereNotNull('deleted_at')->count();
    DB::table('users')->whereNotNull('deleted_at')->delete();
    echo "• Deleted {$count} trashed users.\n";
}

// 4. Force delete trashed suppliers
if (Schema::hasTable('suppliers')) {
    $count = DB::table('suppliers')->whereNotNull('deleted_at')->count();
    DB::table('suppliers')->whereNotNull('deleted_at')->delete();
    echo "• Deleted {$count} trashed suppliers.\n";
}

// 5. Force delete trashed recipes
if (Schema::hasTable('recipes')) {
    $count = DB::table('recipes')->whereNotNull('deleted_at')->count();
    DB::table('recipes')->whereNotNull('deleted_at')->delete();
    echo "• Deleted {$count} trashed recipes.\n";
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "=== CLEANUP COMPLETED SUCCESSFULLY ===\n";
