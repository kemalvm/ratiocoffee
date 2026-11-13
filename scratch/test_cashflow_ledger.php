<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PosOrder;
use App\Models\Expense;
use Carbon\Carbon;

$startDate = '2026-08-01';
$endDate   = '2026-08-31';

// 1. Fetch POS Orders (Inflows)
$ordersQuery = PosOrder::with(['cashier'])
    ->whereIn('status', ['completed', 'confirmed', 'paid'])
    ->whereBetween('created_at', [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()]);

$orders = $ordersQuery->get()->map(function($ord) {
    return [
        'id'               => 'inflow_' . $ord->id,
        'raw_id'           => $ord->id,
        'type'             => 'inflow', // Pemasukan
        'reference_number' => $ord->order_number,
        'date'             => $ord->created_at->format('Y-m-d H:i:s'),
        'description'      => 'Penjualan POS Kasir (' . ($ord->lines ? count($ord->lines) : 0) . ' Item)',
        'category_name'    => 'Penjualan POS & Revenue',
        'payment_method'   => $ord->payment_method ?? 'cash',
        'amount'           => floatval($ord->total_amount),
        'status'           => $ord->status === 'completed' ? 'paid' : $ord->status,
        'created_by_name'  => $ord->cashier ? $ord->cashier->name : 'Kasir',
        'can_edit'         => false,
        'can_delete'       => false,
    ];
});

// 2. Fetch Expenses (Outflows)
$expensesQuery = Expense::with(['category', 'creator'])
    ->whereBetween('expense_date', [$startDate, $endDate]);

$expenses = $expensesQuery->get()->map(function($exp) {
    return [
        'id'               => 'outflow_' . $exp->id,
        'raw_id'           => $exp->id,
        'type'             => 'outflow', // Pengeluaran
        'reference_number' => $exp->reference_number,
        'date'             => Carbon::parse($exp->expense_date)->format('Y-m-d H:i:s'),
        'description'      => $exp->description,
        'notes'            => $exp->notes,
        'category_name'    => $exp->category ? $exp->category->name : 'Operasional',
        'category_id'      => $exp->category_id,
        'payment_method'   => $exp->payment_method ?? 'cash',
        'amount'           => floatval($exp->amount),
        'status'           => $exp->status,
        'created_by_name'  => $exp->creator ? $exp->creator->name : 'System',
        'can_edit'         => true,
        'can_delete'       => true,
        'original_expense' => $exp,
    ];
});

// Combine & Sort by Date Descending
$combined = $orders->concat($expenses)->sortByDesc('date')->values();

echo "Total Combined Transactions: " . count($combined) . "\n";
foreach ($combined->take(10) as $tx) {
    echo sprintf("[%s] %s | %s | %s | Rp %s | %s\n", 
        strtoupper($tx['type']), 
        $tx['reference_number'], 
        $tx['date'], 
        $tx['description'], 
        number_format($tx['amount'], 0, ',', '.'),
        $tx['status']
    );
}
