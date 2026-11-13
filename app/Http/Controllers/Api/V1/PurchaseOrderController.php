<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchasingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchasingService $service) {}

    // GET /api/v1/purchase/orders
    public function index(Request $request): JsonResponse
    {
        $pos = PurchaseOrder::with(['outlet', 'warehouse', 'supplier', 'creator', 'approver', 'lines.ingredient.unit'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->search, fn($q) => $q->where('po_number', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($pos);
    }

    // POST /api/v1/purchase/orders
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'outlet_id'               => ['required', 'exists:outlets,id'],
            'warehouse_id'            => ['required', 'exists:warehouses,id'],
            'supplier_id'             => ['required', 'exists:suppliers,id'],
            'purchase_request_id'     => ['nullable', 'exists:purchase_requests,id'],
            'order_date'              => ['nullable', 'date'],
            'expected_date'           => ['nullable', 'date'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'shipping_cost'           => ['nullable', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.ingredient_id'   => ['required', 'exists:ingredients,id'],
            'items.*.qty_ordered'     => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost'       => ['required', 'numeric', 'min:0'],
            'items.*.batch_number'    => ['nullable', 'string'],
            'items.*.expiry_date'     => ['nullable', 'date'],
            'items.*.notes'           => ['nullable', 'string'],
        ]);

        $po = $this->service->createOrder($data, auth()->id());

        return response()->json(['message' => 'Pesanan Pembelian (PO) berhasil dibuat.', 'po' => $po], 201);
    }

    // GET /api/v1/purchase/orders/{order}
    public function show(PurchaseOrder $order): JsonResponse
    {
        return response()->json([
            'po' => $order->load('outlet', 'warehouse', 'supplier', 'creator', 'approver', 'lines.ingredient.unit', 'goodsReceipts.lines')
        ]);
    }

    // POST /api/v1/purchase/orders/{order}/approve
    public function approve(PurchaseOrder $order): JsonResponse
    {
        $poApproved = $this->service->approveOrder($order, auth()->id());
        return response()->json(['message' => 'PO berhasil disetujui.', 'po' => $poApproved]);
    }

    // POST /api/v1/purchase/orders/{order}/receive
    public function receive(Request $request, PurchaseOrder $order): JsonResponse
    {
        $data = $request->validate([
            'notes'                   => ['nullable', 'string'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.po_line_id'      => ['required', 'exists:purchase_order_lines,id'],
            'items.*.qty_received'    => ['required', 'numeric', 'min:0'],
            'items.*.batch_number'    => ['nullable', 'string'],
            'items.*.expiry_date'     => ['nullable', 'date'],
        ]);

        try {
            $gr = $this->service->receiveGoods($order, $data['items'], auth()->id(), $data['notes'] ?? null);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Penerimaan barang (Goods Receipt) berhasil dicatat & stok telah diupdate.',
            'gr'      => $gr,
        ], 201);
    }

    // DELETE /api/v1/purchase/orders/{order}
    public function destroy(PurchaseOrder $order): JsonResponse
    {
        if (in_array($order->status, ['received', 'partial', 'invoiced', 'paid'])) {
            return response()->json(['message' => 'PO yang sudah diproses barang/pembayaran tidak dapat dihapus.'], 422);
        }

        $order->delete();
        return response()->json(['message' => 'PO berhasil dihapus.']);
    }
}
