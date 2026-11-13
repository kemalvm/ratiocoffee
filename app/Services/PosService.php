<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;

class PosService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Create a new POS transaction order
     */
    public function createOrder(array $data, User $cashier): PosOrder
    {
        return DB::transaction(function () use ($data, $cashier) {
            $datePrefix = 'POS-' . date('Ymd') . '-';
            $latestOrder = PosOrder::withTrashed()
                ->where('order_number', 'like', $datePrefix . '%')
                ->orderByDesc('id')
                ->first();

            $nextSeq = 1;
            if ($latestOrder && preg_match('/-(\d+)$/', $latestOrder->order_number, $matches)) {
                $nextSeq = ((int)$matches[1]) + 1;
            } else {
                $countToday = PosOrder::withTrashed()->whereDate('created_at', now()->toDateString())->count();
                $nextSeq = $countToday + 1;
            }

            $orderNum = $datePrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            $queueNum = 'A' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);

            while (PosOrder::withTrashed()->where('order_number', $orderNum)->exists()) {
                $nextSeq++;
                $orderNum = $datePrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
                $queueNum = 'A' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
            }

            $subtotal  = 0;
            $totalCost = 0;

            $taxSettings = \App\Models\TaxSetting::getSettings();
            $isTaxEnabled = (bool)$taxSettings->is_enabled;
            $isTaxChargedToHpp = $isTaxEnabled && $taxSettings->tax_type === 'charged_to_hpp';
            $taxRate = $isTaxEnabled ? (float)$taxSettings->tax_rate : 0.0;

            // Retrieve global Cup, Food Packaging, and Es Batu costs from inventory
            $cup = Ingredient::where('name', 'like', '%Cup%')->first();
            $cupCost = $cup ? (float)($cup->recipe_unit_cost ?: $cup->average_cost ?: 100.0) : 100.0;

            $foodPkg = Ingredient::where('name', 'like', '%Packaging Makanan%')
                ->orWhere('name', 'like', '%Paper Box%')
                ->orWhere('name', 'like', '%Kotak%')
                ->first();
            $foodPkgCost = $foodPkg ? (float)($foodPkg->recipe_unit_cost ?: $foodPkg->average_cost ?: 500.0) : 500.0;

            $ice = Ingredient::where('name', 'like', '%Es Batu%')
                ->orWhere('name', 'like', '%Ice%')
                ->first();
            $iceCost = $ice ? (float)($ice->recipe_unit_cost ?: $ice->average_cost ?: 50.0) : 50.0;

            $lineData = [];
            foreach ($data['items'] as $item) {
                $product = Product::with('category', 'recipe.ingredients')->findOrFail($item['product_id']);
                $qty = (int)$item['qty'];
                $unitPrice = (float)($item['unit_price'] ?? $product->selling_price);

                // Calculate base recipe cost (excluding any packaging/ice from old recipe structures)
                $lineCost = 0.0;
                if ($product->is_bundling) {
                    // Bundle HPP is the trusted sum of all child-product HPP values.
                    $lineCost = (float)($product->hpp ?? 0);
                } elseif ($product->recipe && $product->recipe->ingredients->isNotEmpty()) {
                    $recipeIngredientCost = 0.0;
                    foreach ($product->recipe->ingredients as $recipeIng) {
                        if ($recipeIng->type === 'packaging' || $recipeIng->type === 'ice') {
                            continue;
                        }
                        $recipeIngredientCost += (float)$recipeIng->qty * (float)$recipeIng->unit_cost;
                    }
                    $recipeYield = max(1, (float)$product->recipe->yield_qty);
                    $lineCost = (
                        $recipeIngredientCost
                        + (float)$product->recipe->packaging_cost
                        + (float)$product->recipe->operational_cost
                    ) / $recipeYield;
                } else {
                    $lineCost = (float)($product->hpp ?? 0);
                }

                // Per-item order type & temperature
                $itemOrderType = $item['order_type'] ?? $data['order_type'] ?? 'dine_in';
                $isTakeaway    = ($itemOrderType === 'takeaway');
                $rawTemp       = strtolower($item['temperature'] ?? 'hot');
                $isIced        = in_array($rawTemp, ['iced', 'cold', 'ice', 'dingin']);
                $normTemp      = $isIced ? 'iced' : 'hot';

                $catName = strtolower($product->category?->name ?? '');
                $isBeverage = str_contains($catName, 'beverage') || str_contains($catName, 'minuman') || str_contains($catName, 'kopi') || str_contains($catName, 'coffee') || str_contains($catName, 'tea') || str_contains($catName, 'teh') || str_contains($catName, 'drink');

                if ($isTakeaway) {
                    if ($isBeverage) {
                        $lineCost += $cupCost;
                    } else {
                        $lineCost += $foodPkgCost;
                    }
                }
                if ($isIced) {
                    $lineCost += $iceCost;
                }

                if ($isTaxChargedToHpp && $taxRate > 0) {
                    $lineCost += $unitPrice * ($taxRate / 100);
                }

                $unitCost = $lineCost;
                $itemSubtotal = $unitPrice * $qty;
                $subtotal  += $itemSubtotal;
                $totalCost += ($unitCost * $qty);

                $lineNotes = $item['notes'] ?? '';
                $tagType = $isTakeaway ? '[Takeaway]' : '[Dine In]';
                $tagTemp = $isIced ? '[Iced]' : '[Hot]';
                $lineNotes = trim("{$lineNotes} {$tagType} {$tagTemp}");

                $lineData[] = [
                    'product_id'   => $product->id,
                    'variant_id'   => $item['variant_id'] ?? null,
                    'product_name' => $product->name,
                    'unit_price'   => $unitPrice,
                    'unit_cost'    => $unitCost,
                    'qty'          => $qty,
                    'subtotal'     => $itemSubtotal,
                    'notes'        => $lineNotes,
                    'modifiers'    => [
                        'order_type'  => $itemOrderType,
                        'temperature' => $normTemp,
                    ],
                ];
            }

            $discountAmount = (float)($data['discount_amount'] ?? 0);
            $taxAmount      = (!$isTaxEnabled || $isTaxChargedToHpp) ? 0.0 : (float)($data['tax_amount'] ?? 0);

            // 1. Net Sales adalah pendapatan murni penjualan produk setelah dipotong diskon
            $netRevenue     = max(0, $subtotal - $discountAmount);

            // 2. Total tagihan kasir (yang dibayar pelanggan) = Net Sales + Pajak
            $totalAmount    = $netRevenue + $taxAmount;

            // 3. Gross Profit murni: Net Sales dikurangi Total HPP (Cost)
            // Pajak tidak boleh dihitung sebagai keuntungan usaha
            $grossProfit    = $netRevenue - $totalCost;

            $outletId = $cashier->outlet_id 
                ?? \App\Models\Outlet::where('active', true)->first()?->id 
                ?? \App\Models\Outlet::first()?->id 
                ?? 1;

            $order = PosOrder::create([
                'outlet_id'       => $outletId,
                'cashier_id'      => $cashier->id,
                'customer_id'     => $data['customer_id'] ?? null,
                'order_number'    => $orderNum,
                'queue_number'    => $queueNum,
                'order_type'      => $data['order_type'] ?? 'dine_in',
                'table_number'    => $data['table_number'] ?? null,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount'      => $taxAmount,
                'total_amount'    => $totalAmount,
                'total_cost'      => $totalCost,
                'gross_profit'    => $grossProfit,
                'status'          => 'preparing',
                'notes'           => $data['notes'] ?? null,
            ]);

            foreach ($lineData as $line) {
                $order->lines()->create($line);
            }

            return $order->load('lines.product', 'cashier', 'customer');
        });
    }

    /**
     * Complete POS Order Payment & Automatically deduct ingredient stocks!
     */
    public function payOrder(PosOrder $order, array $paymentData): PosOrder
    {
        return DB::transaction(function () use ($order, $paymentData) {
            $method   = $paymentData['method'] ?? 'cash';
            $tendered = (float)($paymentData['tendered'] ?? $order->total_amount);
            $change   = max(0, $tendered - (float)$order->total_amount);

            PosPayment::create([
                'order_id'         => $order->id,
                'method'           => $method,
                'amount'           => $order->total_amount,
                'tendered'         => $tendered,
                'change_amount'    => $change,
                'reference_number' => $paymentData['reference_number'] ?? null,
                'status'           => 'completed',
            ]);

            // Keep status as 'preparing' (Diproses Barista) unless explicitly changed by Barista
            if ($order->status === 'pending') {
                $order->update([
                    'status' => 'preparing',
                ]);
            }

            // AUTOMATIC INVENTORY DEDUCTION (Stock Out per ingredient recipe + operational cup/packaging/ice)
            $warehouse = Warehouse::where('outlet_id', $order->outlet_id)->where('is_default', true)->first()
                ?? Warehouse::where('outlet_id', $order->outlet_id)->first()
                ?? Warehouse::where('is_default', true)->first()
                ?? Warehouse::first();

            if ($warehouse) {
                // Find global Cup, Food Packaging, and Es Batu ingredients
                $cup = Ingredient::where('name', 'like', '%Cup%')->first();
                $foodPkg = Ingredient::where('name', 'like', '%Packaging Makanan%')
                    ->orWhere('name', 'like', '%Paper Box%')
                    ->orWhere('name', 'like', '%Kotak%')
                    ->first();
                $ice = Ingredient::where('name', 'like', '%Es Batu%')
                    ->orWhere('name', 'like', '%Ice%')
                    ->first();

                $totalCups = 0;
                $totalFoodPkg = 0;
                $totalIce = 0;

                foreach ($order->lines as $line) {
                    $product = Product::with('category', 'recipe.ingredients')->find($line->product_id);
                    if ($product && $product->recipe && $product->recipe->ingredients) {
                        foreach ($product->recipe->ingredients as $recipeIng) {
                            if ($recipeIng->type === 'packaging' || $recipeIng->type === 'ice') {
                                continue;
                            }

                            $totalDoseNeeded = (float)$recipeIng->qty * $line->qty;
                            $ingModel = $recipeIng->ingredient ?? Ingredient::find($recipeIng->ingredient_id);

                            if ($ingModel) {
                                $stockQtyToDeduct = $ingModel->convertRecipeQtyToMainStockQty($totalDoseNeeded);
                                try {
                                    $this->inventoryService->stockOut([
                                        'ingredient_id'  => $ingModel->id,
                                        'warehouse_id'   => $warehouse->id,
                                        'qty'            => $stockQtyToDeduct,
                                        'reference_type' => PosOrder::class,
                                        'reference_id'   => $order->id,
                                        'notes'          => "POS Deduct Order #{$order->order_number} ({$line->product_name} x{$line->qty})",
                                    ]);
                                } catch (\Exception $e) {
                                    ActivityLog::record(
                                        action:      'pos_stock_out_warning',
                                        module:      'pos',
                                        entity:      'PosOrder',
                                        entityId:    $order->id,
                                        description: "Warning stock deduction for POS #{$order->order_number} ({$ingModel->name}): " . $e->getMessage(),
                                    );
                                }
                            }
                        }
                    }

                    // Determine per-item order type
                    $mods = is_array($line->modifiers) ? $line->modifiers : (is_string($line->modifiers) ? json_decode($line->modifiers, true) : []);
                    $lineOrderType = $mods['order_type'] ?? (str_contains($line->notes ?? '', '[Takeaway]') ? 'takeaway' : $order->order_type);

                    if ($lineOrderType === 'takeaway') {
                        $catName = strtolower($product?->category?->name ?? '');
                        $isBeverage = str_contains($catName, 'beverage') || str_contains($catName, 'minuman') || str_contains($catName, 'kopi') || str_contains($catName, 'coffee') || str_contains($catName, 'tea') || str_contains($catName, 'teh') || str_contains($catName, 'drink') || str_contains(strtolower($product?->name ?? ''), 'kopi') || str_contains(strtolower($product?->name ?? ''), 'coffee') || str_contains(strtolower($product?->name ?? ''), 'tea') || str_contains(strtolower($product?->name ?? ''), 'latte');
                        if ($isBeverage) {
                            $totalCups += $line->qty;
                        } else {
                            $totalFoodPkg += $line->qty;
                        }
                    }

                    $isIced = str_contains(strtolower($line->product_name), 'ice')
                        || str_contains(strtolower($line->notes ?? ''), 'ice')
                        || str_contains(strtolower($line->notes ?? ''), 'iced')
                        || (($mods['temperature'] ?? '') === 'iced');
                    if ($isIced) {
                        $totalIce += $line->qty;
                    }
                }

                // Deduct operational Cup
                if ($totalCups > 0 && $cup) {
                    try {
                        $cupStockDeduct = $cup->convertRecipeQtyToMainStockQty($totalCups);
                        $this->inventoryService->stockOut([
                            'ingredient_id'  => $cup->id,
                            'warehouse_id'   => $warehouse->id,
                            'qty'            => $cupStockDeduct,
                            'reference_type' => PosOrder::class,
                            'reference_id'   => $order->id,
                            'notes'          => "POS Auto Cup Deduct #{$order->order_number} (Takeaway Beverages x{$totalCups})",
                        ]);
                    } catch (\Exception $e) {
                        ActivityLog::record(
                            action:      'pos_stock_out_warning',
                            module:      'pos',
                            entity:      'PosOrder',
                            entityId:    $order->id,
                            description: "Warning stock deduction for POS #{$order->order_number} (Cup): " . $e->getMessage(),
                        );
                    }
                }

                // Deduct operational Food Packaging
                if ($totalFoodPkg > 0 && $foodPkg) {
                    try {
                        $foodPkgStockDeduct = $foodPkg->convertRecipeQtyToMainStockQty($totalFoodPkg);
                        $this->inventoryService->stockOut([
                            'ingredient_id'  => $foodPkg->id,
                            'warehouse_id'   => $warehouse->id,
                            'qty'            => $foodPkgStockDeduct,
                            'reference_type' => PosOrder::class,
                            'reference_id'   => $order->id,
                            'notes'          => "POS Auto Food Packaging Deduct #{$order->order_number} (Takeaway Food x{$totalFoodPkg})",
                        ]);
                    } catch (\Exception $e) {
                        ActivityLog::record(
                            action:      'pos_stock_out_warning',
                            module:      'pos',
                            entity:      'PosOrder',
                            entityId:    $order->id,
                            description: "Warning stock deduction for POS #{$order->order_number} (Food Packaging): " . $e->getMessage(),
                        );
                    }
                }

                // Deduct operational Es Batu
                if ($totalIce > 0 && $ice) {
                    try {
                        $this->inventoryService->stockOut([
                            'ingredient_id'  => $ice->id,
                            'warehouse_id'   => $warehouse->id,
                            'qty'            => $totalIce,
                            'reference_type' => PosOrder::class,
                            'reference_id'   => $order->id,
                            'notes'          => "POS Auto Ice Deduct #{$order->order_number} (Iced option)",
                        ]);
                    } catch (\Exception $e) {
                        ActivityLog::record(
                            action:      'pos_stock_out_warning',
                            module:      'pos',
                            entity:      'PosOrder',
                            entityId:    $order->id,
                            description: "Warning stock deduction for POS #{$order->order_number} (Es Batu): " . $e->getMessage(),
                        );
                    }
                }
            }

            ActivityLog::record(
                action:      'pos_checkout',
                module:      'pos',
                entity:      'PosOrder',
                entityId:    $order->id,
                description: "POS Paid #{$order->order_number} (Rp " . number_format($order->total_amount) . " via {$method})",
            );

            return $order->fresh()->load('lines.product', 'payment', 'cashier');
        });
    }

    /**
     * Update order type (Dine In <-> Takeaway) and temperature for individual items or whole order
     */
    public function updateOrderType(PosOrder $order, string $newOrderType, ?string $newTableNumber, array $items = []): PosOrder
    {
        return DB::transaction(function () use ($order, $newOrderType, $newTableNumber, $items) {
            $oldOrderType = $order->order_type;

            $anyTakeaway = false;
            $anyDineIn   = false;
            $hasSwitchedToTakeaway = false;

            // Update lines notes & modifiers
            foreach ($order->lines as $line) {
                $itemPayload = null;
                if (!empty($items)) {
                    foreach ($items as $it) {
                        if (isset($it['id']) && (int)$it['id'] === (int)$line->id) {
                            $itemPayload = $it;
                            break;
                        }
                    }
                }

                $lineOrderType = $itemPayload['order_type'] ?? $newOrderType;
                $lineTemp      = isset($itemPayload['temperature'])
                    ? (in_array(strtolower($itemPayload['temperature']), ['iced', 'cold', 'ice', 'dingin']) ? 'iced' : 'hot')
                    : $line->temperature;

                if ($lineOrderType === 'takeaway') $anyTakeaway = true;
                if ($lineOrderType === 'dine_in')  $anyDineIn   = true;

                $mods = is_array($line->modifiers) 
                    ? $line->modifiers 
                    : (is_string($line->modifiers) ? json_decode($line->modifiers, true) : []);
                
                $oldLineOrderType = $mods['order_type'] ?? $oldOrderType;
                if ($oldLineOrderType !== 'takeaway' && $lineOrderType === 'takeaway') {
                    $hasSwitchedToTakeaway = true;
                }

                $mods['order_type']  = $lineOrderType;
                $mods['temperature'] = $lineTemp;

                // Clean system tags from notes and append updated tags
                $cleanNotes = preg_replace('/\[(Takeaway|Dine In|Hot|Iced|Cold)\]/i', '', $line->notes ?? '');
                $cleanNotes = trim(preg_replace('/\s+/', ' ', $cleanNotes));

                $tagType = ($lineOrderType === 'takeaway') ? '[Takeaway]' : '[Dine In]';
                $tagTemp = ($lineTemp === 'iced') ? '[Iced]' : '[Hot]';

                $newNotes = trim("{$cleanNotes} {$tagType} {$tagTemp}");

                $line->update([
                    'modifiers' => $mods,
                    'notes'     => $newNotes,
                ]);
            }

            $finalOrderType = ($anyTakeaway && !$anyDineIn) ? 'takeaway' : (($anyDineIn && !$anyTakeaway) ? 'dine_in' : $newOrderType);

            $order->update([
                'order_type'   => $finalOrderType,
                'table_number' => ($anyDineIn || $finalOrderType === 'dine_in') ? $newTableNumber : null,
            ]);

            // Packaging inventory deduction if switched to takeaway
            if ($hasSwitchedToTakeaway && $order->payments()->where('status', 'completed')->exists()) {
                $warehouse = Warehouse::where('outlet_id', $order->outlet_id)->where('is_default', true)->first()
                    ?? Warehouse::where('outlet_id', $order->outlet_id)->first()
                    ?? Warehouse::first();

                if ($warehouse) {
                    $cup = Ingredient::where('name', 'like', '%Cup%')->first();
                    $foodPkg = Ingredient::where('name', 'like', '%Packaging Makanan%')
                        ->orWhere('name', 'like', '%Paper Box%')
                        ->orWhere('name', 'like', '%Kotak%')
                        ->first();

                    $totalCups = 0;
                    $totalFoodPkg = 0;

                    foreach ($order->lines as $line) {
                        $mods = is_array($line->modifiers) ? $line->modifiers : [];
                        if (($mods['order_type'] ?? '') === 'takeaway') {
                            $product = Product::with('category')->find($line->product_id);
                            $catName = strtolower($product?->category?->name ?? '');
                            $isBeverage = str_contains($catName, 'beverage') || str_contains($catName, 'minuman') || str_contains($catName, 'kopi') || str_contains($catName, 'coffee') || str_contains($catName, 'tea') || str_contains($catName, 'teh') || str_contains($catName, 'drink') || str_contains(strtolower($line->product_name), 'kopi') || str_contains(strtolower($line->product_name), 'coffee') || str_contains(strtolower($line->product_name), 'tea');

                            if ($isBeverage) {
                                $totalCups += $line->qty;
                            } else {
                                $totalFoodPkg += $line->qty;
                            }
                        }
                    }

                    if ($totalCups > 0 && $cup) {
                        try {
                            $cupStockDeduct = $cup->convertRecipeQtyToMainStockQty($totalCups);
                            $this->inventoryService->stockOut([
                                'ingredient_id'  => $cup->id,
                                'warehouse_id'   => $warehouse->id,
                                'qty'            => $cupStockDeduct,
                                'reference_type' => PosOrder::class,
                                'reference_id'   => $order->id,
                                'notes'          => "POS Switch to Takeaway Cup Deduct #{$order->order_number} (x{$totalCups})",
                            ]);
                        } catch (\Exception $e) {}
                    }

                    if ($totalFoodPkg > 0 && $foodPkg) {
                        try {
                            $foodPkgStockDeduct = $foodPkg->convertRecipeQtyToMainStockQty($totalFoodPkg);
                            $this->inventoryService->stockOut([
                                'ingredient_id'  => $foodPkg->id,
                                'warehouse_id'   => $warehouse->id,
                                'qty'            => $foodPkgStockDeduct,
                                'reference_type' => PosOrder::class,
                                'reference_id'   => $order->id,
                                'notes'          => "POS Switch to Takeaway Food Packaging Deduct #{$order->order_number} (x{$totalFoodPkg})",
                            ]);
                        } catch (\Exception $e) {}
                    }
                }
            }

            ActivityLog::record(
                action:      'pos_update_order_type',
                module:      'pos',
                entity:      'PosOrder',
                entityId:    $order->id,
                description: "POS Order #{$order->order_number} updated per-item order types",
            );

            return $order->fresh()->load('lines.product', 'payment', 'cashier', 'customer');
        });
    }

    /**
     * Process refund for a POS Order and reverse stock
     */
    public function refundOrder(PosOrder $order, array $data, ?User $processor = null): \App\Models\PosRefund
    {
        return DB::transaction(function () use ($order, $data, $processor) {
            $reasonRaw = $data['reason'] ?? 'customer_request';
            $validReasons = ['wrong_item', 'quality_issue', 'customer_request', 'other'];
            $reason = in_array($reasonRaw, $validReasons) ? $reasonRaw : 'other';
            $notes = $data['notes'] ?? $data['reason'] ?? 'Refund pesanan kasir';

            $datePrefix = 'REF-' . date('Ymd') . '-';
            $refundNum = $datePrefix . str_pad($order->id, 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);

            $refund = \App\Models\PosRefund::create([
                'order_id'      => $order->id,
                'processed_by'  => $processor?->id ?? auth()->id() ?? $order->cashier_id,
                'refund_number' => $refundNum,
                'amount'        => (float)($data['amount'] ?? $order->total_amount),
                'reason'        => $reason,
                'notes'         => $notes,
                'refund_method' => $data['refund_method'] ?? 'cash',
            ]);

            // Reversal of inventory stock
            $this->reverseOrderStock($order, 'stock_in', $notes);

            // Update order and payment statuses
            $order->update(['status' => 'refunded']);
            $order->payments()->update(['status' => 'refunded']);

            ActivityLog::record(
                action:      'refund_order',
                module:      'pos',
                entity:      'PosOrder',
                entityId:    $order->id,
                description: "POS Order #{$order->order_number} di-refund ({$refundNum}): {$notes}",
            );

            return $refund;
        });
    }

    /**
     * Reverse stock when an order is refunded, voided, or deleted
     */
    public function reverseOrderStock(PosOrder $order, string $stockAction = 'stock_in', string $reason = ''): void
    {
        // 1. Try to reverse from recorded StockMovements for this order
        $movements = \App\Models\StockMovement::where('reference_type', PosOrder::class)
            ->where('reference_id', $order->id)
            ->where('type', 'stock_out')
            ->get();

        if ($movements->isNotEmpty()) {
            foreach ($movements as $sm) {
                if ($stockAction === 'waste') {
                    $stock = \App\Models\IngredientStock::where('ingredient_id', $sm->ingredient_id)
                        ->where('warehouse_id', $sm->warehouse_id)
                        ->first();
                    $currentStock = $stock ? (float)$stock->qty : 0;

                    \App\Models\StockMovement::create([
                        'ingredient_id'  => $sm->ingredient_id,
                        'warehouse_id'   => $sm->warehouse_id,
                        'user_id'        => auth()->id(),
                        'type'           => 'waste',
                        'qty'            => -abs((float)$sm->qty),
                        'qty_before'     => $currentStock,
                        'qty_after'      => $currentStock,
                        'unit_cost'      => (float)$sm->unit_cost,
                        'total_cost'     => abs((float)$sm->qty) * (float)$sm->unit_cost,
                        'reference_type' => PosOrder::class,
                        'reference_id'   => $order->id,
                        'notes'          => "POS Waste Pembatalan/Void Order #{$order->order_number}: {$reason}",
                        'movement_at'    => now(),
                    ]);
                } else {
                    try {
                        $this->inventoryService->stockIn([
                            'ingredient_id'  => $sm->ingredient_id,
                            'warehouse_id'   => $sm->warehouse_id,
                            'qty'            => abs((float)$sm->qty),
                            'unit_cost'      => (float)$sm->unit_cost,
                            'is_base_qty'    => true,
                            'reference_type' => PosOrder::class,
                            'reference_id'   => $order->id,
                            'notes'          => "POS Reversal/Retur Order #{$order->order_number}: {$reason}",
                        ]);
                    } catch (\Exception $e) {
                        ActivityLog::record(
                            action:      'pos_stock_reverse_warning',
                            module:      'pos',
                            entity:      'PosOrder',
                            entityId:    $order->id,
                            description: "Warning reversal stock for POS #{$order->order_number}: " . $e->getMessage(),
                        );
                    }
                }
            }
            return;
        }

        // 2. Fallback: compute from recipe ingredients if no stock movement records exist
        $warehouse = Warehouse::where('outlet_id', $order->outlet_id)->where('is_default', true)->first()
            ?? Warehouse::where('outlet_id', $order->outlet_id)->first()
            ?? Warehouse::where('is_default', true)->first()
            ?? Warehouse::first();

        if (!$warehouse) {
            return;
        }

        foreach ($order->lines as $line) {
            $product = Product::with('recipe.ingredients')->find($line->product_id);
            if ($product && $product->recipe && $product->recipe->ingredients) {
                foreach ($product->recipe->ingredients as $recipeIng) {
                    if ($recipeIng->type === 'packaging' || $recipeIng->type === 'ice') {
                        continue;
                    }
                    $totalDoseNeeded = (float)$recipeIng->qty * $line->qty;
                    $ingModel = $recipeIng->ingredient ?? Ingredient::find($recipeIng->ingredient_id);
                    if ($ingModel) {
                        $stockQtyToReturn = $ingModel->convertRecipeQtyToMainStockQty($totalDoseNeeded);
                        try {
                            $this->inventoryService->stockIn([
                                'ingredient_id'  => $ingModel->id,
                                'warehouse_id'   => $warehouse->id,
                                'qty'            => $stockQtyToReturn,
                                'unit_cost'      => (float)($ingModel->average_cost ?: $ingModel->cost_per_unit),
                                'is_base_qty'    => true,
                                'reference_type' => PosOrder::class,
                                'reference_id'   => $order->id,
                                'notes'          => "POS Reversal Fallback Order #{$order->order_number} ({$line->product_name}): {$reason}",
                            ]);
                        } catch (\Exception $e) {}
                    }
                }
            }
        }
    }

    /**
     * Deduct stock for newly added items to an already paid/processing order
     */
    public function deductNewItemsStock(PosOrder $order, array $newLines): void
    {
        $warehouse = Warehouse::where('outlet_id', $order->outlet_id)->where('is_default', true)->first()
            ?? Warehouse::where('outlet_id', $order->outlet_id)->first()
            ?? Warehouse::where('is_default', true)->first()
            ?? Warehouse::first();

        if (!$warehouse) {
            return;
        }

        $cup = Ingredient::where('name', 'like', '%Cup%')->first();
        $foodPkg = Ingredient::where('name', 'like', '%Packaging Makanan%')
            ->orWhere('name', 'like', '%Paper Box%')
            ->orWhere('name', 'like', '%Kotak%')
            ->first();

        $totalCups = 0;
        $totalFoodPkg = 0;

        foreach ($newLines as $lineData) {
            $product = Product::with('category', 'recipe.ingredients')->find($lineData['product_id']);
            $qty = (int)$lineData['qty'];

            if ($product && $product->recipe && $product->recipe->ingredients) {
                foreach ($product->recipe->ingredients as $recipeIng) {
                    if ($recipeIng->type === 'packaging' || $recipeIng->type === 'ice') {
                        continue;
                    }
                    $totalDoseNeeded = (float)$recipeIng->qty * $qty;
                    $ingModel = $recipeIng->ingredient ?? Ingredient::find($recipeIng->ingredient_id);

                    if ($ingModel) {
                        $stockQtyToDeduct = $ingModel->convertRecipeQtyToMainStockQty($totalDoseNeeded);
                        try {
                            $this->inventoryService->stockOut([
                                'ingredient_id'  => $ingModel->id,
                                'warehouse_id'   => $warehouse->id,
                                'qty'            => $stockQtyToDeduct,
                                'reference_type' => PosOrder::class,
                                'reference_id'   => $order->id,
                                'notes'          => "POS Deduct Added Item #{$order->order_number} ({$lineData['product_name']} x{$qty})",
                            ]);
                        } catch (\Exception $e) {
                            ActivityLog::record(
                                action:      'pos_stock_out_warning',
                                module:      'pos',
                                entity:      'PosOrder',
                                entityId:    $order->id,
                                description: "Warning added item stock deduction for POS #{$order->order_number} ({$ingModel->name}): " . $e->getMessage(),
                            );
                        }
                    }
                }
            }

            $orderType = $lineData['modifiers']['order_type'] ?? 'dine_in';
            if ($orderType === 'takeaway') {
                $catName = strtolower($product?->category?->name ?? '');
                $isBeverage = str_contains($catName, 'beverage') || str_contains($catName, 'minuman') || str_contains($catName, 'kopi') || str_contains($catName, 'coffee') || str_contains($catName, 'tea') || str_contains($catName, 'teh') || str_contains($catName, 'drink') || str_contains(strtolower($product?->name ?? ''), 'kopi') || str_contains(strtolower($product?->name ?? ''), 'coffee') || str_contains(strtolower($product?->name ?? ''), 'tea') || str_contains(strtolower($product?->name ?? ''), 'latte');
                if ($isBeverage) {
                    $totalCups += $qty;
                } else {
                    $totalFoodPkg += $qty;
                }
            }
        }

        if ($totalCups > 0 && $cup) {
            try {
                $cupStockDeduct = $cup->convertRecipeQtyToMainStockQty($totalCups);
                $this->inventoryService->stockOut([
                    'ingredient_id'  => $cup->id,
                    'warehouse_id'   => $warehouse->id,
                    'qty'            => $cupStockDeduct,
                    'reference_type' => PosOrder::class,
                    'reference_id'   => $order->id,
                    'notes'          => "POS Added Takeaway Cup Deduct #{$order->order_number} (x{$totalCups})",
                ]);
            } catch (\Exception $e) {}
        }

        if ($totalFoodPkg > 0 && $foodPkg) {
            try {
                $foodPkgStockDeduct = $foodPkg->convertRecipeQtyToMainStockQty($totalFoodPkg);
                $this->inventoryService->stockOut([
                    'ingredient_id'  => $foodPkg->id,
                    'warehouse_id'   => $warehouse->id,
                    'qty'            => $foodPkgStockDeduct,
                    'reference_type' => PosOrder::class,
                    'reference_id'   => $order->id,
                    'notes'          => "POS Added Takeaway Food Packaging Deduct #{$order->order_number} (x{$totalFoodPkg})",
                ]);
            } catch (\Exception $e) {}
        }
    }
}
