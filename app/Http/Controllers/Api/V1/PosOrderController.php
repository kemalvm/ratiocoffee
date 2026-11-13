<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosOrderController extends Controller
{
    public function __construct(private readonly PosService $posService) {}

    // GET /api/v1/pos/orders
    public function index(Request $request): JsonResponse
    {
        $orders = PosOrder::with(['lines.product', 'payment', 'cashier', 'customer'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->order_type, fn($q) => $q->where('order_type', $request->order_type))
            ->when($request->search, fn($q) => $q->where('order_number', 'like', "%{$request->search}%"))
            ->when($request->date, fn($q) => $q->whereDate('created_at', $request->date))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 100);

        return response()->json($orders);
    }

    // POST /api/v1/pos/orders
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_type'            => ['required', 'in:dine_in,takeaway,delivery'],
            'table_number'          => ['nullable', 'string'],
            'customer_id'           => ['nullable', 'exists:customers,id'],
            'discount_amount'       => ['nullable', 'numeric', 'min:0'],
            'tax_amount'            => ['nullable', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.qty'           => ['required', 'integer', 'min:1'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'         => ['nullable', 'string'],
            'items.*.temperature'   => ['nullable', 'string', 'in:hot,iced,cold'],
        ]);

        $order = $this->posService->createOrder($data, auth()->user());

        return response()->json(['message' => 'Order kasir dibuat.', 'order' => $order], 201);
    }


    // POST /api/v1/pos/orders/{order}/pay
    public function pay(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'method'           => ['required', 'in:cash,qris,bank_transfer,debit_card,credit_card,other'],
            'tendered'         => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string'],
        ]);

        try {
            $paidOrder = $this->posService->payOrder($order, $data);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Pembayaran Kasir Berhasil & Stok Terpotong Otomatis.',
            'order'   => $paidOrder,
        ]);
    }

    // GET /api/v1/pos/orders/{order}
    public function show(PosOrder $order): JsonResponse
    {
        return response()->json([
            'order' => $order->load('lines.product', 'payment', 'cashier', 'customer', 'outlet')
        ]);
    }

    // POST /api/v1/pos/orders/{order}/void
    public function void(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'reason'       => ['required', 'string'],
            'stock_action' => ['nullable', 'in:stock_in,waste'],
        ]);

        if (in_array($order->status, ['void', 'refunded'])) {
            return response()->json(['message' => "Pesanan sudah berstatus '{$order->status}'."], 422);
        }

        // Cek apakah pesanan sudah pernah dibayar dan stok telah dipotong
        $hasPaid = $order->payments()->where('status', 'completed')->exists();
        if ($hasPaid) {
            $stockAction = $data['stock_action'] ?? 'stock_in';
            $this->posService->reverseOrderStock($order, $stockAction, $data['reason']);
            $order->payments()->update(['status' => 'refunded']);
        }

        $order->update([
            'status'      => 'void',
            'void_reason' => $data['reason'],
            'voided_by'   => auth()->id(),
            'voided_at'   => now(),
        ]);

        \App\Models\ActivityLog::record(
            action:      'void_order',
            module:      'pos',
            entity:      'PosOrder',
            entityId:    $order->id,
            description: "Order #{$order->order_number} di-void: {$data['reason']}",
        );

        return response()->json(['message' => 'Order berhasil dibatalkan (void).', 'order' => $order]);
    }

    // POST /api/v1/pos/orders/{order}/refund
    public function refund(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'reason'        => ['required', 'string'],
            'notes'         => ['nullable', 'string'],
            'refund_method' => ['nullable', 'in:cash,original_method,store_credit'],
            'amount'        => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($order->status === 'refunded') {
            return response()->json(['message' => 'Pesanan ini sudah pernah di-refund sebelumnya.'], 422);
        }

        $refund = $this->posService->refundOrder($order, $data, auth()->user());

        return response()->json([
            'message' => 'Pesanan berhasil di-refund dan stok bahan telah dikembalikan.',
            'order'   => $order->fresh()->load('lines.product', 'payments', 'cashier', 'customer'),
            'refund'  => $refund,
        ]);
    }

    // POST /api/v1/pos/orders/{order}/hold
    public function hold(Request $request, PosOrder $order): JsonResponse
    {
        if (in_array($order->status, ['completed', 'void', 'refunded'])) {
            return response()->json(['message' => "Pesanan dengan status '{$order->status}' tidak dapat di-hold."], 422);
        }

        $order->update(['status' => 'held']);

        \App\Models\ActivityLog::record(
            action:      'hold_order',
            module:      'pos',
            entity:      'PosOrder',
            entityId:    $order->id,
            description: "Order #{$order->order_number} ditahan (held).",
        );

        return response()->json([
            'message' => 'Pesanan berhasil ditahan (held).',
            'order'   => $order->fresh()->load('lines.product', 'payment', 'cashier', 'customer'),
        ]);
    }

    // POST /api/v1/pos/orders/{order}/resume
    public function resume(Request $request, PosOrder $order): JsonResponse
    {
        if ($order->status !== 'held') {
            return response()->json(['message' => "Hanya pesanan berstatus 'held' yang dapat di-resume."], 422);
        }

        $hasPaid = $order->payments()->where('status', 'completed')->exists();
        $targetStatus = $hasPaid ? 'preparing' : 'pending';

        $order->update(['status' => $targetStatus]);

        \App\Models\ActivityLog::record(
            action:      'resume_order',
            module:      'pos',
            entity:      'PosOrder',
            entityId:    $order->id,
            description: "Order #{$order->order_number} dilanjutkan kembali (status: {$targetStatus}).",
        );

        return response()->json([
            'message' => "Pesanan berhasil dilanjutkan kembali ({$targetStatus}).",
            'order'   => $order->fresh()->load('lines.product', 'payment', 'cashier', 'customer'),
        ]);
    }

    // POST /api/v1/pos/orders/{order}/update-status
    public function updateStatus(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:preparing,processing,ready,completed,pending,confirmed'],
        ]);

        $statusMap = [
            'diproses' => 'preparing',
            'selesai'  => 'completed',
        ];

        $targetStatus = $statusMap[$data['status']] ?? $data['status'];
        $updateData   = ['status' => $targetStatus];

        if ($targetStatus === 'completed') {
            $updateData['completed_at'] = now();
        }

        $order->update($updateData);

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'order'   => $order->fresh()->load('lines.product', 'payment', 'cashier', 'customer'),
        ]);
    }

    // DELETE /api/v1/pos/orders/{order}
    public function destroy(Request $request, PosOrder $order): JsonResponse
    {
        $reason = $request->input('reason', 'Dibatalkan oleh pelanggan/karyawan');
        $hasPaid = $order->payments()->where('status', 'completed')->exists();
        if ($hasPaid && !in_array($order->status, ['void', 'refunded'])) {
            $stockAction = $request->input('stock_action', 'stock_in');
            $this->posService->reverseOrderStock($order, $stockAction, $reason);
            $order->payments()->update(['status' => 'refunded']);
        }

        $order->update([
            'status'      => 'void',
            'void_reason' => $reason,
            'voided_by'   => auth()->id(),
            'voided_at'   => now(),
        ]);

        $order->delete();

        return response()->json(['message' => 'Pesanan berhasil dibatalkan dan dihapus dari antrean.']);
    }

    // PUT /api/v1/pos/orders/{order}/update-type
    public function updateOrderType(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'order_type'          => ['nullable', 'in:dine_in,takeaway,delivery'],
            'table_number'        => ['nullable', 'string'],
            'items'               => ['nullable', 'array'],
            'items.*.id'          => ['required', 'exists:pos_order_lines,id'],
            'items.*.order_type'  => ['required', 'in:dine_in,takeaway,delivery'],
            'items.*.temperature' => ['nullable', 'in:hot,iced,cold'],
        ]);

        $defaultOrderType = $data['order_type'] ?? $order->order_type;

        $updatedOrder = $this->posService->updateOrderType(
            $order,
            $defaultOrderType,
            $data['table_number'] ?? null,
            $data['items'] ?? []
        );

        return response()->json([
            'message' => 'Tipe & opsi pesanan per produk berhasil diperbarui.',
            'order'   => $updatedOrder,
        ]);
    }

    // POST /api/v1/pos/orders/{order}/add-items
    public function addItems(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.qty'           => ['required', 'integer', 'min:1'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.notes'         => ['nullable', 'string'],
            'items.*.temperature'   => ['nullable', 'string', 'in:hot,iced,cold'],
        ]);

        try {
            $updatedOrder = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $order) {
                $subtotal = $order->subtotal;
                $totalCost = $order->total_cost;
                $newLines = [];

                foreach ($data['items'] as $item) {
                    $product = \App\Models\Product::findOrFail($item['product_id']);
                    
                    $unitPrice = $item['unit_price'] ?? $product->selling_price;
                    $costPrice = (float)($product->hpp ?: $product->cogs ?: 0);
                    
                    $lineSubtotal = $unitPrice * $item['qty'];
                    $lineTotalCost = $costPrice * $item['qty'];

                    $subtotal += $lineSubtotal;
                    $totalCost += $lineTotalCost;

                    $itemOrderType = $item['order_type'] ?? $order->order_type ?? 'dine_in';
                    $rawTemp       = strtolower($item['temperature'] ?? 'hot');
                    $isIced        = in_array($rawTemp, ['iced', 'cold', 'ice', 'dingin']);
                    $normTemp      = $isIced ? 'iced' : 'hot';

                    $tagType = ($itemOrderType === 'takeaway') ? '[Takeaway]' : '[Dine In]';
                    $tagTemp = $isIced ? '[Iced]' : '[Hot]';
                    $lineNotes = trim(($item['notes'] ?? '') . " {$tagType} {$tagTemp}");

                    $newLines[] = [
                        'product_id'   => $product->id,
                        'product_name' => $product->name,
                        'qty'          => $item['qty'],
                        'unit_price'   => $unitPrice,
                        'unit_cost'    => $costPrice,
                        'subtotal'     => $lineSubtotal,
                        'notes'        => $lineNotes,
                        'modifiers'    => [
                            'order_type'  => $itemOrderType,
                            'temperature' => $normTemp,
                        ],
                    ];
                }

                $order->lines()->createMany($newLines);

                // Jika pesanan sudah pernah dibayar atau sedang diproses, potong stok untuk menu tambahan baru ini
                $isPaidOrProcessing = $order->payments()->where('status', 'completed')->exists()
                    || in_array($order->status, ['confirmed', 'preparing', 'ready', 'completed']);
                if ($isPaidOrProcessing) {
                    $this->posService->deductNewItemsStock($order, $newLines);
                }

                $discountAmount = (float)$order->discount_amount;
                $taxAmount = (float)$order->tax_amount;

                $netSales = max(0, $subtotal - $discountAmount);
                $totalAmount = $netSales + $taxAmount;
                $grossProfit = max(0, $netSales - $totalCost);

                $order->update([
                    'subtotal'     => $subtotal,
                    'total_cost'   => $totalCost,
                    'total_amount' => $totalAmount,
                    'gross_profit' => $grossProfit,
                ]);

                return $order->fresh()->load('lines.product', 'payment', 'cashier', 'customer');
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menambahkan item: ' . $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Menu berhasil ditambahkan ke pesanan.',
            'order'   => $updatedOrder,
        ]);
    }

    // GET /api/v1/pos/orders/{order}/receipt
    public function receipt(Request $request, PosOrder $order): JsonResponse
    {
        $order->load('lines.product', 'payment', 'cashier', 'customer', 'outlet');

        $validated = $request->validate([
            'printer_type' => ['required', 'in:windows,network,linux'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'printer_host' => ['nullable', 'string', 'max:255'],
            'printer_port' => ['nullable', 'integer', 'between:1,65535'],
            'paper_width' => ['nullable', 'in:58mm,80mm'],
        ]);
        $printerType = $validated['printer_type'];
        $printerTarget = $printerType === 'network'
            ? ($validated['printer_host'] ?? '')
            : ($validated['printer_name'] ?? '');
        if ($printerTarget === '') {
            return response()->json([
                'success' => false,
                'fallback_html' => true,
                'message' => $printerType === 'network'
                    ? 'Host/IP printer network belum diisi.'
                    : 'Nama atau path printer server belum diisi.',
            ]);
        }

        $printerService = new \App\Services\ReceiptPrinterService(
            $printerType,
            $printerTarget,
            (int) ($validated['printer_port'] ?? 9100),
            $validated['paper_width'] ?? '58mm'
        );
        $printStatus = $printerService->printOrderReceipt($order);

        // Ambil info toko untuk fallback cetak browser
        $brand = \App\Models\BrandSetting::allAsArray();

        // Selalu return HTTP 200 — frontend menentukan fallback berdasarkan flag
        return response()->json([
            'success'       => $printStatus['success'],
            'fallback_html' => $printStatus['fallback_html'] ?? !$printStatus['success'],
            'message'       => $printStatus['message'],
            'order'         => $order,
            'store'         => [
                'name'    => $brand['brand_name'] ?? $brand['name'] ?? 'RATIO COFFEE',
                'address' => $brand['address'] ?? '',
                'phone'   => $brand['phone'] ?? $brand['telp'] ?? '',
                'website' => $brand['website'] ?? 'www.ratiocoffee.my.id',
            ],
        ]);
    }
}
