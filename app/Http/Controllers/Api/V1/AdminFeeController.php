<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFeeController extends Controller
{
    // GET /v1/admin-fee/settings — active rate & effective_date
    public function settings(): JsonResponse
    {
        $setting = DB::table('admin_fee_settings')->first();
        if (!$setting) {
            DB::table('admin_fee_settings')->insert([
                'rate' => 5.0,
                'effective_date' => now()->startOfMonth()->toDateString(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $setting = DB::table('admin_fee_settings')->first();
        }

        return response()->json([
            'rate' => (float)($setting->rate ?? 0),
            'effective_date' => $setting->effective_date ?? null
        ]);
    }

    // GET /v1/admin-fee/proposals
    public function proposals(Request $request): JsonResponse
    {
        $query = DB::table('admin_fee_proposals')
            ->leftJoin('users as p', 'admin_fee_proposals.proposed_by', '=', 'p.id')
            ->leftJoin('users as r', 'admin_fee_proposals.reviewed_by', '=', 'r.id')
            ->select(
                'admin_fee_proposals.*',
                'p.name as proposer_name',
                'r.name as reviewer_name'
            )
            ->orderByDesc('admin_fee_proposals.created_at');

        if ($request->filled('status')) {
            $query->where('admin_fee_proposals.status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    // POST /v1/admin-fee/proposals — CEO creates proposal with effective date calendar picker!
    public function propose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'proposed_rate'  => 'required|numeric|min:0|max:100',
            'effective_date' => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $current = DB::table('admin_fee_settings')->first();

        $id = DB::table('admin_fee_proposals')->insertGetId([
            'proposed_rate'  => $data['proposed_rate'],
            'effective_date' => $data['effective_date'],
            'current_rate'   => $current->rate ?? 0,
            'notes'          => $data['notes'] ?? null,
            'status'         => 'pending',
            'proposed_by'    => auth()->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json([
            'message' => 'Pengajuan biaya admin & tanggal efektif berhasil dikirim ke Operator.',
            'id' => $id
        ], 201);
    }

    // PUT /v1/admin-fee/proposals/{id}/review — Operator reviews (approve / reject / revise)
    public function review(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status'         => 'required|in:approved,rejected,revised',
            'operator_notes' => 'nullable|string',
        ]);

        $proposal = DB::table('admin_fee_proposals')->find($id);
        if (!$proposal) {
            return response()->json(['message' => 'Proposal tidak ditemukan.'], 404);
        }

        DB::table('admin_fee_proposals')->where('id', $id)->update([
            'status'         => $data['status'],
            'operator_notes' => $data['operator_notes'] ?? null,
            'reviewed_by'    => auth()->id(),
            'reviewed_at'    => now(),
            'updated_at'     => now(),
        ]);

        // If approved, update active rate and effective start date!
        if ($data['status'] === 'approved') {
            DB::table('admin_fee_settings')->update([
                'rate'           => $proposal->proposed_rate,
                'effective_date' => $proposal->effective_date,
                'updated_at'     => now(),
            ]);

            // Automatically sync/update the Expense entry
            $this->syncRevenueSharingExpense();
        }

        return response()->json(['message' => 'Review pengajuan biaya admin berhasil disimpan.']);
    }

    // GET /v1/admin-fee/revenue-sharing — Revenue sharing calculation & auto-expense sync
    public function revenueSharing(Request $request): JsonResponse
    {
        $setting       = DB::table('admin_fee_settings')->first();
        $rate          = (float)($setting->rate ?? 0);
        $effectiveDate = $setting->effective_date ?? null;

        // Query transactions ONLY starting on or after effective_date!
        $orderQuery = PosOrder::whereIn('status', ['completed', 'paid', 'confirmed']);
        if ($effectiveDate) {
            $orderQuery->whereDate('created_at', '>=', $effectiveDate);
        }

        $totalRevenue = (float)$orderQuery->sum('total_amount');
        $totalSharing = round($totalRevenue * ($rate / 100), 0);

        // Sync or get the Revenue Sharing expense record
        $expense = $this->syncRevenueSharingExpense();

        return response()->json([
            'rate'            => $rate,
            'effective_date'  => $effectiveDate,
            'total_revenue'   => $totalRevenue,
            'total_sharing'   => $totalSharing,
            'expense'         => $expense
        ]);
    }

    // CEO pays the Revenue Sharing Expense (Status becomes 'paid')
    public function paySharing(Request $request): JsonResponse
    {
        $expense = $this->syncRevenueSharingExpense();
        if (!$expense) {
            return response()->json(['message' => 'Expense tagihan revenue sharing belum tersedia.'], 400);
        }

        DB::table('expenses')->where('id', $expense->id)->update([
            'status'     => 'paid',
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pembayaran Revenue Sharing dikirim oleh CEO. Status: PAID (Menunggu Konfirmasi Operator).',
            'status'  => 'paid'
        ]);
    }

    // Operator confirms the Revenue Sharing Payment (Status becomes 'confirmed')
    public function confirmSharing(Request $request, int $expenseId): JsonResponse
    {
        $expense = DB::table('expenses')->find($expenseId);
        if (!$expense) {
            return response()->json(['message' => 'Tagihan expense tidak ditemukan.'], 404);
        }

        DB::table('expenses')->where('id', $expenseId)->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'message' => 'Pembayaran Revenue Sharing berhasil dikonfirmasi Operator. Status: CONFIRMED.',
            'status'  => 'confirmed'
        ]);
    }

    /**
     * Helper to sync/create Revenue Sharing Expense entry in expenses table
     */
    private function syncRevenueSharingExpense()
    {
        $setting       = DB::table('admin_fee_settings')->first();
        $rate          = (float)($setting->rate ?? 0);
        $effectiveDate = $setting->effective_date ?? now()->startOfMonth()->toDateString();

        $totalRevenue = (float)PosOrder::whereIn('status', ['completed', 'paid', 'confirmed'])
            ->whereDate('created_at', '>=', $effectiveDate)
            ->sum('total_amount');

        $totalSharing = round($totalRevenue * ($rate / 100), 0);

        $catId = DB::table('expense_categories')->where('name', 'like', '%Admin%')->value('id')
              ?? DB::table('expense_categories')->first()->id ?? 1;

        $desc = "Revenue Sharing Operator (Rate " . $rate . "%, Mulai Efektif: " . $effectiveDate . ")";

        $expense = DB::table('expenses')
            ->where('is_revenue_sharing', true)
            ->first();

        $userId   = auth()->id() ?? DB::table('users')->value('id');
        $outletId = DB::table('outlets')->value('id');

        if (!$expense) {
            $id = DB::table('expenses')->insertGetId([
                'outlet_id'          => $outletId,
                'category_id'        => $catId,
                'created_by'         => $userId,
                'reference_number'   => 'REV-SHARING-' . date('Ym'),
                'expense_date'       => now()->toDateString(),
                'amount'             => $totalSharing,
                'is_revenue_sharing' => 1,
                'description'        => $desc,
                'status'             => 'pending',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $expense = DB::table('expenses')->find($id);
        } else {
            // Update amount if not yet confirmed
            if (!in_array($expense->status, ['confirmed'])) {
                DB::table('expenses')->where('id', $expense->id)->update([
                    'amount'      => $totalSharing,
                    'description' => $desc,
                    'updated_at'  => now(),
                ]);
                $expense = DB::table('expenses')->find($expense->id);
            }
        }

        return $expense;
    }
}
