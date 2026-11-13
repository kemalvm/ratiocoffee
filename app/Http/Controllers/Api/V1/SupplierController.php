<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    // ─── GET /api/v1/suppliers ────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Supplier::withCount('ingredients')
            ->with(['ingredients' => function ($q) {
                $q->select('id', 'name', 'code', 'default_supplier_id', 'last_purchase_price', 'average_cost', 'net_content', 'current_stock', 'unit_id')
                  ->with('unit:id,name,symbol');
            }])
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('code', 'like', "%{$request->search}%")
                   ->orWhere('products_summary', 'like', "%{$request->search}%")
                   ->orWhere('contact_person', 'like', "%{$request->search}%")
                   ->orWhere('phone', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->status,   fn($q) => $q->where('status',   $request->status))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->orderBy('name');

        if ($request->has('per_page') && (int)$request->per_page > 0) {
            $suppliers = $query->paginate((int)$request->per_page);
        } else {
            $suppliers = $query->get();
        }

        return response()->json($suppliers);
    }

    // ─── GET /api/v1/suppliers/categories ─────────────────────────────────
    public function categories(): JsonResponse
    {
        return response()->json(\App\Models\Supplier::categories());
    }

    // ─── POST /api/v1/suppliers ───────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:200'],
            'category'              => ['nullable', 'string'],
            'products_summary'      => ['nullable', 'string'],
            'contact_person'        => ['nullable', 'string'],
            'phone'                 => ['nullable', 'string'],
            'email'                 => ['nullable', 'email'],
            'website'               => ['nullable', 'url'],
            'address'               => ['nullable', 'string'],
            'tax_id'                => ['nullable', 'string'],
            'bank_name'             => ['nullable', 'string'],
            'bank_account'          => ['nullable', 'string'],
            'bank_account_name'     => ['nullable', 'string'],
            'payment_term_days'     => ['nullable', 'integer', 'min:0'],
            'next_payment_due_date' => ['nullable', 'date'],
            'payment_due_dates'     => ['nullable', 'array'],
            'payment_due_dates.*'   => ['nullable', 'string'],
            'payment_method'        => ['nullable', Rule::in(['cash','credit','transfer','mixed'])],
            'credit_limit'          => ['nullable', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string'],
            'status'                => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if (($data['payment_method'] ?? 'cash') === 'cash') {
            $data['next_payment_due_date'] = null;
            $data['payment_due_dates']     = null;
            $data['payment_term_days']     = 0;
        } else {
            if (!empty($data['payment_due_dates']) && is_array($data['payment_due_dates'])) {
                $cleanDates = array_values(array_filter($data['payment_due_dates']));
                sort($cleanDates);
                $data['payment_due_dates']     = $cleanDates;
                $data['next_payment_due_date'] = $cleanDates[0] ?? $data['next_payment_due_date'] ?? null;
            }
        }

        $data['code'] = $this->generateCode();

        $supplier = Supplier::create($data);

        ActivityLog::record(
            action:      'create_supplier',
            module:      'inventory',
            entity:      'Supplier',
            entityId:    $supplier->id,
            newData:     $data,
            description: "Created supplier: {$supplier->name}",
        );

        return response()->json(['message' => 'Supplier berhasil dibuat.', 'supplier' => $supplier], 201);
    }

    // ─── GET /api/v1/suppliers/{supplier} ────────────────────────────────

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['supplier' => $supplier->load('ingredients.unit')]);
    }

    // ─── PATCH /api/v1/suppliers/{supplier} ──────────────────────────────

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $old  = $supplier->toArray();
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:200'],
            'category'              => ['nullable', 'string'],
            'products_summary'      => ['nullable', 'string'],
            'contact_person'        => ['nullable', 'string'],
            'phone'                 => ['nullable', 'string'],
            'email'                 => ['nullable', 'email'],
            'website'               => ['nullable', 'url'],
            'address'               => ['nullable', 'string'],
            'tax_id'                => ['nullable', 'string'],
            'bank_name'             => ['nullable', 'string'],
            'bank_account'          => ['nullable', 'string'],
            'bank_account_name'     => ['nullable', 'string'],
            'payment_term_days'     => ['nullable', 'integer', 'min:0'],
            'next_payment_due_date' => ['nullable', 'date'],
            'payment_due_dates'     => ['nullable', 'array'],
            'payment_due_dates.*'   => ['nullable', 'string'],
            'payment_method'        => ['nullable', Rule::in(['cash','credit','transfer','mixed'])],
            'credit_limit'          => ['nullable', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string'],
            'status'                => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $pm = $data['payment_method'] ?? $supplier->payment_method;
        if ($pm === 'cash') {
            $data['next_payment_due_date'] = null;
            $data['payment_due_dates']     = null;
            $data['payment_term_days']     = 0;
        } else {
            if (array_key_exists('payment_due_dates', $data) && is_array($data['payment_due_dates'])) {
                $cleanDates = array_values(array_filter($data['payment_due_dates']));
                sort($cleanDates);
                $data['payment_due_dates']     = $cleanDates;
                $data['next_payment_due_date'] = $cleanDates[0] ?? null;
            }
        }

        $supplier->update($data);

        ActivityLog::record(
            action:      'update_supplier',
            module:      'inventory',
            entity:      'Supplier',
            entityId:    $supplier->id,
            oldData:     $old,
            newData:     $data,
        );

        return response()->json(['message' => 'Supplier berhasil diperbarui.', 'supplier' => $supplier->fresh()]);
    }

    // ─── DELETE /api/v1/suppliers/{supplier} ──────────────────────────────

    public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json(['message' => 'Supplier tidak dapat dihapus karena memiliki riwayat pembelian.'], 422);
        }

        ActivityLog::record(
            action:      'delete_supplier',
            module:      'inventory',
            entity:      'Supplier',
            entityId:    $supplier->id,
            oldData:     $supplier->toArray(),
        );

        $supplier->delete();
        return response()->json(['message' => 'Supplier berhasil dihapus.']);
    }

    // ─── POST /api/v1/suppliers/{supplier}/extend-due-date ───────────────

    public function extendDueDate(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'new_due_date' => ['required', 'date'],
            'reason'       => ['nullable', 'string', 'max:500'],
        ]);

        $history = $supplier->payment_due_dates ?? [];
        if (!is_array($history)) {
            $history = json_decode($history, true) ?? [];
        }

        $history[] = [
            'previous_due_date' => $supplier->next_payment_due_date,
            'new_due_date'      => $data['new_due_date'],
            'reason'            => $data['reason'] ?? 'Perpanjangan tenggat waktu tempo',
            'extended_at'       => now()->toDateTimeString(),
            'extended_by'       => auth()->user()?->name ?? 'Management',
        ];

        $supplier->update([
            'payment_method'        => $supplier->payment_method === 'cash' ? 'credit' : $supplier->payment_method,
            'next_payment_due_date' => $data['new_due_date'],
            'payment_due_dates'     => $history,
        ]);

        ActivityLog::record(
            action:      'extend_supplier_due_date',
            module:      'inventory',
            entity:      'Supplier',
            entityId:    $supplier->id,
            newData:     $data,
            description: "Extended due date for supplier {$supplier->name} to {$data['new_due_date']}",
        );

        return response()->json([
            'message'  => 'Tenggat waktu supplier berhasil diperpanjang.',
            'supplier' => $supplier->fresh(),
        ]);
    }

    private function generateCode(): string
    {
        $last = Supplier::withTrashed()->max('id') ?? 0;
        return 'SUP-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}
