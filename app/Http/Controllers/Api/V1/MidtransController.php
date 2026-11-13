<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Services\PosService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    private $posService;

    public function __construct(PosService $posService)
    {
        $this->posService = $posService;
        $this->setupMidtransConfig();
    }

    private function setupMidtransConfig()
    {
        Config::$serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
        Config::$isProduction = config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false));
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Endpoint to get Snap Token for a POS Order
     */
    public function getToken(Request $request, PosOrder $order): JsonResponse
    {
        // Don't generate if already paid
        if ($order->payments()->where('status', 'completed')->exists()) {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.'], 400);
        }

        $params = [
            'transaction_details' => [
                'order_id' => $order->order_number . '-' . time(), // Appending time to avoid duplicate order_id in Midtrans if retried
                'gross_amount' => (int) $order->total_amount,
            ],
            'customer_details' => [
                'first_name' => $order->customer ? $order->customer->name : 'Pelanggan POS',
                'email' => $order->customer ? $order->customer->email : 'customer@ratiocoffee.com',
                'phone' => $order->customer ? $order->customer->phone : '081234567890',
            ],
            'item_details' => []
        ];

        foreach ($order->lines as $line) {
            $params['item_details'][] = [
                'id' => $line->product_id,
                'price' => (int) $line->unit_price,
                'quantity' => $line->qty,
                'name' => mb_substr($line->product_name, 0, 50),
            ];
        }

        if ((float)$order->discount_amount > 0) {
            $params['item_details'][] = [
                'id'       => 'DISCOUNT',
                'price'    => -(int)$order->discount_amount,
                'quantity' => 1,
                'name'     => 'Potongan Diskon / Promo',
            ];
        }

        if ((float)$order->tax_amount > 0) {
            $params['item_details'][] = [
                'id'       => 'TAX',
                'price'    => (int)$order->tax_amount,
                'quantity' => 1,
                'name'     => 'Pajak Resto (PB1)',
            ];
        }

        try {
            $snapToken = Snap::getSnapToken($params);
            return response()->json(['snap_token' => $snapToken]);
        } catch (\Exception $e) {
            Log::error('Midtrans Token Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat token pembayaran: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Webhook Notification from Midtrans
     */
    public function notification(Request $request): JsonResponse
    {
        // Bypass SDK verification specifically for the Midtrans "Test Notification URL" button
        if (str_starts_with($request->input('order_id', ''), 'payment_notif_test_')) {
            return response()->json(['message' => 'Test Notification OK'], 200);
        }

        // Verifikasi Signature Key SHA-512
        $serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
        $inputSignature = $request->input('signature_key');
        $grossAmount = $request->input('gross_amount');
        $statusCode = $request->input('status_code');
        $orderId = $request->input('order_id');
        $mySignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($inputSignature !== $mySignature) {
            return response()->json(['message' => 'Invalid signature key'], 403);
        }

        try {
            $notif = new Notification();
        } catch (\Exception $e) {
            Log::error('Midtrans Notification Error: ' . $e->getMessage());
            return response()->json(['message' => 'Notification error: ' . $e->getMessage()], 500);
        }

        $transaction = $notif->transaction_status;
        $type = $notif->payment_type;
        $order_id = $notif->order_id;
        $fraud = $notif->fraud_status;

        // Parse our actual order_number from Midtrans order_id (e.g. POS-20231012-0001-1698765432)
        $orderNumber = preg_replace('/-\d{10,}$/', '', $order_id);

        $order = PosOrder::where('order_number', $orderNumber)->first();
        if (!$order) {
            // Return 200 OK even if not found so Midtrans "Test notification" doesn't fail
            return response()->json(['message' => 'Order not found (Test Notification OK)'], 200);
        }

        if ($transaction == 'capture') {
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    // TODO set payment status in merchant's database to 'Challenge by FDS'
                } else {
                    $this->handleSuccessPayment($order, $notif);
                }
            }
        } else if ($transaction == 'settlement') {
            $this->handleSuccessPayment($order, $notif);
        } else if ($transaction == 'pending') {
            // TODO set payment status in merchant's database to 'Pending'
        } else if ($transaction == 'deny' || $transaction == 'expire' || $transaction == 'cancel') {
            // TODO set payment status in merchant's database to 'Denied' or 'Expire'
        }

        return response()->json(['message' => 'Notification processed successfully']);
    }

    private function handleSuccessPayment(PosOrder $order, $notif)
    {
        // Prevent double payment
        if ($order->payments()->where('status', 'completed')->exists()) {
            return;
        }

        $paymentMethod = match($notif->payment_type) {
            'qris' => 'qris',
            'bank_transfer', 'echannel' => 'bank_transfer',
            'credit_card' => 'credit_card',
            default => 'other'
        };

        $paymentData = [
            'method' => $paymentMethod,
            'tendered' => $notif->gross_amount,
            'reference_number' => $notif->transaction_id,
        ];

        try {
            $this->posService->payOrder($order, $paymentData);
        } catch (\Exception $e) {
            Log::error('Midtrans Pay Order Error: ' . $e->getMessage());
        }
    }
}
