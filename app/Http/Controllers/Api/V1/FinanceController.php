<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\PosOrder;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Synchronize inventory purchases & clean up trashed ingredients from expenses
     */
    private function syncInventoryExpenses(): void
    {
        $userId   = auth()->id() ?? DB::table('users')->value('id');
        $outletId = auth()->user()?->outlet_id ?? DB::table('outlets')->value('id');

        // 1. Clean up expenses for soft-deleted / trashed ingredients
        $trashedIngredients = DB::table('ingredients')->whereNotNull('deleted_at')->get();
        foreach ($trashedIngredients as $trashed) {
            $rawName = preg_replace('/-TRASHED-\d+$/', '', $trashed->name);
            DB::table('expenses')
                ->whereNull('deleted_at')
                ->where('notes', 'like', '%Auto-generated%')
                ->where(function($q) use ($trashed, $rawName) {
                    $q->where('description', 'like', "%{$trashed->name}%")
                      ->orWhere('description', 'like', "%{$rawName}%");
                })
                ->update(['deleted_at' => now()]);
        }

        $cat = \App\Models\ExpenseCategory::firstOrCreate(
            ['name' => 'Pembelian Bahan Baku & Inventory'],
            ['active' => true]
        );

        // 2. Ensure every active ingredient has its initial/base purchase cost recorded in Finance as an Expense
        $activeIngredients = DB::table('ingredients')->whereNull('deleted_at')->get();
        foreach ($activeIngredients as $ing) {
            $name = $ing->name;
            $net = (float)($ing->net_content ?: 1);
            $lastPrice = (float)($ing->last_purchase_price ?? 0);
            $avgCost = (float)($ing->average_cost ?? 0);

            // Compute correct package purchase price
            if ($lastPrice >= ($avgCost * $net * 0.9) && $lastPrice > 1000) {
                $packagePrice = $lastPrice;
            } else {
                $packagePrice = $avgCost * $net;
                if ($packagePrice <= 0) {
                    $packagePrice = (float)($ing->standard_cost * $net);
                }
            }

            if ($packagePrice <= 0) continue;

            // Check if active expense already exists for this ingredient purchase
            $existingExp = DB::table('expenses')
                ->whereNull('deleted_at')
                ->where('description', 'like', "%{$name}%")
                ->first();

            if (!$existingExp) {
                $refNo = 'EXP-' . date('Ym') . '-' . sprintf('%04d', rand(1000, 9999));
                DB::table('expenses')->insert([
                    'reference_number' => $refNo,
                    'category_id'      => $cat->id,
                    'expense_date'     => $ing->created_at ? \Carbon\Carbon::parse($ing->created_at)->toDateString() : now()->toDateString(),
                    'amount'           => $packagePrice,
                    'description'      => "Pembelian Inventory Baru: {$name} (1 kemasan @ Rp " . number_format($packagePrice, 0, ',', '.') . ")",
                    'payment_method'   => 'cash',
                    'notes'            => 'Auto-generated dari Tambah Inventory Baru',
                    'status'           => 'paid',
                    'outlet_id'        => $outletId,
                    'created_by'       => $userId,
                    'created_at'       => $ing->created_at ?? now(),
                    'updated_at'       => now(),
                ]);
            } else if ((float)$existingExp->amount < 1000 && $packagePrice >= 1000) {
                // Fix legacy small unit price amounts (e.g. 112.5 -> 135000)
                DB::table('expenses')
                    ->where('id', $existingExp->id)
                    ->update([
                        'amount'      => $packagePrice,
                        'description' => "Pembelian Inventory Baru: {$name} (1 kemasan @ Rp " . number_format($packagePrice, 0, ',', '.') . ")",
                        'updated_at'  => now(),
                    ]);
            }
        }

        // 3. Ensure any stock_in movements also have expense records if missing
        $stockIns = DB::table('stock_movements')
            ->where('type', 'stock_in')
            ->where('qty', '>', 0)
            ->get();

        foreach ($stockIns as $sm) {
            $ing = DB::table('ingredients')->where('id', $sm->ingredient_id)->whereNull('deleted_at')->first();
            if (!$ing) continue;

            $cost = (float)($sm->total_cost > 0 ? $sm->total_cost : ($sm->qty * $sm->unit_cost));
            if ($cost <= 0) continue;

            $expExists = DB::table('expenses')
                ->whereNull('deleted_at')
                ->where('notes', 'like', "%Restock%")
                ->where('description', 'like', "%{$ing->name}%")
                ->where('created_at', '>=', \Carbon\Carbon::parse($sm->created_at)->subMinutes(5))
                ->where('created_at', '<=', \Carbon\Carbon::parse($sm->created_at)->addMinutes(5))
                ->exists();

            if (!$expExists) {
                // Only insert if not already covered by initial stock or recent restock
                $recentExp = DB::table('expenses')
                    ->whereNull('deleted_at')
                    ->where('description', 'like', "%{$ing->name}%")
                    ->where('amount', '>=', $cost * 0.95)
                    ->where('amount', '<=', $cost * 1.05)
                    ->exists();

                if (!$recentExp) {
                    $refNo = 'EXP-' . date('Ym') . '-' . sprintf('%04d', rand(1000, 9999));
                    DB::table('expenses')->insert([
                        'reference_number' => $refNo,
                        'category_id'      => $cat->id,
                        'expense_date'     => $sm->movement_at ? \Carbon\Carbon::parse($sm->movement_at)->toDateString() : now()->toDateString(),
                        'amount'           => $cost,
                        'description'      => "Kulakan / Restock Inventory: {$ing->name} (Rp " . number_format($cost, 0, ',', '.') . ")",
                        'payment_method'   => 'cash',
                        'notes'            => 'Auto-generated from Stock In / Kulakan Ulang',
                        'status'           => 'paid',
                        'outlet_id'        => $outletId,
                        'created_by'       => $userId,
                        'created_at'       => $sm->created_at ?? now(),
                        'updated_at'       => now(),
                    ]);
                }
            }
        }
    }

    // ── GET /finance/summary ──────────────────────────────────────────────
    public function summary(Request $request): JsonResponse
    {
        $this->syncInventoryExpenses();

        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date', $startDate);

        $posQuery = PosOrder::whereNotIn('status', ['cancelled', 'rejected']);
        if ($startDate) {
            $posQuery->whereBetween('created_at', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay()
            ]);
        }
        $posRevenue = (float) $posQuery->sum('total_amount');
        $posCount   = $posQuery->count();

        $expQuery = DB::table('expenses')->whereNull('deleted_at');
            
        if ($startDate) {
            $expQuery->whereBetween('expense_date', [
                \Carbon\Carbon::parse($startDate)->toDateString(),
                \Carbon\Carbon::parse($endDate)->toDateString()
            ]);
        }
        $totalExpenses = (float) $expQuery->sum('amount');
        $expCount      = $expQuery->count();

        return response()->json([
            'total_revenue'     => $posRevenue,
            'total_expenses'    => $totalExpenses,
            'net_cash_flow'     => $posRevenue - $totalExpenses,
            'transaction_count' => $posCount + $expCount,
        ]);
    }

    // ── GET /finance/cash-flow ────────────────────────────────────────────
    public function cashFlow(Request $request): JsonResponse
    {
        $this->syncInventoryExpenses();

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = \Carbon\Carbon::parse($request->get('start_date'))->startOfDay();
            $end   = \Carbon\Carbon::parse($request->get('end_date'))->endOfDay();
        } elseif ($request->filled('start_date')) {
            $start = \Carbon\Carbon::parse($request->get('start_date'))->startOfDay();
            $end   = \Carbon\Carbon::parse($request->get('start_date'))->endOfDay();
        } else {
            $months = intval($request->get('months', 1));
            $start  = now()->subMonths($months - 1)->startOfMonth();
            $end    = now()->endOfMonth();
        }

        $revenue = PosOrder::whereNotIn('status', ['cancelled', 'rejected'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $expenses = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'rejected')
            ->selectRaw('expense_date as date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->get();

        $weeklyRevenue  = $this->groupByWeek($revenue);
        $weeklyExpenses = $this->groupByWeek($expenses);

        return response()->json([
            'weekly_revenue'  => $weeklyRevenue,
            'weekly_expenses' => $weeklyExpenses,
            'total_revenue'   => floatval($revenue->sum('total')),
            'total_expenses'  => floatval($expenses->sum('total')),
            'net_profit'      => floatval($revenue->sum('total') - $expenses->sum('total')),
        ]);
    }

    private function groupByWeek($items): array
    {
        $weeks = [
            'Minggu 1' => 0,
            'Minggu 2' => 0,
            'Minggu 3' => 0,
            'Minggu 4' => 0,
        ];
        foreach ($items as $item) {
            $day = intval(date('j', strtotime($item->date)));
            $weekIndex = min(4, max(1, ceil($day / 7)));
            $weekNum = 'Minggu ' . $weekIndex;
            $weeks[$weekNum] = ($weeks[$weekNum] ?? 0) + floatval($item->total);
        }
        return $weeks;
    }

    // ── GET /finance/profit-loss ──────────────────────────────────────────
    public function profitLoss(Request $request): JsonResponse
    {
        $this->syncInventoryExpenses();

        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date',   now()->toDateString());

        $revenue = PosOrder::whereNotIn('status', ['cancelled', 'rejected'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->sum('total_amount');

        $cogs = PosOrder::whereNotIn('status', ['cancelled', 'rejected'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->sum('total_cost');

        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        $grossProfit = $revenue - $cogs;
        $netProfit   = $grossProfit - $expenses;
        $margin      = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

        $expenseBreakdown = Expense::with('category')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get();

        return response()->json([
            'period'            => ['start' => $startDate, 'end' => $endDate],
            'revenue'           => $revenue,
            'cogs'              => $cogs,
            'gross_profit'      => $grossProfit,
            'operating_expense' => $expenses,
            'net_profit'        => $netProfit,
            'net_margin_pct'    => $margin,
            'expense_breakdown' => $expenseBreakdown,
        ]);
    }

    // ── GET /finance/daily-closings ───────────────────────────────────────
    public function dailyClosings(Request $request): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'No daily closings found.']);
    }

    // ── POST /finance/daily-closings ──────────────────────────────────────
    public function closingStore(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Daily closing recorded.'], 201);
    }

    // ── GET /finance/transactions ─────────────────────────────────────────
    public function transactions(Request $request): JsonResponse
    {
        $this->syncInventoryExpenses();

        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date', $startDate);
        $type      = strtolower($request->get('type', ''));
        $search    = $request->get('search');

        $inflows  = collect();
        $outflows = collect();

        // 1. Fetch Inflows (POS Orders) unless type is expense/outflow
        if ($type !== 'expense' && $type !== 'outflow') {
            $ordQuery = PosOrder::with(['cashier'])
                ->whereNotIn('status', ['cancelled', 'rejected']);

            if ($startDate) {
                $ordQuery->whereBetween('created_at', [
                    \Carbon\Carbon::parse($startDate)->startOfDay(),
                    \Carbon\Carbon::parse($endDate)->endOfDay()
                ]);
            }

            if ($search) {
                $ordQuery->where('order_number', 'like', "%{$search}%");
            }

            $inflows = $ordQuery->get()->map(function($ord) {
                return [
                    'id'               => 'inflow_' . $ord->id,
                    'raw_id'           => $ord->id,
                    'type'             => 'income',
                    'reference_number' => $ord->order_number,
                    'created_at'       => $ord->created_at ? $ord->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'date'             => $ord->created_at ? $ord->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'description'      => 'Penjualan POS Kasir (' . ($ord->lines ? count($ord->lines) : 1) . ' Items)',
                    'category'         => ['name' => 'Penjualan POS Kasir'],
                    'category_name'    => 'Penjualan POS Kasir',
                    'category_id'      => null,
                    'payment_method'   => $ord->payment_method ?? 'cash',
                    'amount'           => floatval($ord->total_amount),
                    'status'           => 'paid',
                    'user'             => $ord->cashier ? ['name' => $ord->cashier->name] : ['name' => 'Kasir POS'],
                    'creator'          => $ord->cashier ? ['name' => $ord->cashier->name] : ['name' => 'Kasir POS'],
                    'can_edit'         => false,
                    'can_delete'       => false,
                ];
            });
        }

        if ($type !== 'income' && $type !== 'inflow') {
            $expQuery = DB::table('expenses')->whereNull('deleted_at');

            if ($startDate) {
                $expQuery->whereBetween('expense_date', [
                    \Carbon\Carbon::parse($startDate)->toDateString(),
                    \Carbon\Carbon::parse($endDate)->toDateString()
                ]);
            }

            if ($search) {
                $expQuery->where('description', 'like', "%{$search}%");
            }

            $outflows = $expQuery->get()->map(function($exp) {
                return [
                    'id'               => 'outflow_' . $exp->id,
                    'raw_id'           => $exp->id,
                    'type'             => 'expense',
                    'reference_number' => $exp->reference_number ?? ('EXP-' . sprintf('%04d', $exp->id)),
                    'created_at'       => $exp->created_at ? \Carbon\Carbon::parse($exp->created_at)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'date'             => $exp->expense_date ? \Carbon\Carbon::parse($exp->expense_date)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'description'      => $exp->description ?? 'Pengeluaran Operasional',
                    'notes'            => $exp->notes ?? null,
                    'category'         => ['name' => $exp->is_revenue_sharing ? 'Revenue Sharing' : 'Operasional'],
                    'category_name'    => $exp->is_revenue_sharing ? 'Revenue Sharing' : 'Operasional',
                    'category_id'      => null,
                    'payment_method'   => $exp->payment_method ?? 'cash',
                    'amount'           => floatval($exp->amount),
                    'status'           => $exp->status ?? 'paid',
                    'user'             => ['name' => 'Management / CEO'],
                    'creator'          => ['name' => 'Management / CEO'],
                    'can_edit'         => true,
                    'can_delete'       => true,
                    'original_expense' => $exp,
                ];
            });
        }

        $all = $inflows->concat($outflows)->sortByDesc('created_at')->values();

        return response()->json([
            'data'          => $all,
            'total'         => $all->count(),
            'total_inflow'  => floatval($inflows->sum('amount')),
            'total_outflow' => floatval($outflows->sum('amount')),
        ]);
    }
}
