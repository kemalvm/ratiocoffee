<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Services\PurchasingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(private readonly PurchasingService $service) {}

    // GET /api/v1/purchase/requests
    public function index(Request $request): JsonResponse
    {
        $prs = PurchaseRequest::with(['outlet', 'warehouse', 'requester', 'approver', 'lines.ingredient.unit'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('pr_number', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($prs);
    }

    // POST /api/v1/purchase/requests
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'outlet_id'               => ['required', 'exists:outlets,id'],
            'warehouse_id'            => ['required', 'exists:warehouses,id'],
            'needed_by'               => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.ingredient_id'   => ['required', 'exists:ingredients,id'],
            'items.*.qty_requested'   => ['required', 'numeric', 'min:0.001'],
            'items.*.notes'           => ['nullable', 'string'],
        ]);

        $pr = $this->service->createRequest($data, auth()->id());

        return response()->json(['message' => 'Pengajuan Pembelian (PR) berhasil dibuat.', 'pr' => $pr], 201);
    }

    // GET /api/v1/purchase/requests/{request}
    public function show(PurchaseRequest $request): JsonResponse
    {
        return response()->json(['pr' => $request->load('outlet', 'warehouse', 'requester', 'approver', 'lines.ingredient.unit')]);
    }

    // POST /api/v1/purchase/requests/{request}/approve
    public function approve(Request $request, PurchaseRequest $pr): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['nullable', 'array'],
        ]);

        $prApproved = $this->service->approveRequest($pr, auth()->id(), $data['lines'] ?? null);

        return response()->json(['message' => 'PR berhasil disetujui.', 'pr' => $prApproved]);
    }

    // DELETE /api/v1/purchase/requests/{request}
    public function destroy(PurchaseRequest $pr): JsonResponse
    {
        if ($pr->status !== 'draft' && $pr->status !== 'submitted') {
            return response()->json(['message' => 'PR yang sudah disetujui/diproses tidak dapat dihapus.'], 422);
        }

        $pr->delete();
        return response()->json(['message' => 'PR berhasil dihapus.']);
    }
}
