<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\PosOrder;
use App\Models\PosOrderLine;
use App\Models\PosPayment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReportDataSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->warn('No products found to seed orders.');
            return;
        }

        $paymentMethods = ['cash', 'qris', 'bank_transfer', 'debit_card', 'credit_card'];
        $orderTypes     = ['dine_in', 'takeaway'];

        // Clean existing test orders to avoid cluttering
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PosOrderLine::truncate();
        PosPayment::truncate();
        PosOrder::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $orderCount = 0;

        $outletId  = \App\Models\Outlet::first()?->id ?? 2;
        $cashierId = \App\Models\User::first()?->id ?? 2;

        // Generate orders over the past 60 days
        for ($daysAgo = 60; $daysAgo >= 0; $daysAgo--) {
            // Generate 2 to 6 orders per day
            $dailyOrders = rand(2, 6);
            if ($daysAgo === 0) {
                // Today gets more orders for immediate 1 Hari feedback
                $dailyOrders = rand(8, 15);
            }

            for ($i = 1; $i <= $dailyOrders; $i++) {
                $orderCount++;
                $orderDate = Carbon::now()->subDays($daysAgo)->setHour(rand(8, 21))->setMinute(rand(0, 59));
                
                $orderNumber = 'ORD-' . $orderDate->format('Ymd') . '-' . str_pad($orderCount, 4, '0', STR_PAD_LEFT);
                $queueNumber = str_pad($i, 3, '0', STR_PAD_LEFT);
                $orderType   = $orderTypes[array_rand($orderTypes)];

                // Pick 1 to 3 random products
                $orderProducts = $products->random(rand(1, 3));

                $subtotal   = 0;
                $totalCost  = 0;
                $lineItemsData = [];

                foreach ($orderProducts as $prod) {
                    $qty       = rand(1, 4);
                    $unitPrice = (float) $prod->selling_price;
                    $unitCost  = (float) ($prod->hpp ?: $prod->cogs ?: 2000);
                    $lineSub   = $qty * $unitPrice;
                    $lineCost  = $qty * $unitCost;

                    $subtotal  += $lineSub;
                    $totalCost += $lineCost;

                    $lineItemsData[] = [
                        'product_id'   => $prod->id,
                        'product_name' => $prod->name,
                        'unit_price'   => $unitPrice,
                        'unit_cost'    => $unitCost,
                        'qty'          => $qty,
                        'subtotal'     => $lineSub,
                    ];
                }

                $taxAmount   = 0; // flat gross
                $totalAmount = $subtotal;
                $grossProfit = max(0, $totalAmount - $totalCost);

                $order = PosOrder::create([
                    'outlet_id'     => $outletId,
                    'cashier_id'    => $cashierId,
                    'order_number'  => $orderNumber,
                    'queue_number'  => $queueNumber,
                    'order_type'    => $orderType,
                    'subtotal'      => $subtotal,
                    'discount_amount' => 0,
                    'tax_amount'    => 0,
                    'total_amount'  => $totalAmount,
                    'total_cost'    => $totalCost,
                    'gross_profit'  => $grossProfit,
                    'status'        => 'completed',
                    'completed_at'  => $orderDate,
                    'created_at'    => $orderDate,
                    'updated_at'    => $orderDate,
                ]);

                foreach ($lineItemsData as $itemData) {
                    $itemData['order_id']   = $order->id;
                    $itemData['created_at'] = $orderDate;
                    $itemData['updated_at'] = $orderDate;
                    PosOrderLine::create($itemData);
                }

                $method = $paymentMethods[array_rand($paymentMethods)];
                PosPayment::create([
                    'order_id'         => $order->id,
                    'method'           => $method,
                    'amount'           => $totalAmount,
                    'tendered'         => $method === 'cash' ? ceil($totalAmount / 10000) * 10000 : $totalAmount,
                    'change_amount'    => $method === 'cash' ? (ceil($totalAmount / 10000) * 10000) - $totalAmount : 0,
                    'reference_number' => 'REF-' . strtoupper(Str::random(8)),
                    'status'           => 'completed',
                    'created_at'       => $orderDate,
                    'updated_at'       => $orderDate,
                ]);
            }
        }

        $this->command->info("Successfully seeded {$orderCount} completed POS transactions into database.");
    }
}
