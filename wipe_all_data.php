<?php
require 'c:/xampp/htdocs/ratio_coffee/vendor/autoload.php';
$app = require_once 'c:/xampp/htdocs/ratio_coffee/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== WIPING ALL TRANSACTIONS & INVENTORY DATA ===\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

// 1. Wipe POS Transactions
DB::table('pos_order_lines')->truncate();
DB::table('pos_payments')->truncate();
DB::table('pos_refunds')->truncate();
DB::table('pos_orders')->truncate();
echo "✔ All POS Orders & Transactions Wiped.\n";

// 2. Wipe Stock Movements & Stocks
DB::table('stock_movements')->truncate();
DB::table('stock_batches')->truncate();
DB::table('ingredient_stocks')->truncate();
echo "✔ All Stock Movements & Stocks Wiped.\n";

// 3. Wipe Purchase Orders & Expenses
if (\Illuminate\Support\Facades\Schema::hasTable('purchase_order_lines')) DB::table('purchase_order_lines')->truncate();
if (\Illuminate\Support\Facades\Schema::hasTable('purchase_invoices')) DB::table('purchase_invoices')->truncate();
if (\Illuminate\Support\Facades\Schema::hasTable('purchase_orders')) DB::table('purchase_orders')->truncate();
if (\Illuminate\Support\Facades\Schema::hasTable('expenses')) DB::table('expenses')->truncate();
echo "✔ All Purchase Orders & Expenses Wiped.\n";

// 4. Wipe Ingredients (WITHOUT deleting categories or units)
DB::table('recipe_ingredients')->truncate();
DB::table('recipes')->truncate();
DB::table('ingredients')->truncate();
echo "✔ All Ingredients Wiped (Ingredient Categories & Units Preserved!).\n";

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "=== WIPE COMPLETED SUCCESSFULLY ===\n";
