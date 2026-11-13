<?php

namespace App\Services;

use App\Models\BrandSetting;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Exception;
use Illuminate\Support\Facades\Log;

class ReceiptPrinterService
{
    protected $printer;
    protected $connectionType;
    protected $printerName;
    protected int $networkPort;
    protected int $lineWidth;
    protected bool $connected = false;

    /**
     * Inisialisasi koneksi printer
     */
    public function __construct($connectionType, $printerName, int $networkPort = 9100, string $paperWidth = '58mm')
    {
        $this->connectionType = $connectionType;
        $this->printerName = $printerName;
        $this->networkPort = $networkPort;
        $this->lineWidth = $paperWidth === '80mm' ? 48 : 32;
        
        try {
            $connector = null;

            switch ($connectionType) {
                case 'windows':
                    $connector = new WindowsPrintConnector($printerName);
                    break;
                case 'network':
                    $connector = new NetworkPrintConnector($printerName, $networkPort, 3);
                    break;
                case 'linux':
                    $connector = new FilePrintConnector($printerName);
                    break;
                default:
                    throw new Exception("Tipe koneksi printer tidak didukung: {$connectionType}");
            }

            $this->printer = new Printer($connector);
            $this->connected = true;
            
        } catch (Exception $e) {
            Log::warning("Koneksi Printer Gagal ({$connectionType}/{$printerName}): " . $e->getMessage());
            $this->printer = null;
            $this->connected = false;
        }
    }

    /**
     * Ambil informasi toko dari BrandSetting secara dinamis
     */
    protected function getStoreInfo(): array
    {
        try {
            $brand = BrandSetting::allAsArray();
            return [
                'name'    => $brand['brand_name'] ?? $brand['name'] ?? 'RATIO COFFEE',
                'address' => $brand['address'] ?? '',
                'phone'   => $brand['phone'] ?? $brand['telp'] ?? '',
                'website' => $brand['website'] ?? 'www.ratiocoffee.my.id',
            ];
        } catch (Exception $e) {
            return [
                'name'    => 'RATIO COFFEE',
                'address' => '',
                'phone'   => '',
                'website' => 'www.ratiocoffee.my.id',
            ];
        }
    }

    /**
     * Format label pembayaran dari kode metode
     */
    protected function formatPaymentMethod(?string $method): string
    {
        return match (strtolower($method ?? 'cash')) {
            'cash'          => 'Tunai (Cash)',
            'qris'          => 'QRIS',
            'bank_transfer' => 'Transfer Bank',
            'debit_card'    => 'Kartu Debit',
            'credit_card'   => 'Kartu Kredit',
            default         => ucfirst($method ?? 'Cash'),
        };
    }

    /**
     * Fungsi utama untuk mencetak struk order via ESC/POS printer
     */
    public function printOrderReceipt($order): array
    {
        if (!$this->printer || !$this->connected) {
            return [
                'success'       => false, 
                'fallback_html' => true,
                'message'       => "Printer fisik server offline ({$this->connectionType}/{$this->printerName}). Menggunakan cetak browser.",
            ];
        }

        try {
            $store = $this->getStoreInfo();
            $payment = $order->payment;

            // --- HEADER TOKO ---
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setTextSize(2, 2);
            $this->printer->text(strtoupper($store['name']) . "\n");
            
            $this->printer->setTextSize(1, 1);
            if (!empty($store['address'])) {
                $this->printer->text($store['address'] . "\n");
            }
            if (!empty($store['phone'])) {
                $this->printer->text("Telp: " . $store['phone'] . "\n");
            }
            $this->printer->feed(); 

            // --- INFO ORDER ---
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text(str_repeat("-", $this->lineWidth) . "\n");
            $this->printer->text("No Order : " . ($order->order_number ?? '-') . "\n");
            
            if ($order->queue_number) {
                $this->printer->text("Antrean  : " . $order->queue_number . "\n");
            }
            
            $this->printer->text("Kasir    : " . ($order->cashier?->name ?? 'Kasir') . "\n");
            $this->printer->text("Waktu    : " . ($order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i')) . "\n");
            
            $orderTypeLabel = match ($order->order_type) {
                'takeaway' => 'Takeaway',
                'delivery' => 'Delivery',
                default    => 'Dine In',
            };
            $this->printer->text("Tipe     : " . $orderTypeLabel . "\n");
            
            if ($order->table_number) {
                $this->printer->text("Meja     : " . $order->table_number . "\n");
            }
            if ($order->customer?->name) {
                $this->printer->text("Customer : " . $order->customer->name . "\n");
            }
            
            $this->printer->text(str_repeat("-", $this->lineWidth) . "\n");

            // --- ITEM MENU ---
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            foreach ($order->lines as $item) {
                $name = mb_substr($item->product_name ?? 'Menu', 0, $this->lineWidth);
                $qtyStr = $item->qty . "x " . number_format($item->unit_price, 0, ',', '.');
                $subtotal = $item->subtotal;

                $this->printer->text($name . "\n");
                
                $leftCol = "  " . $qtyStr;
                $rightCol = number_format($subtotal, 0, ',', '.');
                $spaces = $this->lineWidth - strlen($leftCol) - strlen($rightCol);
                
                $this->printer->text($leftCol . str_repeat(" ", max(0, $spaces)) . $rightCol . "\n");
            }

            $this->printer->text(str_repeat("-", $this->lineWidth) . "\n");

            // --- TOTAL & PEMBAYARAN ---
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            
            $this->printAlignedLine("Subtotal", "Rp " . number_format($order->subtotal, 0, ',', '.'));
            
            if ($order->discount_amount > 0) {
                $this->printAlignedLine("Diskon", "- Rp " . number_format($order->discount_amount, 0, ',', '.'));
            }
            if ($order->tax_amount > 0) {
                $this->printAlignedLine("Pajak", "Rp " . number_format($order->tax_amount, 0, ',', '.'));
            }

            $this->printer->text(str_repeat("=", $this->lineWidth) . "\n");
            $this->printer->setEmphasis(true);
            $this->printAlignedLine("TOTAL", "Rp " . number_format($order->total_amount, 0, ',', '.'));
            $this->printer->setEmphasis(false);
            $this->printer->text(str_repeat("=", $this->lineWidth) . "\n");

            // --- RINCIAN PEMBAYARAN ---
            if ($payment) {
                $this->printAlignedLine("Bayar", $this->formatPaymentMethod($payment->method));
                
                if (strtolower($payment->method) === 'cash') {
                    $this->printAlignedLine("Diterima", "Rp " . number_format($payment->tendered ?? 0, 0, ',', '.'));
                    $this->printAlignedLine("Kembali", "Rp " . number_format($payment->change_amount ?? 0, 0, ',', '.'));
                }
                
                if ($payment->reference_number) {
                    $this->printAlignedLine("Ref", $payment->reference_number);
                }
            }

            // --- CATATAN ORDER ---
            if ($order->notes) {
                $this->printer->text(str_repeat("-", $this->lineWidth) . "\n");
                $this->printer->text("Catatan: " . $order->notes . "\n");
            }

            // --- FOOTER ---
            $this->printer->feed(1);
            $this->printer->text(str_repeat("-", $this->lineWidth) . "\n");
            $this->printer->feed(1);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Terima Kasih atas Kunjungan Anda\n");
            if (!empty($store['website'])) {
                $this->printer->text($store['website'] . "\n");
            }
            
            $this->printer->feed(4); 
            $this->printer->cut(); 

            $this->printer->close();
            return ['success' => true, 'fallback_html' => false, 'message' => 'Struk berhasil dicetak via printer server.'];

        } catch (Exception $e) {
            Log::error("Gagal mencetak struk: " . $e->getMessage());
            
            // Coba tutup printer jika masih terbuka
            try { $this->printer->close(); } catch (Exception $ex) {}
            
            return [
                'success'       => false,
                'fallback_html' => true,
                'message'       => 'Printer server gagal mencetak: ' . $e->getMessage() . '. Menggunakan cetak browser.',
            ];
        }
    }

    /**
     * Helper: cetak baris rata kiri-kanan dalam lebar 32 karakter
     */
    protected function printAlignedLine(string $left, string $right): void
    {
        $spaces = $this->lineWidth - strlen($left) - strlen($right);
        $this->printer->text($left . str_repeat(" ", max(1, $spaces)) . $right . "\n");
    }
}
