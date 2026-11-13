<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Illuminate\Support\Facades\DB;

class PurchasingService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Create a new Purchase Request (PR)
     */
    public function createRequest(array $data, int $userId): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $userId) {
            $lastId = PurchaseRequest::withTrashed()->max('id') ?? 0;
            $prNumber = 'PR-' . date('Ym') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            $pr = PurchaseRequest::create([
                'outlet_id'    => $data['outlet_id'],
                'warehouse_id' => $data['warehouse_id'],
                'requested_by' => $userId,
                'pr_number'    => $prNumber,
                'status'       => 'submitted',
                'needed_by'    => $data['needed_by'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'submitted_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $pr->lines()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'qty_requested' => $item['qty_requested'],
                    'notes'         => $item['notes'] ?? null,
                ]);
            }

            ActivityLog::record(
                action:      'create_purchase_request',
                module:      'purchasing',
                entity:      'PurchaseRequest',
                entityId:    $pr->id,
                description: "Created PR {$pr->pr_number}",
            );

            return $pr->load('lines.ingredient', 'requester', 'outlet', 'warehouse');
        });
    }

    /**
     * Approve Purchase Request
     */
    public function approveRequest(PurchaseRequest $pr, int $approverId, ?array $approvedLines = null): PurchaseRequest
    {
        return DB::transaction(function () use ($pr, $approverId, $approvedLines) {
            if ($approvedLines) {
                foreach ($approvedLines as $lineId => $qtyApproved) {
                    PurchaseRequestLine::where('id', $lineId)
                        ->where('purchase_request_id', $pr->id)
                        ->update(['qty_approved' => $qtyApproved]);
                }
            } else {
                foreach ($pr->lines as $line) {
                    $line->update(['qty_approved' => $line->qty_requested]);
                }
            }

            $pr->update([
                'status'      => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            ActivityLog::record(
                action:      'approve_purchase_request',
                module:      'purchasing',
                entity:      'PurchaseRequest',
                entityId:    $pr->id,
                description: "Approved PR {$pr->pr_number}",
            );

            return $pr->fresh()->load('lines.ingredient', 'approver');
        });
    }

    /**
     * Create Purchase Order (PO)
     */
    public function createOrder(array $data, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $lastId = PurchaseOrder::withTrashed()->max('id') ?? 0;
            $poNumber = 'PO-' . date('Ym') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += ($item['qty_ordered'] * $item['unit_cost']);
            }

            $taxAmount      = $data['tax_amount'] ?? 0;
            $discountAmount = $data['discount_amount'] ?? 0;
            $shippingCost   = $data['shipping_cost'] ?? 0;
            $totalAmount    = $subtotal + $taxAmount + $shippingCost - $discountAmount;

            $po = PurchaseOrder::create([
                'outlet_id'           => $data['outlet_id'],
                'warehouse_id'        => $data['warehouse_id'],
                'supplier_id'         => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'created_by'          => $userId,
                'po_number'           => $poNumber,
                'status'              => 'submitted',
                'order_date'          => $data['order_date'] ?? now(),
                'expected_date'       => $data['expected_date'] ?? null,
                'subtotal'            => $subtotal,
                'tax_amount'          => $taxAmount,
                'discount_amount'     => $discountAmount,
                'shipping_cost'       => $shippingCost,
                'total_amount'        => $totalAmount,
                'notes'               => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $po->lines()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'qty_ordered'   => $item['qty_ordered'],
                    'unit_cost'     => $item['unit_cost'],
                    'batch_number'  => $item['batch_number'] ?? null,
                    'expiry_date'   => $item['expiry_date'] ?? null,
                    'notes'         => $item['notes'] ?? null,
                ]);
            }

            if (!empty($data['purchase_request_id'])) {
                PurchaseRequest::where('id', $data['purchase_request_id'])->update(['status' => 'converted']);
            }

            ActivityLog::record(
                action:      'create_purchase_order',
                module:      'purchasing',
                entity:      'PurchaseOrder',
                entityId:    $po->id,
                description: "Created PO {$po->po_number} to supplier ID {$po->supplier_id}",
            );

            return $po->load('lines.ingredient', 'supplier', 'outlet', 'warehouse');
        });
    }

    /**
     * Approve Purchase Order
     */
    public function approveOrder(PurchaseOrder $po, int $approverId): PurchaseOrder
    {
        $po->update([
            'status'      => 'approved',
            'approved_by' => $approverId,
        ]);

        ActivityLog::record(
            action:      'approve_purchase_order',
            module:      'purchasing',
            entity:      'PurchaseOrder',
            entityId:    $po->id,
            description: "Approved PO {$po->po_number}",
        );

        return $po->fresh();
    }

    /**
     * Process Goods Receipt (Receive Items against PO & update Inventory)
     */
    public function receiveGoods(PurchaseOrder $po, array $receiveItems, int $userId, ?string $notes = null): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $receiveItems, $userId, $notes) {
            $lastId = GoodsReceipt::withTrashed()->max('id') ?? 0;
            $grNumber = 'GR-' . date('Ym') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            $gr = GoodsReceipt::create([
                'purchase_order_id' => $po->id,
                'warehouse_id'      => $po->warehouse_id,
                'received_by'       => $userId,
                'gr_number'         => $grNumber,
                'receipt_date'      => now(),
                'status'            => 'confirmed',
                'notes'             => $notes,
            ]);

            $allCompleted = true;

            foreach ($receiveItems as $item) {
                $poLine = PurchaseOrderLine::findOrFail($item['po_line_id']);
                $qtyReceivedNow = (float)$item['qty_received'];

                if ($qtyReceivedNow <= 0) continue;

                // 1. Create Goods Receipt Line
                $gr->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'ingredient_id'          => $poLine->ingredient_id,
                    'qty_received'           => $qtyReceivedNow,
                    'unit_cost'              => $poLine->unit_cost,
                    'batch_number'           => $item['batch_number'] ?? $poLine->batch_number,
                    'expiry_date'            => $item['expiry_date'] ?? $poLine->expiry_date,
                ]);

                // 2. Update PO Line received quantity
                $newQtyReceivedTotal = (float)$poLine->qty_received + $qtyReceivedNow;
                $poLine->update(['qty_received' => $newQtyReceivedTotal]);

                if ($newQtyReceivedTotal < (float)$poLine->qty_ordered) {
                    $allCompleted = false;
                }

                // 3. Call Inventory Engine to perform Stock In & Update FIFO/MAVG Costs!
                $this->inventoryService->stockIn([
                    'ingredient_id'  => $poLine->ingredient_id,
                    'warehouse_id'   => $po->warehouse_id,
                    'qty'            => $qtyReceivedNow,
                    'unit_cost'      => $poLine->unit_cost,
                    'batch_number'   => $item['batch_number'] ?? $poLine->batch_number,
                    'expiry_date'    => $item['expiry_date'] ?? $poLine->expiry_date,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id'   => $gr->id,
                    'notes'          => "Goods Receipt {$gr->gr_number} (PO: {$po->po_number})",
                ]);
            }

            // Update PO Status
            $newPoStatus = $allCompleted ? 'received' : 'partial';
            $po->update(['status' => $newPoStatus]);

            ActivityLog::record(
                action:      'receive_goods',
                module:      'purchasing',
                entity:      'GoodsReceipt',
                entityId:    $gr->id,
                description: "Received goods for PO {$po->po_number} via GR {$gr->gr_number}",
            );

            return $gr->load('lines.ingredient', 'purchaseOrder', 'warehouse');
        });
    }
}
