<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\PosOrder;
use App\Models\PosOrderLine;
use App\Models\PosPayment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Standard completed/valid order statuses for financial calculations
     */
    private array $validStatuses = ['completed', 'confirmed', 'paid', 'success', 'settled', 'preparing', 'ready'];

    // ─────────────────────────────────────────────────────────────────────────
    // 1. GET /api/v1/reports/sales — Laporan Penjualan (Strict DB Query)
    // ─────────────────────────────────────────────────────────────────────────
    public function sales(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date',   now()->toDateString());

        // ── KPI Summary from actual PosOrders ──────────────────────────────
        $summary = PosOrder::whereIn('status', $this->validStatuses)
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->selectRaw('
                COUNT(*)                      AS total_orders,
                COALESCE(SUM(total_amount),0) AS total_sales,
                COALESCE(SUM(gross_profit),0) AS gross_profit,
                COALESCE(AVG(total_amount),0) AS aov
            ')
            ->first();

        $totalOrders = (int)   ($summary->total_orders ?? 0);
        $totalSales  = (float) ($summary->total_sales  ?? 0);
        $grossProfit = (float) ($summary->gross_profit ?? 0);
        $aov         = (float) ($summary->aov          ?? 0);

        // ── Per-product breakdown from actual PosOrderLines ─────────────────
        $lines = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
            ->whereIn('pos_orders.status', $this->validStatuses)
            ->whereBetween(DB::raw('DATE(pos_orders.created_at)'), [$startDate, $endDate])
            ->join('products', 'pos_order_lines.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->select(
                'pos_order_lines.product_name as name',
                'product_categories.name as category',
                DB::raw('SUM(pos_order_lines.qty)      AS qty'),
                DB::raw('SUM(pos_order_lines.subtotal) AS revenue'),
                DB::raw('SUM(pos_order_lines.qty * COALESCE(pos_order_lines.unit_cost, products.hpp, 0)) AS cogs')
            )
            ->groupBy('pos_order_lines.product_name', 'product_categories.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) {
                $profit = max(0, (float)$row->revenue - (float)$row->cogs);
                $margin = $row->revenue > 0 ? round(($profit / $row->revenue) * 100, 1) : 0;
                return [
                    'name'     => $row->name,
                    'category' => $row->category ?? 'Lain-Lain',
                    'qty'      => (int) $row->qty,
                    'revenue'  => (float) $row->revenue,
                    'profit'   => (float) $profit,
                    'margin'   => $margin,
                ];
            });

        // ── Payment Method Breakdown from actual PosPayments ───────────────
        $methodLabels = [
            'CASH'          => 'Tunai (Cash)',
            'QRIS'          => 'QRIS',
            'BANK_TRANSFER' => 'Transfer Bank',
            'DEBIT_CARD'    => 'Kartu Debit',
            'CREDIT_CARD'   => 'Kartu Kredit',
        ];

        $paymentBreakdown = PosPayment::whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', $this->validStatuses)
                  ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
            })
            ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->get()
            ->map(function ($i) use ($methodLabels) {
                $rawMethod = strtoupper($i->method);
                return [
                    'method' => $methodLabels[$rawMethod] ?? $rawMethod,
                    'total'  => (float) $i->total,
                    'count'  => (int) $i->count,
                ];
            });

        return response()->json([
            'period'            => ['start' => $startDate, 'end' => $endDate],
            'summary'           => [
                'total_orders' => $totalOrders,
                'total_sales'  => $totalSales,
                'gross_profit' => $grossProfit,
                'aov'          => $aov,
            ],
            'items'             => $lines->values(),
            'payment_breakdown' => $paymentBreakdown->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. GET /api/v1/reports/inventory — Laporan Stok & Valuation (Strict DB)
    // ─────────────────────────────────────────────────────────────────────────
    public function inventory(Request $request): JsonResponse
    {
        $ingredients = Ingredient::with(['unit', 'category', 'stocks'])->get();

        $items = $ingredients->map(function ($ing) {
            $stockFromWh  = $ing->stocks->sum('qty');
            $currentStock = $ing->stocks->isNotEmpty() ? (float)$stockFromWh : (float)$ing->current_stock;

            $unitStr   = is_object($ing->unit) ? ($ing->unit->symbol ?? $ing->unit->name ?? 'unit') : ($ing->unit ?? 'unit');
            // Use the centralised stock_value accessor so net_content division is applied consistently.
            // Avoids max(average_cost, last_purchase_price) which forces a package-level price onto per-unit stock.
            $valuation = (float) $ing->stock_value;

            $status = 'normal';
            if ($currentStock <= 0) {
                $status = 'out_of_stock';
            } elseif ($currentStock <= (float)$ing->min_stock) {
                $status = 'low_stock';
            }

            return [
                'id'           => $ing->id,
                'code'         => $ing->code,
                'name'         => $ing->name,
                'category'     => $ing->category->name ?? 'Umum',
                'unit'         => $unitStr,
                'current_stock'=> $currentStock,
                'min_stock'    => (float) $ing->min_stock,
                'unit_cost'    => (float) $ing->cost_per_unit,
                'valuation'    => $valuation,
                'status'       => $status,
            ];
        });

        $totalValuation   = $items->sum('valuation');
        $totalItems       = $items->count();
        $lowStockCount    = $items->where('status', 'low_stock')->count();
        $outOfStockCount  = $items->where('status', 'out_of_stock')->count();

        return response()->json([
            'summary' => [
                'total_items'       => $totalItems,
                'total_valuation'   => (float) $totalValuation,
                'low_stock_count'   => $lowStockCount,
                'out_of_stock_count'=> $outOfStockCount,
            ],
            'items' => $items->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. GET /api/v1/reports/profit-loss — Laporan Laba Rugi (Strict DB Query)
    // ─────────────────────────────────────────────────────────────────────────
    public function profitLoss(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date',   now()->toDateString());

        // Revenue from actual POS Orders
        $revenue = (float) PosOrder::whereIn('status', $this->validStatuses)
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->sum('total_amount');

        // COGS from actual POS Orders
        $cogs = (float) PosOrder::whereIn('status', $this->validStatuses)
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->sum('total_cost');

        // Operating Expenses from Expenses table
        $operatingExpense = (float) Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        $grossProfit   = $revenue - $cogs;
        $totalExpenses = $operatingExpense;
        $netProfit     = $grossProfit - $totalExpenses;
        $grossMargin   = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0;
        $netMargin     = $revenue > 0 ? round(($netProfit   / $revenue) * 100, 1) : 0;

        // Expense breakdown by category
        $expenseBreakdown = Expense::with('category')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->map(fn($e) => [
                'category' => $e->category->name ?? 'Operasional & Kasir',
                'amount'   => (float) $e->total,
            ]);

        // Monthly trend for P&L table based on actual database records
        $monthlyData = [];
        $start = \Carbon\Carbon::parse($startDate)->startOfMonth();
        $end   = \Carbon\Carbon::parse($endDate)->endOfMonth();
        $curr  = $start->copy();

        while ($curr <= $end) {
            $mStart = $curr->copy()->startOfMonth()->toDateString();
            $mEnd   = $curr->copy()->endOfMonth()->toDateString();

            $mRev  = (float) PosOrder::whereIn('status', $this->validStatuses)
                ->whereBetween(DB::raw('DATE(created_at)'), [$mStart, $mEnd])->sum('total_amount');
            $mCogs = (float) PosOrder::whereIn('status', $this->validStatuses)
                ->whereBetween(DB::raw('DATE(created_at)'), [$mStart, $mEnd])->sum('total_cost');
            $mExp  = (float) Expense::whereBetween('expense_date', [$mStart, $mEnd])
                ->where('status', '!=', 'rejected')->sum('amount');

            $monthlyData[] = [
                'label'       => $curr->format('M Y'),
                'revenue'     => $mRev,
                'cogs'        => $mCogs,
                'gross_profit'=> $mRev - $mCogs,
                'expenses'    => $mExp,
                'net_profit'  => ($mRev - $mCogs) - $mExp,
            ];
            $curr->addMonth();
        }

        return response()->json([
            'period'            => ['start' => $startDate, 'end' => $endDate],
            'summary'           => [
                'revenue'           => $revenue,
                'cogs'              => $cogs,
                'gross_profit'      => $grossProfit,
                'gross_margin_pct'  => $grossMargin,
                'operating_expense' => $totalExpenses,
                'net_profit'        => $netProfit,
                'net_margin_pct'    => $netMargin,
            ],
            'expense_breakdown' => $expenseBreakdown->values(),
            'monthly_trend'     => $monthlyData,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. GET /api/v1/reports/top-menu — Laporan Menu Terlaris (Strict DB)
    // ─────────────────────────────────────────────────────────────────────────
    public function topMenu(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date');
        $range     = $request->get('range', '1_month');
        $todayStr  = now()->toDateString();

        $query = PosOrderLine::join('pos_orders', 'pos_order_lines.order_id', '=', 'pos_orders.id')
            ->whereIn('pos_orders.status', $this->validStatuses)
            ->join('products', 'pos_order_lines.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id');

        if ($startDate && $endDate) {
            $query->whereBetween(DB::raw('DATE(pos_orders.created_at)'), [$startDate, $endDate]);
            $label = "Periode {$startDate} s/d {$endDate}";
        } else {
            if ($range === '1_day') {
                $query->whereDate('pos_orders.created_at', $todayStr);
                $label = 'Hari Ini (' . now()->format('d M Y') . ')';
            } elseif ($range === '7_days') {
                $query->where('pos_orders.created_at', '>=', now()->subDays(6)->startOfDay());
                $label = '7 Hari Terakhir';
            } elseif ($range === '1_year') {
                $query->where('pos_orders.created_at', '>=', now()->subYear()->startOfDay());
                $label = '1 Tahun Terakhir';
            } else {
                $query->where('pos_orders.created_at', '>=', now()->subDays(29)->startOfDay());
                $label = '30 Hari Terakhir';
            }
        }

        $items = (clone $query)
            ->select(
                'pos_order_lines.product_name as name',
                'product_categories.name as category',
                DB::raw('SUM(pos_order_lines.qty)      AS total_qty'),
                DB::raw('SUM(pos_order_lines.subtotal) AS total_revenue'),
                DB::raw('SUM(pos_order_lines.qty * COALESCE(pos_order_lines.unit_cost, products.hpp, 0)) AS total_cogs')
            )
            ->groupBy('pos_order_lines.product_name', 'product_categories.name')
            ->orderByDesc('total_qty')
            ->get()
            ->map(function ($row, $index) {
                $profit = max(0, (float)$row->total_revenue - (float)$row->total_cogs);
                $margin = $row->total_revenue > 0 ? round(($profit / $row->total_revenue) * 100, 1) : 0;
                return [
                    'rank'          => $index + 1,
                    'name'          => $row->name,
                    'category'      => $row->category ?? 'Lain-Lain',
                    'total_qty'     => (int) $row->total_qty,
                    'total_revenue' => (float) $row->total_revenue,
                    'profit'        => (float) $profit,
                    'margin'        => $margin,
                ];
            });

        $totalQty     = $items->sum('total_qty');
        $totalRevenue = $items->sum('total_revenue');

        return response()->json([
            'range'         => $range,
            'range_label'   => $label,
            'summary'       => [
                'total_menu_sold' => (int)   $totalQty,
                'total_revenue'   => (float) $totalRevenue,
                'total_sku'       => $items->count(),
            ],
            'items'         => $items->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. GET /api/v1/reports/export — Export Laporan to Excel (CSV Format)
    // ─────────────────────────────────────────────────────────────────────────
    public function exportExcel(Request $request): StreamedResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date',   now()->toDateString());
        $filename  = "Laporan_Bisnis_Ratio_Coffee_" . date('Ymd_His') . ".csv";

        $sales     = json_decode($this->sales($request)->getContent(), true);
        $pl        = json_decode($this->profitLoss($request)->getContent(), true);
        $inventory = json_decode($this->inventory($request)->getContent(), true);
        $topMenu   = json_decode($this->topMenu($request)->getContent(), true);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($startDate, $endDate, $sales, $pl, $inventory, $topMenu) {
            $file = fopen('php://output', 'w');
            
            fputs($file, "\xEF\xBB\xBF");

            $csv = function($row) use ($file) {
                fputcsv($file, $row, ';');
            };

            $csv(['=== LAPORAN BISNIS RATIO COFFEE ERP ===']);
            $csv(['Periode Laporan', "{$startDate} s/d {$endDate}"]);
            $csv([]);

            // 1. Ringkasan Penjualan
            $csv(['1. RINGKASAN PENJUALAN & TRANSAKSI']);
            $csv(['Total Omzet Penjualan', 'Total Struk / Transaksi', 'Laba Kotor Sales', 'Rata-Rata Nilai Struk (AOV)']);
            $csv([
                $sales['summary']['total_sales'] ?? 0,
                $sales['summary']['total_orders'] ?? 0,
                $sales['summary']['gross_profit'] ?? 0,
                $sales['summary']['aov'] ?? 0,
            ]);
            $csv([]);

            $csv(['RINCIAN PENJUALAN PER PRODUK']);
            $csv(['Nama Produk', 'Kategori', 'Qty Terjual', 'Total Omzet (Rp)', 'Estimasi Profit (Rp)', 'Margin (%)']);
            foreach ($sales['items'] ?? [] as $item) {
                $csv([
                    $item['name'],
                    $item['category'],
                    $item['qty'],
                    $item['revenue'],
                    $item['profit'],
                    $item['margin'] . '%',
                ]);
            }
            $csv([]);

            // 2. Laba Rugi
            $csv(['2. LAPORAN LABA RUGI (PROFIT & LOSS)']);
            $csv(['Total Pendapatan', 'Total HPP (COGS)', 'Beban Operasional', 'Net Profit (Laba Bersih)', 'Net Margin (%)']);
            $csv([
                $pl['summary']['revenue'] ?? 0,
                $pl['summary']['cogs'] ?? 0,
                $pl['summary']['operating_expense'] ?? 0,
                $pl['summary']['net_profit'] ?? 0,
                ($pl['summary']['net_margin_pct'] ?? 0) . '%',
            ]);
            $csv([]);

            // 3. Valuasi Inventaris
            $csv(['3. LAPORAN VALUASI STOK INVENTARIS']);
            $csv(['Total SKU Item', 'Total Valuasi Stok (Rp)', 'Item Stok Menipis', 'Item Stok Habis']);
            $csv([
                $inventory['summary']['total_items'] ?? 0,
                $inventory['summary']['total_valuation'] ?? 0,
                $inventory['summary']['low_stock_count'] ?? 0,
                $inventory['summary']['out_of_stock_count'] ?? 0,
            ]);
            $csv([]);

            // 4. Menu Terlaris
            $csv(['4. RANKING MENU TERLARIS']);
            $csv(['Rank', 'Nama Menu', 'Kategori', 'Qty Terjual', 'Total Omzet (Rp)', 'Profit (Rp)', 'Margin (%)']);
            foreach ($topMenu['items'] ?? [] as $tm) {
                $csv([
                    $tm['rank'],
                    $tm['name'],
                    $tm['category'],
                    $tm['total_qty'],
                    $tm['total_revenue'],
                    $tm['profit'],
                    $tm['margin'] . '%',
                ]);
            }

            fclose($file);
        }, 200, $headers);
    }
}
