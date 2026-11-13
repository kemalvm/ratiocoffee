<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\PosOrder;
use App\Models\PosOrderLine;
use App\Models\PosPayment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Cash outflow for a period, without double-counting inventory-generated expenses.
     */
    private function expenseBreakdown(\Carbon\Carbon $start, \Carbon\Carbon $end): array
    {
        $inventory = 0.0;
        if (Schema::hasTable('stock_movements')) {
            $inventory = (float) DB::table('stock_movements')
                ->where('type', 'stock_in')
                ->where('qty', '>', 0)
                ->whereBetween('movement_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(CASE WHEN total_cost > 0 THEN total_cost ELSE ABS(qty * unit_cost) END), 0) AS total')
                ->value('total');
        }

        $recurring = 0.0;
        if (Schema::hasTable('expense_payments')) {
            $recurringQuery = DB::table('expense_payments as ep')
                ->join('expenses as e', 'e.id', '=', 'ep.expense_id')
                ->where('ep.status', 'paid')
                ->whereNull('e.deleted_at')
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('ep.paid_at', [$start, $end])
                        ->orWhere(function ($fallback) use ($start, $end) {
                            $fallback->whereNull('ep.paid_at')
                                ->whereBetween('ep.due_date', [$start->toDateString(), $end->toDateString()]);
                        });
                });
            $recurring = (float) $recurringQuery->sum('ep.amount');
        }

        $direct = 0.0;
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'expense_date')) {
            $directQuery = DB::table('expenses as e')
                ->whereNull('e.deleted_at')
                ->whereBetween('e.expense_date', [$start->toDateString(), $end->toDateString()])
                // Inventory purchases are sourced from stock_movements above.
                ->where(function ($query) {
                    $query->whereNull('e.notes')
                        ->orWhere('e.notes', 'not like', '%Auto-generated%');
                })
                ->where('e.description', 'not like', '%Inventory%')
                ->where('e.description', 'not like', '%Restock%')
                ->where('e.description', 'not like', '%Kulakan%');

            if (Schema::hasColumn('expenses', 'status')) {
                $directQuery->whereIn('e.status', ['paid', 'approved', 'confirmed']);
            }

            if (Schema::hasTable('expense_payments')) {
                $directQuery->whereNotExists(function ($payment) {
                    $payment->selectRaw('1')
                        ->from('expense_payments as ep')
                        ->whereColumn('ep.expense_id', 'e.id')
                        ->where('ep.status', 'paid');
                });
            }
            $direct = (float) $directQuery->sum('e.amount');
        }

        return [
            'inventory' => $inventory,
            'recurring' => $recurring,
            'direct' => $direct,
            'total' => $inventory + $recurring + $direct,
        ];
    }

    /**
     * Get Comprehensive CEO Dashboard Analytics
     */
  /**
     * Get Comprehensive CEO Dashboard Analytics
     */
    public function ceo(Request $request): JsonResponse
    {
        $todayStr      = now()->toDateString();
        $startMonth    = now()->startOfMonth()->toDateTimeString();
        $endMonth      = now()->endOfMonth()->toDateTimeString();
        $prevMonthDate = now()->subMonth()->startOfMonth()->toDateTimeString();
        $prevMonthEnd  = now()->subMonth()->endOfMonth()->toDateTimeString();

        // Financial KPIs recognize only orders with a completed payment.
        // Operational order status (preparing/ready/completed) is independent from payment status.
        $validStatuses = ['completed', 'confirmed', 'paid', 'preparing', 'processing', 'ready', 'settled'];
        $paidOrder = fn($query) => $query->whereHas('payments', fn($payment) => $payment->where('status', 'completed'));

        // Net revenue excludes tax. Gross profit is net revenue minus COGS and may be negative.
        $netRevSql = 'COALESCE(SUM(COALESCE(subtotal, 0) - COALESCE(discount_amount, 0)), 0)';
        $grossProfitSql = 'COALESCE(SUM((COALESCE(subtotal, 0) - COALESCE(discount_amount, 0)) - COALESCE(total_cost, 0)), 0)';

        // ── 1. KPI Today ──────────────────────────────────────────────────
        $todayData = PosOrder::whereDate('created_at', $todayStr)
            ->where($paidOrder)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total_orders, 
                {$netRevSql} as total_revenue, 
                {$grossProfitSql} as total_profit
            ")
            ->first();

        $todayRevenue     = (float)($todayData->total_revenue ?? 0);
        $todayOrdersCount = (int)($todayData->total_orders ?? 0);
        $todayProfit      = (float)($todayData->total_profit ?? 0);
        $todayMargin      = $todayRevenue > 0 ? ($todayProfit / $todayRevenue) * 100 : 0;
        $todayCash        = (float)PosPayment::whereHas('order', fn($q) =>
                                $q->whereDate('created_at', $todayStr)->whereNull('deleted_at')
                            )->where('method', 'cash')->where('status', 'completed')->sum('amount');
        $todayCustomers   = (int)PosOrder::whereDate('created_at', $todayStr)
            ->where($paidOrder)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');
        $todayAvgOrder    = $todayOrdersCount > 0 ? $todayRevenue / $todayOrdersCount : 0;
        $todayExpenseBreakdown = $this->expenseBreakdown(now()->startOfDay(), now()->endOfDay());
        $todayExpense = $todayExpenseBreakdown['total'];

        // ── 2. KPI Month ──────────────────────────────────────────────────
        $monthData = PosOrder::whereBetween('created_at', [$startMonth, $endMonth])
            ->where($paidOrder)
            ->whereNull('deleted_at')
            ->selectRaw("
                {$netRevSql} as total_revenue, 
                {$grossProfitSql} as total_profit
            ")
            ->first();

        $monthRevenue     = (float)($monthData->total_revenue ?? 0);
        $monthProfit      = (float)($monthData->total_profit ?? 0);
        $monthExpenseBreakdown = $this->expenseBreakdown(now()->startOfMonth(), now()->endOfMonth());
        $monthExpense = $monthExpenseBreakdown['total'];
        
        $prevMonthRevenue = (float)PosOrder::whereBetween('created_at', [$prevMonthDate, $prevMonthEnd])
            ->where($paidOrder)
            ->whereNull('deleted_at')
            ->selectRaw($netRevSql)
            ->value('total_revenue') ?? 0;

        $growth = $prevMonthRevenue > 0 ? (($monthRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100 : 0;
        $margin = $monthRevenue > 0 ? ($monthProfit / $monthRevenue) * 100 : 0;

        // Inventory valuation — use $ing->stock_value accessor which correctly applies
        // the net_content divisor: ($stock / $net) * $pkgPrice, avoiding the "gram × pack-price" inflation.
        $inventoryValue = (float) Ingredient::whereNull('deleted_at')
            ->where('status', 'active')
            ->get()
            ->sum(fn($ing) => max(0, (float) $ing->stock_value));

        // ── 3. Revenue & Profit Trend ─────────────────────────────────────
        $trendRange = $request->query('trend_range', '1_month');
        $days = [];
        $revenueTrend = [];
        $profitTrend  = [];

        if ($trendRange === '1_day') {
            for ($h = 0; $h < 24; $h++) {
                $hourStr = str_pad($h, 2, '0', STR_PAD_LEFT);
                $days[]  = "Jam {$hourStr}:00";

                $row = PosOrder::whereDate('created_at', $todayStr)
                    ->whereRaw('HOUR(created_at) = ?', [$h])
                    ->where($paidOrder)
                    ->whereNull('deleted_at')
                    ->selectRaw("{$netRevSql} as rev, {$grossProfitSql} as prof")
                    ->first();

                $revenueTrend[] = (float)($row->rev ?? 0);
                $profitTrend[]  = (float)($row->prof ?? 0);
            }
        } elseif ($trendRange === '7_days') {
            for ($i = 6; $i >= 0; $i--) {
                $date   = now()->subDays($i)->toDateString();
                $days[] = now()->subDays($i)->format('d M');

                $row = PosOrder::whereDate('created_at', $date)
                    ->where($paidOrder)
                    ->whereNull('deleted_at')
                    ->selectRaw("{$netRevSql} as rev, {$grossProfitSql} as prof")
                    ->first();

                $revenueTrend[] = (float)($row->rev ?? 0);
                $profitTrend[]  = (float)($row->prof ?? 0);
            }
        } elseif ($trendRange === '1_year') {
            for ($i = 11; $i >= 0; $i--) {
                $monthDate = now()->subMonths($i);
                $days[]    = $monthDate->format('M Y');

                $row = PosOrder::whereYear('created_at', $monthDate->year)
                    ->whereMonth('created_at', $monthDate->month)
                    ->where($paidOrder)
                    ->whereNull('deleted_at')
                    ->selectRaw("{$netRevSql} as rev, {$grossProfitSql} as prof")
                    ->first();

                $revenueTrend[] = (float)($row->rev ?? 0);
                $profitTrend[]  = (float)($row->prof ?? 0);
            }
        } elseif ($trendRange === 'all') {
            $firstOrder = PosOrder::where($paidOrder)->orderBy('created_at')->first();
            $curr = ($firstOrder?->created_at ?? now())->copy()->startOfMonth();
            $endDate = now()->endOfMonth();

            while ($curr <= $endDate) {
                $days[] = $curr->format('M Y');
                $row = PosOrder::whereYear('created_at', $curr->year)
                    ->whereMonth('created_at', $curr->month)
                    ->where($paidOrder)
                    ->whereNull('deleted_at')
                    ->selectRaw("{$netRevSql} as rev, {$grossProfitSql} as prof")
                    ->first();
                $revenueTrend[] = (float)($row->rev ?? 0);
                $profitTrend[] = (float)($row->prof ?? 0);
                $curr->addMonth();
            }
        } else { // '1_month' (30 Hari)
            for ($i = 29; $i >= 0; $i--) {
                $date   = now()->subDays($i)->toDateString();
                $days[] = now()->subDays($i)->format('d M');

                $row = PosOrder::whereDate('created_at', $date)
                    ->where($paidOrder)
                    ->whereNull('deleted_at')
                    ->selectRaw("{$netRevSql} as rev, {$grossProfitSql} as prof")
                    ->first();

                $revenueTrend[] = (float)($row->rev ?? 0);
                $profitTrend[]  = (float)($row->prof ?? 0);
            }
        }

        // ── 4. Sales by Category (Overall) ──────────────────────────────────
        $salesByCategory = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
            ->join('products', 'pos_order_lines.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->whereIn('pos_orders.status', $validStatuses)
            ->select('product_categories.name as category', DB::raw('SUM(pos_order_lines.subtotal) as total'))
            ->groupBy('product_categories.name')
            ->get()
            ->map(fn($item) => [
                'category' => $item->category ?? 'Lain-Lain',
                'total'    => (float)$item->total,
            ]);

        // ── 5. Payment Methods Breakdown ──────────────────────────────────
        $paymentMethods = PosPayment::select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->get()
            ->map(fn($i) => [
                'method' => strtoupper($i->method),
                'total'  => (float)$i->total,
                'count'  => (int)$i->count,
            ]);

        // ── 6. Live Queue Status (Diproses vs Selesai) ───────────────────
        $preparingCount = PosOrder::whereDate('created_at', $todayStr)->whereIn('status', ['preparing', 'pending', 'processing', 'ready', 'confirmed'])->count();
        $completedCount = PosOrder::whereDate('created_at', $todayStr)->where('status', 'completed')->count();
        $totalQueueCount = $preparingCount + $completedCount;

        $queueStatus = [
            'preparing'       => $preparingCount,
            'completed'       => $completedCount,
            'total'           => $totalQueueCount,
            'completion_rate' => $totalQueueCount > 0 ? round(($completedCount / $totalQueueCount) * 100, 1) : 0,
        ];

        // ── 7. Stock Alerts ───────────────────────────────────────────────
        $stockAlerts = Ingredient::with(['stocks', 'unit'])
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get()
            ->map(function ($ing) {
                $stockFromWarehouse = (float)$ing->stocks->sum('qty');
                $currentStock = $ing->stocks->isNotEmpty() ? $stockFromWarehouse : (float)$ing->current_stock;
                
                $status = 'normal';
                if ($currentStock <= 0) {
                    $status = 'out_of_stock';
                } elseif ($currentStock <= (float)$ing->min_stock) {
                    $status = 'low_stock';
                }

                $unitStr = is_object($ing->unit) ? ($ing->unit->symbol ?? $ing->unit->name ?? 'unit') : ($ing->unit ?? 'unit');

                return [
                    'id'          => $ing->id,
                    'name'        => $ing->name,
                    'code'        => $ing->code,
                    'stock'       => $currentStock,
                    'min_stock'   => (float)$ing->min_stock,
                    'unit'        => $unitStr,
                    'status'      => $status,
                ];
            })
            ->filter(fn($item) => $item['status'] !== 'normal')
            ->values();

        // ── 8. Recent Transactions (10 latest) ─────────────────────────────
        $recentTransactions = PosOrder::with(['lines.product', 'cashier'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // ── 9. Recent Activity Logs ───────────────────────────────────────
        $recentActivities = ActivityLog::with('user')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // ── 10. Top Products (Overall Top 5) ─────────────────────────────
        $topProducts = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
            ->whereIn('pos_orders.status', $validStatuses)
            ->whereNull('pos_orders.deleted_at')
            ->select('pos_order_lines.product_name', DB::raw('SUM(pos_order_lines.qty) as total_qty'), DB::raw('SUM(pos_order_lines.subtotal) as total_sales'))
            ->groupBy('pos_order_lines.product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'product_name' => $item->product_name,
                'total_qty'    => (int)$item->total_qty,
                'total_sales'  => (float)$item->total_sales,
            ]);

        $allProductsSold = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
            ->whereIn('pos_orders.status', $validStatuses)
            ->whereNull('pos_orders.deleted_at')
            ->select('pos_order_lines.product_name', DB::raw('SUM(pos_order_lines.qty) as total_qty'), DB::raw('SUM(pos_order_lines.subtotal) as total_sales'))
            ->groupBy('pos_order_lines.product_name')
            ->orderByDesc('total_qty')
            ->get()
            ->map(fn($item) => [
                'product_name' => $item->product_name,
                'total_qty'    => (int)$item->total_qty,
                'total_sales'  => (float)$item->total_sales,
            ]);

        $worstProducts = Product::whereNotIn('name', $topProducts->pluck('product_name'))
            ->limit(5)
            ->get()
            ->map(fn($p) => ['product_name' => $p->name, 'total_qty' => 0, 'total_sales' => 0]);

        // ── 11. Supplier Payment Due Reminders (Countdown Pembayaran Terdekat) ─────
        $supplierDueReminders = [];
        try {
            // A. Purchase Invoices (Unpaid - if model exists)
            if (class_exists(\App\Models\PurchaseInvoice::class)) {
                $invoices = \App\Models\PurchaseInvoice::with('supplier')
                    ->whereIn('status', ['issued', 'partial', 'overdue', 'draft'])
                    ->orderBy('due_date', 'asc')
                    ->get();

                foreach ($invoices as $inv) {
                    if (!$inv->due_date) continue;
                    $target = \Carbon\Carbon::parse($inv->due_date)->startOfDay();
                    $diffDays = (int)now()->startOfDay()->diffInDays($target, false);

                    $statusText = $diffDays < 0
                        ? "🔴 Terlewat " . abs($diffDays) . " hari"
                        : ($diffDays === 0 ? "⚠️ HARI INI JATUH TEMPO!" : "⏰ {$diffDays} Hari Lagi");

                    $supplierDueReminders[] = [
                        'id'               => 'inv-' . $inv->id,
                        'supplier_name'    => $inv->supplier->name ?? 'Supplier',
                        'contact_person'   => $inv->supplier->contact_person ?? '-',
                        'phone'            => $inv->supplier->phone ?? '',
                        'invoice_number'   => $inv->invoice_number,
                        'due_date'         => $inv->due_date->format('Y-m-d'),
                        'days_left'        => $diffDays,
                        'amount_due'       => (float)($inv->remaining_amount ?? $inv->total_amount),
                        'payment_method'   => $inv->supplier->payment_method ?? 'credit',
                        'payment_term_days'=> $inv->supplier->payment_term_days ?? 30,
                        'status'           => $diffDays < 0 ? 'overdue' : ($diffDays === 0 ? 'today' : 'upcoming'),
                        'status_text'      => $statusText,
                    ];
                }
            }

            // B. Active Suppliers with explicit next_payment_due_date or payment_due_dates
            $activeSuppliers = \App\Models\Supplier::where('status', 'active')->get();
            foreach ($activeSuppliers as $sup) {
                $dueDate = null;

                // 1. Check array payment_due_dates
                if (is_array($sup->payment_due_dates) && count($sup->payment_due_dates)) {
                    foreach ($sup->payment_due_dates as $dStr) {
                        if ($dStr) {
                            $dueDate = \Carbon\Carbon::parse($dStr)->startOfDay();
                            break;
                        }
                    }
                }

                // 2. Check next_payment_due_date
                if (!$dueDate && $sup->next_payment_due_date) {
                    $dueDate = \Carbon\Carbon::parse($sup->next_payment_due_date)->startOfDay();
                }

                // Note: Only add if an explicit due date is configured
                if ($dueDate) {
                    $diffDays = (int)now()->startOfDay()->diffInDays($dueDate, false);
                    $statusText = $diffDays < 0
                        ? "🔴 Terlewat " . abs($diffDays) . " hari"
                        : ($diffDays === 0 ? "⚠️ HARI INI JATUH TEMPO!" : "⏰ {$diffDays} Hari Lagi");

                    // Avoid duplicate if already covered by invoice
                    $alreadyAdded = collect($supplierDueReminders)->contains('supplier_name', $sup->name);
                    if (!$alreadyAdded) {
                        $supplierDueReminders[] = [
                            'id'               => 'sup-' . $sup->id,
                            'supplier_name'    => $sup->name,
                            'contact_person'   => $sup->contact_person ?? 'PIC Vendor',
                            'phone'            => $sup->phone ?? '',
                            'invoice_number'   => 'TEMPO-' . $sup->code,
                            'due_date'         => $dueDate->format('Y-m-d'),
                            'days_left'        => $diffDays,
                            'amount_due'       => (float)($sup->credit_limit ?: 0),
                            'payment_method'   => $sup->payment_method ?: 'credit',
                            'payment_term_days'=> $sup->payment_term_days ?: 14,
                            'status'           => $diffDays < 0 ? 'overdue' : ($diffDays === 0 ? 'today' : 'upcoming'),
                            'status_text'      => $statusText,
                        ];
                    }
                }
            }

            // Sort by due date ASC (nearest payment first)
            usort($supplierDueReminders, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));
        } catch (\Throwable $e) {
            \Log::error('Error building supplier due reminders: ' . $e->getMessage());
        }

        // ── 12. Advanced AI Business Intelligence Insights Feed ─────────────
        $insights = [];

        // Insight A: Supplier Due Reminders Analysis
        $urgentSuppliers = collect($supplierDueReminders)->filter(fn($s) => $s['days_left'] <= 7);
        if ($urgentSuppliers->count() > 0) {
            $first = $urgentSuppliers->first();
            $insights[] = [
                'type' => 'warning',
                'icon' => '⏰',
                'text' => "Terdapat {$urgentSuppliers->count()} tagihan supplier jatuh tempo dalam 7 hari ke depan ({$first['supplier_name']} - {$first['status_text']}). Segera siapkan alokasi kas."
            ];
        } elseif (count($supplierDueReminders) > 0) {
            $first = $supplierDueReminders[0];
            $insights[] = [
                'type' => 'info',
                'icon' => '📅',
                'text' => "Jatuh tempo pembayaran supplier terdekat: {$first['supplier_name']} pada " . date('d M Y', strtotime($first['due_date'])) . " ({$first['days_left']} hari lagi)."
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'icon' => '✅',
                'text' => "Seluruh pembayaran supplier terpantau aman dan tidak ada tagihan jatuh tempo dalam waktu dekat."
            ];
        }

        // Insight B: Real-Time Stock & Inventory Alerts
        if ($stockAlerts->count() > 0) {
            $names = $stockAlerts->take(3)->pluck('name')->implode(', ');
            $insights[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'text' => "Terdapat {$stockAlerts->count()} bahan baku kritis di bawah minimum ({$names}). Segera terbitkan Purchase Order."
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'icon' => '📦',
                'text' => "Persediaan seluruh bahan baku di gudang utama 100% aman (di atas batas minimum stok)."
            ];
        }

        // Insight C: Hero Product Performance
        if ($topProducts->count() > 0) {
            $hero = $topProducts->first();
            $insights[] = [
                'type' => 'success',
                'icon' => '🏆',
                'text' => "Menu terlaris periode ini adalah {$hero['product_name']} dengan total " . number_format($hero['total_qty']) . " porsi terjual (Revenue: Rp " . number_format($hero['total_sales']) . ")."
            ];
        }

        // Insight D: POS Sales Peak Hours
        $peakHourData = PosOrder::whereDate('created_at', $todayStr)
            ->where('status', 'completed')
            ->select(DB::raw('HOUR(created_at) as hr'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('hr')
            ->orderByDesc('cnt')
            ->first();

        if ($peakHourData) {
            $hrStart = str_pad($peakHourData->hr, 2, '0', STR_PAD_LEFT) . ':00';
            $hrEnd   = str_pad($peakHourData->hr + 1, 2, '0', STR_PAD_LEFT) . ':00';
            $insights[] = [
                'type' => 'info',
                'icon' => '🔥',
                'text' => "Peak hours transaksi outlet hari ini berada pada pukul {$hrStart} - {$hrEnd} dengan {$peakHourData->cnt} transaksi kasir."
            ];
        }

        // Insight E: Profit Margin & Revenue Trend
        if ($growth > 0) {
            $insights[] = [
                'type' => 'success',
                'icon' => '📈',
                'text' => "Revenue bulan ini tumbuh +" . number_format($growth, 1) . "% dibanding bulan lalu dengan Laba Kotor Rp " . number_format($monthProfit) . " (Margin " . number_format($margin, 1) . "%)."
            ];
        } elseif ($monthRevenue > 0) {
            $insights[] = [
                'type' => 'success',
                'icon' => '💡',
                'text' => "Margin profit kotor bisnis bulan ini sehat di angka " . number_format($margin, 1) . "% (Total Laba Kotor Rp " . number_format($monthProfit) . ")."
            ];
        }

        return response()->json([
            'brand'                 => \App\Models\BrandSetting::allAsArray(),
            'kpi' => [
                'today' => [
                    'revenue'     => $todayRevenue,
                    'orders'      => $todayOrdersCount,
                    'profit'      => $todayProfit,
                    'margin'      => round($todayMargin, 1),
                    'cash'        => $todayCash,
                    'expense'     => $todayExpense,
                    'expense_breakdown' => $todayExpenseBreakdown,
                    'customers'   => $todayCustomers,
                    'avg_order'   => $todayAvgOrder,
                ],
                'month' => [
                    'revenue'     => $monthRevenue,
                    'profit'      => $monthProfit,
                    'expense'     => $monthExpense,
                    'expense_breakdown' => $monthExpenseBreakdown,
                    'growth'      => round($growth, 1),
                    'margin'      => round($margin, 1),
                    'inventory'   => $inventoryValue,
                ]
            ],
            'charts' => [
                'days'              => $days,
                'revenue_trend'     => $revenueTrend,
                'profit_trend'      => $profitTrend,
                'sales_by_category' => $salesByCategory,
                'payment_methods'   => $paymentMethods,
            ],
            'queue'                 => $queueStatus,
            'stock_alerts'          => $stockAlerts,
            'supplier_due_reminders'=> $supplierDueReminders,
            'recent_transactions'   => $recentTransactions,
            'recent_activities'     => $recentActivities,
            'top_products'          => $topProducts,
            'all_products_sold'     => $allProductsSold,
            'worst_products'        => $worstProducts,
            'insights'              => $insights,
        ]);
    }

    /**
     * Get All Products Sold filtered independently by range (1_day, 7_days, 1_month, etc.)
     */
    public function soldProducts(Request $request): JsonResponse
    {
        $range = $request->input('range', '1_day');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $todayStr = now()->toDateString();

        $validStatuses = ['completed', 'confirmed', 'paid', 'preparing', 'processing', 'ready', 'settled'];

        $productsQuery = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
            ->whereIn('pos_orders.status', $validStatuses)
            ->whereNull('pos_orders.deleted_at');

        if ($startDate && $endDate) {
            $productsQuery->whereBetween('pos_orders.created_at', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay()
            ]);
            $range = 'custom';
        } else {
            if ($range === '1_day') {
                $productsQuery->whereDate('pos_orders.created_at', $todayStr);
            } elseif ($range === '7_days') {
                $productsQuery->where('pos_orders.created_at', '>=', now()->subDays(6)->startOfDay());
            } elseif ($range === '1_month') {
                $productsQuery->where('pos_orders.created_at', '>=', now()->subDays(29)->startOfDay());
            } elseif ($range === '1_year') {
                $productsQuery->where('pos_orders.created_at', '>=', now()->subYear()->startOfDay());
            }
        }

        $allProductsSold = $productsQuery
            ->select('pos_order_lines.product_name', DB::raw('SUM(pos_order_lines.qty) as total_qty'), DB::raw('SUM(pos_order_lines.subtotal) as total_sales'))
            ->groupBy('pos_order_lines.product_name')
            ->orderByDesc('total_qty')
            ->get()
            ->map(fn($item) => [
                'product_name' => $item->product_name,
                'total_qty'    => (int)$item->total_qty,
                'total_sales'  => (float)$item->total_sales,
            ]);

        return response()->json([
            'range'             => $range,
            'all_products_sold' => $allProductsSold,
        ]);
    }

    /**
     * Get Payment Methods breakdown filtered by range (1_day, 7_days, 1_month, all)
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $range    = $request->input('range', '1_day');
        $todayStr = now()->toDateString();
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = PosPayment::select(
            'method',
            DB::raw('SUM(amount) as total'),
            DB::raw('COUNT(*) as count')
        );

        if ($startDate && $endDate) {
            $query->whereHas('order', fn($q) => $q->whereBetween('created_at', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay()
            ]));
            $range = 'custom';
        } else {
            if ($range === '1_day') {
                $query->whereHas('order', fn($q) => $q->whereDate('created_at', $todayStr));
            } elseif ($range === '7_days') {
                $query->whereHas('order', fn($q) => $q->where('created_at', '>=', now()->subDays(6)->startOfDay()));
            } elseif ($range === '1_month') {
                $query->whereHas('order', fn($q) => $q->where('created_at', '>=', now()->subDays(29)->startOfDay()));
            }
            // 'all' = no date filter
        }

        $paymentMethods = $query->groupBy('method')->get()->map(fn($i) => [
            'method' => strtoupper($i->method),
            'total'  => (float) $i->total,
            'count'  => (int) $i->count,
        ]);

        $totalTransactions = $paymentMethods->sum('count');

        return response()->json([
            'range'             => $range,
            'payment_methods'   => $paymentMethods,
            'total_transactions' => $totalTransactions,
        ]);
    }

    /**
     * AI Business Intelligence Interactive Chat Endpoint
     * Comprehensive data-querying engine for all ERP modules.
     */
    public function biChat(Request $request): JsonResponse
    {
        try {
            $msg = strtolower(trim($request->input('message', '')));
            $todayStr = now()->toDateString();
            $startMonth = now()->startOfMonth()->toDateString();
            $endMonth = now()->endOfMonth()->toDateString();

            $reply = $this->matchBiQuery($msg, $todayStr, $startMonth, $endMonth);

            return response()->json([
                'reply'     => $reply,
                'timestamp' => now()->format('H:i'),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('BI Chat Error: ' . $e->getMessage());
            return response()->json([
                'reply'     => "🤖 **Ratio AI Intelligence**: Terjadi kendala saat membaca data database. Error: " . $e->getMessage(),
                'timestamp' => now()->format('H:i'),
            ]);
        }
    }

    /**
     * Get Average Stats (Transactions & Revenue) based on weekly or monthly filter.
     * Calculation: group all valid orders by their actual period (ISO week / year-month),
     * then compute the mathematical mean across those real periods.
     */
    public function averageStats(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'week'); // 'week' or 'month'
        $validStatuses = ['completed', 'confirmed', 'paid', 'preparing', 'processing', 'ready', 'settled'];

        $averageTransactionCount = 0;
        $averageRevenue = 0;

        if ($filter === 'week') {
            // Group by ISO year-week (mode 1 = Monday start) → mean per week
            $weeklyData = PosOrder::whereIn('status', $validStatuses)
                ->selectRaw('YEARWEEK(created_at, 1) as period, COUNT(*) as orders, SUM(total_amount) as revenue')
                ->groupBy('period')
                ->get();

            if ($weeklyData->count() > 0) {
                $averageTransactionCount = (int) round($weeklyData->avg('orders'));
                $averageRevenue = (int) round($weeklyData->avg('revenue'));
            }
        } elseif ($filter === 'month') {
            // Group by calendar year-month → mean per month
            $monthlyData = PosOrder::whereIn('status', $validStatuses)
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, COUNT(*) as orders, SUM(total_amount) as revenue')
                ->groupBy('period')
                ->get();

            if ($monthlyData->count() > 0) {
                $averageTransactionCount = (int) round($monthlyData->avg('orders'));
                $averageRevenue = (int) round($monthlyData->avg('revenue'));
            }
        }

        return response()->json([
            'filter' => $filter,
            'average_transactions' => $averageTransactionCount,
            'average_revenue' => $averageRevenue,
        ]);
    }

    private function matchBiQuery(string $msg, string $todayStr, string $startMonth, string $endMonth): string
    {
        $fmt = fn($v) => 'Rp ' . number_format((float)$v, 0, ',', '.');
        $validStatuses = ['completed', 'confirmed', 'preparing', 'ready'];

        // ── 1. Comprehensive Performance Analysis ──────────────────────────
        if ($this->msgHas($msg, ['analisis', 'performa', 'evaluasi', 'kinerja', 'kesehatan', 'insight', 'laporan bisnis'])) {
            try {
                $todayRev   = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_amount');
                $todayCost  = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_cost');
                $todayOrd   = PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->count();
                $todayExp   = (float) \App\Models\Expense::whereDate('expense_date', $todayStr)->sum('amount');
                $todayGross = $todayRev - $todayCost;
                $todayNet   = $todayGross - $todayExp;

                $monthRev   = (float) PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->sum('total_amount');
                $monthCost  = (float) PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->sum('total_cost');
                $monthOrd   = PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->count();
                $monthExp   = (float) \App\Models\Expense::whereBetween('expense_date', [$startMonth, $endMonth])->sum('amount');
                $monthGross = $monthRev - $monthCost;
                $monthNet   = $monthGross - $monthExp;

                $marginPct  = $monthRev > 0 ? round(($monthGross / $monthRev) * 100, 1) : 0;
                $netMargin  = $monthRev > 0 ? round(($monthNet / $monthRev) * 100, 1) : 0;
                $aov        = $monthOrd > 0 ? round($monthRev / $monthOrd, 0) : 0;

                $lowStockCount = Ingredient::whereNull('deleted_at')->where('status', 'active')
                    ->whereColumn('current_stock', '<=', 'min_stock')->count();

                $health = "🟢 **Sehat & Aktif**";
                if ($monthRev > 0 && $netMargin < 15) $health = "🟡 **Waspada (Margin Net Low)**";
                if ($monthRev == 0) $health = "ℹ️ **Standby (0 Transaksi)**";

                return "📊 **Ratio BI — Analisis Kinerja Bisnis**\n\n"
                    . "Status Kinerja: {$health}\n\n"
                    . "🗓️ **Hari Ini (" . now()->format('d M Y') . "):**\n"
                    . "• Omzet: **{$fmt($todayRev)}** ({$todayOrd} order)\n"
                    . "• Laba Kotor: **{$fmt($todayGross)}**\n"
                    . "• Pengeluaran: **{$fmt($todayExp)}**\n"
                    . "• Laba Bersih: **{$fmt($todayNet)}**\n\n"
                    . "📅 **Performansi Bulan Ini (" . now()->format('F Y') . "):**\n"
                    . "• Total Omzet: **{$fmt($monthRev)}** ({$monthOrd} order)\n"
                    . "• Laba Kotor: **{$fmt($monthGross)}** (Margin: **{$marginPct}%**)\n"
                    . "• Total Pengeluaran: **{$fmt($monthExp)}**\n"
                    . "• Laba Bersih: **{$fmt($monthNet)}** (Net Margin: **{$netMargin}%**)\n"
                    . "• Rata-rata Order (AOV): **{$fmt($aov)}**\n"
                    . "• Stok Kritis: **{$lowStockCount} bahan baku** perlu restock.";
            } catch (\Throwable $e) {
                return "⚠️ Gagal menganalisis performa bisnis: " . $e->getMessage();
            }
        }

        // ── 2. Revenue / Omzet ────────────────────────────────────────────
        if ($this->msgHas($msg, ['omzet', 'revenue', 'penjualan', 'pendapatan', 'pemasukan', 'income', 'sales'])) {
            try {
                $todayRev = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_amount');
                $todayOrd = PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->count();
                $monthRev = (float) PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->sum('total_amount');
                $monthOrd = PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->count();
                $aovMonth = $monthOrd > 0 ? round($monthRev / $monthOrd, 0) : 0;

                return "📊 **Laporan Real-Time Omzet & Penjualan**\n\n"
                    . "🗓️ **Hari Ini** (" . now()->format('d M Y') . "):\n"
                    . "• Total Omzet: **{$fmt($todayRev)}**\n"
                    . "• Transaksi: **{$todayOrd} order**\n\n"
                    . "📅 **Bulan Ini** (" . now()->format('F Y') . "):\n"
                    . "• Total Omzet: **{$fmt($monthRev)}**\n"
                    . "• Transaksi: **{$monthOrd} order**\n"
                    . "• Average Order Value (AOV): **{$fmt($aovMonth)}**";
            } catch (\Throwable $e) {
                return "⚠️ Gagal mengambil data omzet: " . $e->getMessage();
            }
        }

        // ── 3. Profit / Laba ──────────────────────────────────────────────
        if ($this->msgHas($msg, ['profit', 'laba', 'untung', 'margin', 'keuntungan'])) {
            try {
                $todayRev   = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_amount');
                $todayCost  = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_cost');
                $todayGross = $todayRev - $todayCost;

                $monthRev   = (float) PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->sum('total_amount');
                $monthCost  = (float) PosOrder::whereBetween('created_at', ["$startMonth 00:00:00", "$endMonth 23:59:59"])->whereIn('status', $validStatuses)->sum('total_cost');
                $monthGross = $monthRev - $monthCost;
                $monthExp   = (float) \App\Models\Expense::whereBetween('expense_date', [$startMonth, $endMonth])->sum('amount');
                $monthNet   = $monthGross - $monthExp;
                $margin     = $monthRev > 0 ? round(($monthGross / $monthRev) * 100, 1) : 0;

                return "💰 **Laporan Profitabilitas & Analisis Margin**\n\n"
                    . "• Laba Kotor Hari Ini: **{$fmt($todayGross)}**\n"
                    . "• Laba Kotor Bulan Ini: **{$fmt($monthGross)}** (Margin: **{$margin}%**)\n"
                    . "• Total Operational Expense: **{$fmt($monthExp)}**\n"
                    . "• Laba Bersih (Net Profit): **{$fmt($monthNet)}**\n\n"
                    . ($margin >= 40 ? "✅ Margin kotor toko sehat (>40%)." : ($monthRev > 0 ? "⚠️ Margin kotor di bawah 40%, pertimbangkan penyesuaian harga atau HPP." : "ℹ️ Belum ada transaksi penjualan bulan ini."));
            } catch (\Throwable $e) {
                return "⚠️ Gagal menghitung profitabilitas: " . $e->getMessage();
            }
        }

        // ── 4. Expenses / Pengeluaran ─────────────────────────────────────
        if ($this->msgHas($msg, ['pengeluaran', 'expense', 'biaya', 'beban', 'cost', 'operasional', 'keluar'])) {
            try {
                $todayExp = (float) \App\Models\Expense::whereDate('expense_date', $todayStr)->sum('amount');
                $monthExp = (float) \App\Models\Expense::whereBetween('expense_date', [$startMonth, $endMonth])->sum('amount');
                $topCats  = \App\Models\Expense::join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
                    ->whereBetween('expense_date', [$startMonth, $endMonth])
                    ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
                    ->groupBy('expense_categories.name')
                    ->orderByDesc('total')->limit(5)->get();

                $catList = $topCats->map(fn($c) => "• {$c->name}: **{$fmt($c->total)}**")->join("\n");
                return "💸 **Laporan Pengeluaran Operasional (Expense)**\n\n"
                    . "• Hari Ini: **{$fmt($todayExp)}**\n"
                    . "• Bulan Ini: **{$fmt($monthExp)}**\n\n"
                    . "**Kategori Pengeluaran Terbesar:**\n" . ($catList ?: '_(Belum ada pengeluaran tercatat)_');
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca data pengeluaran: " . $e->getMessage();
            }
        }

        // ── 5. Produk Terlaris / Menu ─────────────────────────────────────
        if ($this->msgHas($msg, ['laku', 'terlaris', 'favorit', 'populer', 'best seller', 'top produk', 'top menu'])) {
            try {
                $tops = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
                    ->whereIn('pos_orders.status', $validStatuses)
                    ->select('pos_order_lines.product_name', DB::raw('SUM(pos_order_lines.qty) as total_qty'), DB::raw('SUM(pos_order_lines.subtotal) as total_sales'))
                    ->groupBy('pos_order_lines.product_name')
                    ->orderByDesc('total_sales')
                    ->limit(5)->get();

                if ($tops->isEmpty()) return "☕ Belum ada data transaksi penjualan produk di database.";
                $list = $tops->map(fn($t, $i) => ($i + 1) . ". **{$t->product_name}** — **{$t->total_qty} porsi** ({$fmt($t->total_sales)})")->join("\n");
                return "☕ **5 Menu Terlaris (All Time)**\n\n{$list}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal menganalisis produk terlaris: " . $e->getMessage();
            }
        }

        // ── 6. Produk by name ─────────────────────────────────────────────
        if ($this->msgHas($msg, ['produk', 'menu', 'katalog', 'harga jual', 'selling'])) {
            try {
                $products = Product::with('category')->where('available_for_pos', true)->get();
                if ($products->isEmpty()) return "🍵 Belum ada produk aktif terdaftar.";
                $list = $products->map(fn($p) => "• **{$p->name}** (" . ($p->category?->name ?? 'Kategori') . ") — Jual: **{$fmt($p->selling_price)}** | HPP: {$fmt($p->cost_price)}")->join("\n");
                return "🍵 **Daftar Produk Aktif POS** ({$products->count()} menu)\n\n{$list}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal memuat produk: " . $e->getMessage();
            }
        }

        // ── 7. Stok / Inventory ───────────────────────────────────────────
        if ($this->msgHas($msg, ['stok', 'stock', 'inventory', 'bahan baku', 'bahan', 'persediaan', 'gudang', 'habis'])) {
            try {
                $ingredients = Ingredient::whereNull('deleted_at')->with(['unit', 'stocks', 'category'])->get();
                if ($ingredients->isEmpty()) return "📦 Belum ada item bahan baku yang terdaftar di Inventory.";

                $lowCount = 0;
                $details = $ingredients->map(function ($i) use ($fmt, &$lowCount) {
                    $stock = $i->stocks->isNotEmpty() ? (float)$i->stocks->sum('qty') : (float)$i->current_stock;
                    $sym   = $i->unit->symbol ?? 'unit';
                    $isLow = $stock <= (float)$i->min_stock;
                    if ($isLow) $lowCount++;
                    $status = $stock <= 0 ? '🔴 HABIS' : ($isLow ? '🟡 CRITICAL' : '🟢 SAFE');
                    $cost   = (float)($i->average_cost > 0 ? $i->average_cost : ($i->last_purchase_price > 0 ? $i->last_purchase_price : $i->standard_cost));
                    return "• {$status} **{$i->name}**: **{$stock} {$sym}** (min: {$i->min_stock}) | HPP: {$fmt($cost)}/{$sym}";
                })->join("\n");

                return "📦 **Status Real-Time Stok Bahan Baku** ({$ingredients->count()} item, **{$lowCount} perlu restock**)\n\n{$details}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca status stok: " . $e->getMessage();
            }
        }

        // ── 8. Supplier ───────────────────────────────────────────────────
        if ($this->msgHas($msg, ['supplier', 'vendor', 'pemasok', 'toko bahan', 'tempat beli'])) {
            try {
                $suppliers = \App\Models\Supplier::where('status', 'active')->get();
                if ($suppliers->isEmpty()) return "🏭 Belum ada data supplier yang terdaftar.";
                $list = $suppliers->map(fn($s) => "• **{$s->name}** ({$s->category}) — PIC: {$s->contact_person} ({$s->phone}) | Metode: {$s->payment_method}")->join("\n");
                return "🏭 **Daftar Supplier Aktif** ({$suppliers->count()})\n\n{$list}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca data supplier: " . $e->getMessage();
            }
        }

        // ── 9. Transaksi / Order ──────────────────────────────────────────
        if ($this->msgHas($msg, ['transaksi', 'order', 'pesanan', 'kasir', 'checkout'])) {
            try {
                $todayOrd = PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->count();
                $todayRev = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_amount');
                $recent   = PosOrder::with('cashier')->whereIn('status', $validStatuses)->orderByDesc('created_at')->limit(5)->get();
                $list     = $recent->map(fn($o) => "• #{$o->order_number} — **{$fmt($o->total_amount)}** ({$o->order_type}) oleh " . ($o->cashier?->name ?? 'Kasir') . " ({$o->created_at->format('d/m H:i')})")->join("\n");

                return "🧾 **Laporan Real-Time Transaksi POS**\n\n"
                    . "Hari Ini: **{$todayOrd} order** — Total **{$fmt($todayRev)}**\n\n"
                    . "**5 Transaksi Terakhir:**\n" . ($list ?: '_(Belum ada transaksi)_');
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca data transaksi: " . $e->getMessage();
            }
        }

        // ── 10. Karyawan / User / Staff ────────────────────────────────────
        if ($this->msgHas($msg, ['karyawan', 'staff', 'pegawai', 'user', 'team', 'tim'])) {
            try {
                $users = User::with('role')->where('status', 'active')->get();
                $list  = $users->map(fn($u) => "• **{$u->name}** — Role: " . ($u->role?->name ?? 'User') . " | {$u->email}")->join("\n");
                return "👥 **Daftar Karyawan & Akses Sistem** ({$users->count()} orang)\n\n{$list}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca data karyawan: " . $e->getMessage();
            }
        }

        // ── 11. Hutang / Jatuh Tempo ──────────────────────────────────────
        if ($this->msgHas($msg, ['hutang', 'jatuh tempo', 'tagihan', 'piutang', 'cicilan', 'kredit', 'bayar supplier', 'due'])) {
            try {
                $suppliers = \App\Models\Supplier::where('status', 'active')->whereNotNull('next_payment_due_date')->orderBy('next_payment_due_date')->get();
                if ($suppliers->isEmpty()) return "📝 Tidak ada jadwal pembayaran hutang / jatuh tempo supplier saat ini.";
                $list = $suppliers->map(function ($s) {
                    $due  = \Carbon\Carbon::parse($s->next_payment_due_date);
                    $diff = (int) now()->startOfDay()->diffInDays($due->startOfDay(), false);
                    $st   = $diff < 0 ? "🔴 Terlewat " . abs($diff) . " hari" : ($diff === 0 ? "⚠️ HARI INI" : "⏰ {$diff} hari lagi");
                    return "• **{$s->name}** — Jatuh Tempo: {$due->format('d M Y')} ({$st})";
                })->join("\n");
                return "📝 **Pengingat Jatuh Tempo Supplier**\n\n{$list}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca data jatuh tempo: " . $e->getMessage();
            }
        }

        // ── 12. Resep / Recipe / HPP ──────────────────────────────────────
        if ($this->msgHas($msg, ['resep', 'recipe', 'hpp', 'harga pokok', 'cost produksi', 'biaya produksi'])) {
            try {
                $recipes = \App\Models\Recipe::with(['product', 'ingredients.ingredient.unit'])->get();
                if ($recipes->isEmpty()) return "📋 Belum ada resep produk yang terdaftar.";
                $list = $recipes->map(function ($r) use ($fmt) {
                    $ings = $r->ingredients->map(fn($ri) => "{$ri->ingredient?->name} {$ri->qty_used}" . ($ri->ingredient?->unit?->symbol ?? ''))->join(', ');
                    return "• **{$r->product?->name}** — HPP: **{$fmt($r->total_cost)}** | Bahan: {$ings}";
                })->join("\n");
                return "📋 **Resep & HPP Produk**\n\n{$list}";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca resep produk: " . $e->getMessage();
            }
        }

        // ── 13. Pajak / Tax ───────────────────────────────────────────────
        if ($this->msgHas($msg, ['pajak', 'tax', 'pb1', 'ppn', 'service charge'])) {
            try {
                $tax = \App\Models\TaxSetting::first();
                if (!$tax) return "🏛️ Pengaturan pajak belum dikonfigurasi.";
                $statusTax = $tax->is_enabled ? '✅ Aktif' : '❌ Nonaktif';
                $statusSc  = $tax->is_service_charge_enabled ? '✅ Aktif' : '❌ Nonaktif';
                return "🏛️ **Pengaturan Pajak Resto**\n\n"
                    . "• **{$tax->tax_name}**: {$tax->tax_rate}% ({$statusTax})\n"
                    . "• **{$tax->service_charge_name}**: {$tax->service_charge_rate}% ({$statusSc})";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membaca pengaturan pajak: " . $e->getMessage();
            }
        }

        // ── 14. Ringkasan / Summary ───────────────────────────────────────
        if ($this->msgHas($msg, ['ringkasan', 'summary', 'rangkuman', 'laporan', 'overview', 'bisnis'])) {
            try {
                $todayRev  = (float) PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->sum('total_amount');
                $todayOrd  = PosOrder::whereDate('created_at', $todayStr)->whereIn('status', $validStatuses)->count();
                $todayExp  = (float) \App\Models\Expense::whereDate('expense_date', $todayStr)->sum('amount');
                $ingCount  = Ingredient::whereNull('deleted_at')->count();
                $prodCount = Product::where('available_for_pos', true)->count();
                return "📋 **Ringkasan Bisnis Hari Ini** (" . now()->format('d M Y') . ")\n\n"
                    . "💰 Omzet Hari Ini: **{$fmt($todayRev)}** ({$todayOrd} order)\n"
                    . "💸 Pengeluaran Hari Ini: **{$fmt($todayExp)}**\n"
                    . "📦 Master Inventory: {$ingCount} item bahan baku\n"
                    . "🍵 Produk Aktif POS: {$prodCount} menu";
            } catch (\Throwable $e) {
                return "⚠️ Gagal membuat ringkasan bisnis: " . $e->getMessage();
            }
        }

        // ── Fallback: Smart guidance ──────────────────────────────────────
        return "🤖 **Ratio AI Assistant** siap membantu! Coba tanyakan:\n\n"
            . "📊 *\"Analisis performa bisnis toko\"*\n"
            . "💰 *\"Berapa omzet hari ini?\"*\n"
            . "📊 *\"Bagaimana profit bulan ini?\"*\n"
            . "💸 *\"Total pengeluaran bulan ini?\"*\n"
            . "☕ *\"Produk apa yang paling laku?\"*\n"
            . "📦 *\"Status stok bahan baku?\"*\n"
            . "🏭 *\"Daftar supplier aktif?\"*\n"
            . "🧾 *\"Transaksi terakhir?\"*\n"
            . "👥 *\"Daftar karyawan aktif?\"*";
    }

    /**
     * Get Dynamic Real-time Operational Notifications & Alerts
     */
    public function notifications(Request $request): JsonResponse
    {
        $notifs = [];

        try {
            // 1. Critical Inventory Stock Alerts (Top Priority)
            $criticalIngredients = Ingredient::active()
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereColumn('current_stock', '<=', 'min_stock')
                      ->orWhere('current_stock', '<=', 0);
                })
                ->with('unit')
                ->orderBy('current_stock', 'asc')
                ->get();

            foreach ($criticalIngredients as $ing) {
                $unitSymbol = $ing->unit?->symbol ?? 'unit';
                $stockVal = (float)$ing->current_stock;
                $minVal = (float)$ing->min_stock;
                
                $notifs[] = [
                    'id'         => 'inv-' . $ing->id,
                    'type'       => 'danger',
                    'badge'      => '🔴 STOK KRITIS',
                    'title'      => "Stok {$ing->name} Hampir Habis",
                    'message'    => "Sisa {$stockVal} {$unitSymbol} di Gudang Utama (Min: {$minVal} {$unitSymbol})",
                    'icon'       => '⚠️',
                    'url'        => '/inventory',
                    'timestamp'  => 'Perlu Restock'
                ];
            }

            // 2. Research & Development (R&D) Projects in Testing State
            $testingProjects = \App\Models\ResearchProduct::where('status', 'testing')->get();
            foreach ($testingProjects as $rd) {
                $notifs[] = [
                    'id'         => 'rd-' . $rd->id,
                    'type'       => 'warning',
                    'badge'      => '🧪 TAHAP UJI COBA',
                    'title'      => "Riset \"{$rd->name}\" Siap Rilis POS",
                    'message'    => "Formulasi rasa & uji stok selesai. Siap dipublikasikan ke Kasir.",
                    'icon'       => '🔬',
                    'url'        => '/products/research',
                    'timestamp'  => 'Lab R&D'
                ];
            }

            // 3. Supplier Active Debt & Reminders
            $suppliersWithDebt = \App\Models\Supplier::where('status', 'active')
                ->where('total_debt', '>', 0)
                ->limit(3)
                ->get();

            foreach ($suppliersWithDebt as $sup) {
                $debtFmt = number_format((float)$sup->total_debt, 0, ',', '.');
                $notifs[] = [
                    'id'         => 'sup-' . $sup->id,
                    'type'       => 'info',
                    'badge'      => '📦 SUPPLIER',
                    'title'      => "Tagihan PO {$sup->name}",
                    'message'    => "Tagihan supplier sebesar Rp {$debtFmt} memerlukan perhatian",
                    'icon'       => '🛒',
                    'url'        => '/inventory/suppliers',
                    'timestamp'  => 'Procurement'
                ];
            }

            // 4. Fallback System Notice if no critical alerts
            if (empty($notifs)) {
                $notifs[] = [
                    'id'         => 'sys-ok',
                    'type'       => 'success',
                    'badge'      => '🟢 OPERASIONAL NORMAL',
                    'title'      => 'Semua Sistem Berjalan Optimal',
                    'message'    => 'Tidak ada stok kritis atau tagihan pending saat ini.',
                    'icon'       => '✅',
                    'url'        => '/dashboard',
                    'timestamp'  => 'System Live'
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Notifications Error: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'count'  => count($notifs),
            'data'   => $notifs
        ]);
    }

    private function msgHas(string $msg, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) return true;
        }
        return false;
    }
}
