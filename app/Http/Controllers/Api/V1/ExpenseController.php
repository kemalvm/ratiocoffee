<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Auto-sync Revenue Sharing Expense from Admin Fee settings & effective date
        $this->syncRevenueSharingExpense();

        $expenses = DB::table('expenses')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($e) {
                $e->name = $e->name ?? $e->description ?? ('Expense #' . $e->id);
                $e->latest_payment = DB::table('expense_payments')
                    ->where('expense_id', $e->id)
                    ->orderByDesc('due_date')
                    ->first();

                // If expense itself has payment or revenue sharing status
                if ($e->is_revenue_sharing) {
                    $st = $e->status ?? 'pending';
                    if ($st === 'confirmed') {
                        $e->latest_payment = (object)['status' => 'confirmed', 'label' => 'Confirmed'];
                    } elseif ($st === 'paid') {
                        $e->latest_payment = (object)['status' => 'paid', 'label' => 'Paid (Menunggu Konfirmasi Operator)'];
                    } else {
                        $e->latest_payment = (object)['status' => 'pending', 'label' => 'Belum Bayar (Pending)'];
                    }
                }

                $e->next_due = $this->nextDueLabel($e);
                return $e;
            });

        return response()->json($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'amount'             => 'nullable|numeric|min:0',
            'frequency'          => 'required|in:daily,weekly,monthly',
            'reminder_day'       => 'nullable|integer|min:0|max:6',
            'reminder_date'      => 'nullable|integer|min:1|max:31',
            'bank_name'          => 'nullable|string|max:100',
            'bank_account'       => 'nullable|string|max:50',
            'account_holder'     => 'nullable|string|max:200',
            'notes'              => 'nullable|string',
            'is_revenue_sharing' => 'boolean',
        ]);
        $data['created_by'] = auth()->id();
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('expenses')->insertGetId($data);
        return response()->json(['message' => 'Expense berhasil ditambahkan.', 'expense' => DB::table('expenses')->find($id)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'               => 'sometimes|string|max:200',
            'amount'             => 'nullable|numeric|min:0',
            'frequency'          => 'sometimes|in:daily,weekly,monthly',
            'reminder_day'       => 'nullable|integer|min:0|max:6',
            'reminder_date'      => 'nullable|integer|min:1|max:31',
            'bank_name'          => 'nullable|string|max:100',
            'bank_account'       => 'nullable|string|max:50',
            'account_holder'     => 'nullable|string|max:200',
            'notes'              => 'nullable|string',
            'is_revenue_sharing' => 'boolean',
        ]);
        $data['updated_at'] = now();
        DB::table('expenses')->where('id', $id)->update($data);
        return response()->json(['message' => 'Expense berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('expenses')->where('id', $id)->update(['deleted_at' => now()]);
        return response()->json(['message' => 'Expense berhasil dihapus.']);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $expense = DB::table('expenses')->find($id);
        if (!$expense) return response()->json(['message' => 'Tidak ditemukan.'], 404);

        $data = $request->validate([
            'due_date' => 'nullable|date',
            'amount'   => 'nullable|numeric|min:0',
            'proof'    => 'nullable|string',
        ]);

        $dueDate = $data['due_date'] ?? now()->toDateString();

        if ($expense->is_revenue_sharing) {
            DB::table('expenses')->where('id', $id)->update([
                'status'     => 'paid',
                'updated_at' => now(),
            ]);
        }

        $existing = DB::table('expense_payments')
            ->where('expense_id', $id)
            ->where('due_date', $dueDate)
            ->first();

        $payload = [
            'status'          => 'paid',
            'paid_at'         => now(),
            'amount'          => $data['amount'] ?? $expense->amount,
            'proof'           => $data['proof'] ?? null,
            'paid_by'         => auth()->id(),
            'operator_status' => $expense->is_revenue_sharing ? 'pending' : null,
            'updated_at'      => now(),
        ];

        if ($existing) {
            DB::table('expense_payments')->where('id', $existing->id)->update($payload);
            $payId = $existing->id;
        } else {
            $payId = DB::table('expense_payments')->insertGetId(array_merge($payload, [
                'expense_id' => $id,
                'due_date'   => $dueDate,
                'created_at' => now(),
            ]));
        }

        return response()->json(['message' => 'Pembayaran berhasil dicatat. Status: Paid (Menunggu Konfirmasi Operator jika Revenue Sharing).', 'payment_id' => $payId]);
    }

    public function markUnpaid(Request $request, int $id): JsonResponse
    {
        $expense = DB::table('expenses')->find($id);
        if ($expense && $expense->is_revenue_sharing) {
            DB::table('expenses')->where('id', $id)->update(['status' => 'pending', 'updated_at' => now()]);
        }

        DB::table('expense_payments')
            ->where('expense_id', $id)
            ->update(['status' => 'unpaid', 'paid_at' => null, 'operator_status' => null, 'updated_at' => now()]);
            
        return response()->json(['message' => 'Status diubah ke Belum Bayar.']);
    }

    public function confirmPayment(Request $request, int $id): JsonResponse
    {
        $expense = DB::table('expenses')->find($id);
        if (!$expense) return response()->json(['message' => 'Tagihan expense tidak ditemukan.'], 404);

        DB::table('expenses')->where('id', $id)->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'updated_at'   => now(),
        ]);

        DB::table('expense_payments')->where('expense_id', $id)->update([
            'operator_status' => 'confirmed',
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => 'Pembayaran Revenue Sharing berhasil dikonfirmasi Operator. Status: CONFIRMED.']);
    }

    public function payments(Request $request): JsonResponse
    {
        $query = DB::table('expense_payments as ep')
            ->join('expenses as e', 'ep.expense_id', '=', 'e.id')
            ->leftJoin('users as u', 'ep.paid_by', '=', 'u.id')
            ->select('ep.*', 'e.name as expense_name', 'e.frequency', 'e.is_revenue_sharing',
                     'e.bank_name', 'e.bank_account', 'e.account_holder', 'u.name as paid_by_name')
            ->whereNull('e.deleted_at')
            ->orderByDesc('ep.due_date');

        if ($request->filled('status'))     $query->where('ep.status', $request->status);
        if ($request->filled('expense_id')) $query->where('ep.expense_id', $request->expense_id);

        return response()->json($query->paginate(50));
    }

    private function nextDueLabel(object $e): string
    {
        $freq = $e->frequency ?? 'monthly';
        $days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        return match($freq) {
            'daily'   => 'Setiap Hari',
            'weekly'  => 'Setiap ' . ($days[$e->reminder_day ?? 1] ?? '-'),
            'monthly' => 'Setiap Tgl ' . ($e->reminder_date ?? 1),
            default   => '-',
        };
    }

    private function syncRevenueSharingExpense()
    {
        $setting       = DB::table('admin_fee_settings')->first();
        $rate          = (float)($setting->rate ?? 0);
        $effectiveDate = $setting->effective_date ?? now()->startOfMonth()->toDateString();

        $totalRevenue = (float)PosOrder::whereIn('status', ['completed', 'paid', 'confirmed'])
            ->whereDate('created_at', '>=', $effectiveDate)
            ->sum('total_amount');

        $totalSharing = round($totalRevenue * ($rate / 100), 0);
        $desc = "Tagihan Revenue Sharing Admin Operator (" . $rate . "% Mulai " . $effectiveDate . ")";

        $expense = DB::table('expenses')
            ->where('is_revenue_sharing', true)
            ->first();

        if (!$expense) {
            DB::table('expenses')->insert([
                'name'               => $desc,
                'description'        => $desc,
                'amount'             => $totalSharing,
                'frequency'          => 'monthly',
                'is_revenue_sharing' => 1,
                'status'             => 'pending',
                'created_by'         => auth()->id(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } else {
            if ($expense->status === 'pending') {
                DB::table('expenses')->where('id', $expense->id)->update([
                    'name'        => $desc,
                    'description' => $desc,
                    'amount'      => $totalSharing,
                    'updated_at'  => now(),
                ]);
            }
        }
    }
}
