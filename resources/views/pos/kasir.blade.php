@extends('layouts.erp')
@section('title', 'POS Kasir – Ratio Coffee ERP')

@push('styles')
<style>
.pos-layout{display:grid;grid-template-columns:1fr 390px;gap:20px;min-height:calc(100vh - 160px)}
.product-card{background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;transition:all .15s ease;user-select:none}
.product-card:hover{border-color:var(--clr-primary);transform:translateY(-2px);box-shadow:var(--shadow-sm)}
.product-card:active{transform:scale(.97)}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px}
.cart-sidebar{background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;display:flex;flex-direction:column;height:fit-content;position:sticky;top:80px}
.cart-item{border-bottom:1px solid var(--border);padding:10px 0}
.pkg-bar{background:var(--bg-surface);border:1px solid var(--border);padding:8px 14px;border-radius:8px;margin-bottom:12px;font-size:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.temp-btn{padding:2px 8px;font-size:10px;border-radius:4px;border:1px solid var(--border);background:var(--bg-muted);color:var(--txt-secondary);cursor:pointer;transition:all .15s}
.temp-btn.active{background:var(--clr-primary);border-color:var(--clr-primary);color:#fff}
.qty-ctrl{display:flex;align-items:center;gap:6px}
.qty-btn{width:24px;height:24px;border-radius:4px;border:1px solid var(--border);background:var(--bg-muted);cursor:pointer;font-weight:700;display:flex;align-items:center;justify-content:center;transition:background .15s}
.qty-btn:hover{background:var(--border)}
.pay-method-btn{flex:1;padding:8px 4px;border-radius:6px;border:1px solid var(--border);background:var(--bg-muted);cursor:pointer;font-size:12px;font-weight:700;transition:all .15s;text-align:center}
.pay-method-btn.active{background:var(--clr-primary);border-color:var(--clr-primary);color:#fff}
.quick-cash-btn{padding:6px;border-radius:6px;border:1px solid var(--border);background:var(--bg-muted);cursor:pointer;font-size:12px;font-weight:600;transition:background .15s;text-align:center}
.quick-cash-btn:hover{background:var(--border)}
.receipt-line{display:flex;justify-content:space-between;margin-bottom:4px}
@media(max-width:900px){.pos-layout{grid-template-columns:1fr}.cart-sidebar{position:static;top:0}}
@media(max-width:480px){.products-grid{grid-template-columns:repeat(auto-fill,minmax(125px,1fr));gap:8px}}

/* ── Thermal Receipt Preview ─────────────────────────────────── */
.receipt-preview-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
}
.receipt-paper {
    background: #fff; color: #111; font-family: 'Courier New', Courier, monospace;
    width: 320px; max-width: 95vw; padding: 24px 18px;
    border-radius: 4px; box-shadow: 0 8px 40px rgba(0,0,0,0.3);
    font-size: 12px; line-height: 1.6;
    max-height: 85vh; overflow-y: auto;
}
.receipt-paper .receipt-header { text-align: center; margin-bottom: 8px; }
.receipt-paper .receipt-store-name { font-size: 18px; font-weight: 900; letter-spacing: 1px; }
.receipt-paper .receipt-store-info { font-size: 11px; color: #555; }
.receipt-paper .receipt-divider { border: none; border-top: 1px dashed #999; margin: 8px 0; }
.receipt-paper .receipt-row { display: flex; justify-content: space-between; gap: 8px; }
.receipt-paper .receipt-row-label { color: #555; }
.receipt-paper .receipt-item-name { font-weight: 700; }
.receipt-paper .receipt-item-detail { padding-left: 12px; color: #444; }
.receipt-paper .receipt-total-row { font-weight: 900; font-size: 14px; }
.receipt-paper .receipt-footer { text-align: center; margin-top: 12px; font-size: 11px; color: #777; }
.receipt-actions { display: flex; gap: 8px; margin-top: 16px; justify-content: center; }
.receipt-actions button { padding: 8px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; }
.receipt-actions .btn-print-browser { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }
.receipt-actions .btn-print-browser:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.receipt-actions .btn-close-receipt { background: #e5e7eb; color: #374151; }
.receipt-actions .btn-close-receipt:hover { background: #d1d5db; }

@media print {
    /* 1. Hilangkan header/footer bawaan browser (URL, Tanggal, dll) */
    @page { margin: 0; }
    html, body, .erp-layout, .main-content, [x-data="posKasir()"] { 
        margin: 0 !important; padding: 0 !important; 
        background: #fff !important; 
        height: auto !important; min-height: 0 !important; 
    }

    /* 2. Sembunyikan SEMUA elemen root di body (termasuk AI Assistant Hostinger) */
    body > * { display: none !important; }
    
    /* 3. Tampilkan HANYA kontainer utama ERP */
    body > .erp-layout { display: block !important; }
    
    /* 4. Di dalam ERP, sembunyikan sidebar, topbar, modal lain */
    .erp-layout > *:not(.main-content) { display: none !important; }
    .main-content > div:first-child { display: none !important; } /* Sembunyikan judul halaman */
    
    /* 5. Di dalam kontainer Kasir, sembunyikan semua KECUALI struk */
    [x-data="posKasir()"] > *:not(.receipt-preview-overlay) { display: none !important; }

    /* 6. Posisikan struk absolut di pojok kiri atas untuk menghindari margin/halaman kosong */
    .receipt-preview-overlay {
        position: absolute !important;
        top: 0 !important; left: 0 !important;
        display: block !important;
        background: transparent !important;
        backdrop-filter: none !important;
        margin: 0 !important; padding: 0 !important;
    }
    
    .receipt-paper {
        box-shadow: none !important; border-radius: 0 !important;
        width: var(--receipt-width, 58mm) !important; max-width: 100% !important;
        margin: 0; padding: 0;
        max-height: none !important; overflow: visible !important;
    }
    
    .receipt-actions { display: none !important; }
}
</style>
@endpush

@section('page-content')
<div x-data="posKasir()" x-init="init()" x-cloak>

    <!-- Packaging Stock Bar -->
    <div class="pkg-bar mb-3">
        <span style="font-weight:700;color:var(--txt-primary)">📦 Stok Packaging (Takeaway):</span>
        <span>🥤 <strong>Cup:</strong>
            <span :style="(pkg.cup||0)<=10?'color:var(--clr-danger);font-weight:700':'color:var(--clr-success);font-weight:700'"
                  x-text="(pkg.cup||0)+' pcs'"></span>
        </span>
        <span style="border-left:1px solid var(--border);height:14px"></span>
        <span>🍱 <strong>Packaging Makanan:</strong>
            <span :style="(pkg.food||0)<=10?'color:var(--clr-danger);font-weight:700':'color:var(--clr-success);font-weight:700'"
                  x-text="(pkg.food||0)+' pcs'"></span>
        </span>
        <span x-show="loading" class="ms-auto">
            <span class="spinner-border spinner-border-sm text-warning"></span>
        </span>
    </div>

    <div class="pos-layout">
        <!-- ══ LEFT: Product Catalog ══════════════════════════════════════ -->
        <div>
            <!-- Search & Category Filter -->
            <div style="display:flex;gap:10px;margin-bottom:14px;overflow-x:auto;padding-bottom:4px;flex-wrap:wrap">
                <input x-model="search" class="form-input" placeholder="🔍 Cari menu…" style="max-width:200px;min-width:150px" />
                <button class="btn btn-sm" :class="selCat===''?'btn-primary':'btn-secondary'" @click="selCat=''">Semua Menu</button>
                <button x-show="products.some(p => p.is_bundling)" class="btn btn-sm"
                    :class="selCat==='__bundling__'?'btn-primary':'btn-secondary'"
                    @click="selCat='__bundling__'">Paket Bundling</button>
                <template x-for="cat in categories" :key="cat.id">
                    <button class="btn btn-sm" :class="selCat===cat.id?'btn-primary':'btn-secondary'"
                        @click="selCat=cat.id" x-text="cat.name"></button>
                </template>
            </div>

            <!-- Products Grid -->
            <div class="products-grid">
                <template x-if="!loading && filteredProducts.length === 0">
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--txt-muted)">
                        ☕ Tidak ada menu yang sesuai filter.
                    </div>
                </template>
                <template x-for="p in filteredProducts" :key="p.id">
                    <div class="product-card" @click="addToCart(p)">
                        <div style="font-size:32px;text-align:center;margin-bottom:8px">☕</div>
                        <div style="font-weight:700;font-size:13.5px;margin-bottom:4px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden" x-text="p.name"></div>
                        <div style="font-size:11.5px;color:var(--txt-muted);margin-bottom:5px" x-text="p.is_bundling ? 'Paket Bundling' : (p.category?.name ?? 'Menu')"></div>
                        <template x-if="p.is_bundling && p.bundle_data?.length">
                            <div style="font-size:10.5px;color:var(--txt-secondary);margin-bottom:8px;line-height:1.4">
                                <template x-for="item in p.bundle_data" :key="item.product_id">
                                    <div x-text="(item.qty || 1) + 'x ' + item.product_name"></div>
                                </template>
                            </div>
                        </template>
                        <div style="font-weight:800;color:var(--clr-primary);font-size:14px"
                             x-text="'Rp '+Number(p.selling_price).toLocaleString('id-ID')"></div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ══ RIGHT: Cart & Checkout ═════════════════════════════════════ -->
        <div class="cart-sidebar">
            <!-- Cart Header -->
            <div style="margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <h3 style="margin:0;font-size:16px;font-weight:700">🛒 Keranjang Kasir</h3>
                    <div style="display:flex;gap:4px">
                        <button class="btn btn-sm d-inline-flex align-items-center gap-1"
                                :class="printerStatus.connected ? 'btn-outline-success text-success bg-success-subtle border-success' : 'btn-outline-secondary'"
                                onclick="showPrinterSettingsModal()"
                                :title="printerStatus.connected ? 'Printer Terhubung: ' + printerStatus.name : 'Pengaturan Printer POS'">
                            <span x-text="printerStatus.connected ? '🟢' : '⚙️'"></span>
                            <span x-text="printerStatus.connected ? (printerStatus.name || 'Printer') : 'Printer'"></span>
                        </button>
                        <button class="btn btn-secondary btn-sm" @click="clearCart()">Bersihkan</button>
                    </div>
                </div>
                <!-- Order Type -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <button class="btn btn-sm" :class="orderType==='dine_in'?'btn-primary':'btn-secondary'"
                        @click="setOrderType('dine_in')">🍽 Dine In</button>
                    <button class="btn btn-sm" :class="orderType==='takeaway'?'btn-primary':'btn-secondary'"
                        @click="setOrderType('takeaway')">🛍 Takeaway</button>
                </div>
                <input x-show="orderType==='dine_in'" x-model="tableNumber"
                    class="form-input mt-2" placeholder="No. Meja (mis. Meja 04)" />
            </div>

            <!-- Cart Items -->
            <div style="flex:1;overflow-y:auto;max-height:380px;margin-bottom:12px">
                <div x-show="cart.length===0" style="text-align:center;padding:32px 10px;color:var(--txt-muted);font-size:12.5px">
                    Keranjang kosong. Tap menu di sebelah kiri untuk menambah pesanan.
                </div>
                <template x-for="(item,idx) in cart" :key="idx">
                    <div class="cart-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                            <div style="font-weight:700;font-size:13px" x-text="item.name"></div>
                            <div style="font-weight:700;color:var(--clr-primary);font-size:13px"
                                 x-text="'Rp '+(item.unit_price*item.qty).toLocaleString('id-ID')"></div>
                        </div>
                        <!-- Temp & Order Type Controls -->
                        <div style="display:flex;align-items:center;gap:6px;margin:4px 0 6px;flex-wrap:wrap">
                            <button class="temp-btn" :class="item.temperature==='hot'?'active':''" @click="item.temperature='hot'">🔥 Hot</button>
                            <button class="temp-btn" :class="item.temperature==='iced'?'active':''" @click="item.temperature='iced'">❄️ Ice</button>
                            <select x-model="item.order_type" class="form-input" style="padding:2px 6px;font-size:11px;height:auto;width:auto">
                                <option value="dine_in">🍽 Dine In</option>
                                <option value="takeaway">🛍 Takeaway</option>
                            </select>
                        </div>
                        <!-- Packaging warning -->
                        <div x-show="item.order_type==='takeaway'" style="font-size:11px;margin-bottom:6px;padding:4px 8px;background:var(--bg-muted);border-radius:6px;display:flex;justify-content:space-between">
                            <span x-show="item.is_beverage" style="color:var(--txt-secondary)">
                                🥤 Butuh <strong x-text="item.qty+' Cup'"></strong>
                                (Stok: <strong :style="(pkg.cup||0)<item.qty?'color:var(--clr-danger)':'color:var(--clr-success)'"
                                    x-text="(pkg.cup||0)+' pcs'"></strong>)
                            </span>
                            <span x-show="!item.is_beverage" style="color:var(--txt-secondary)">
                                🍱 Butuh <strong x-text="item.qty+' Packaging'"></strong>
                                (Stok: <strong :style="(pkg.food||0)<item.qty?'color:var(--clr-danger)':'color:var(--clr-success)'"
                                    x-text="(pkg.food||0)+' pcs'"></strong>)
                            </span>
                        </div>
                        <!-- Notes & Qty -->
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <input x-model="item.notes" class="form-input" placeholder="Catatan (mis. Less Ice)"
                                style="max-width:160px;font-size:11px;padding:4px 8px" />
                            <div class="qty-ctrl">
                                <button class="qty-btn" @click="updateQty(idx,-1)">−</button>
                                <span style="font-weight:700;font-size:13px;min-width:20px;text-align:center" x-text="item.qty"></span>
                                <button class="qty-btn" @click="updateQty(idx,1)">+</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Totals & Discount -->
            <div style="border-top:1px solid var(--border);padding-top:12px">
                <!-- Diskon Section Header -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <span style="font-size:12.5px;font-weight:700;color:var(--txt-primary);display:inline-flex;align-items:center;gap:4px">
                        🏷️ Diskon / Promo
                        <template x-if="calculatedDiscount > 0">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-1.5 py-0.5" style="font-size:10px"
                                  x-text="'-' + effectiveDiscountPercent + '%'"></span>
                        </template>
                    </span>
                    <button type="button" class="btn btn-sm text-primary p-0 border-0 fw-bold" style="font-size:11.5px;background:none"
                            @click="showDiscountInput = !showDiscountInput">
                        <span x-text="showDiscountInput ? '▲ Sembunyikan' : (calculatedDiscount > 0 ? '✏️ Ubah Diskon' : '➕ Tambah Diskon')"></span>
                    </button>
                </div>

                <!-- Active Discount Info Pill (when collapsed & discount applied) -->
                <template x-if="calculatedDiscount > 0 && !showDiscountInput">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;margin-bottom:8px;font-size:11.5px">
                        <div>
                            <strong style="color:var(--clr-danger)" x-text="'- Rp ' + calculatedDiscount.toLocaleString('id-ID')"></strong>
                            <span class="text-muted ms-1" style="font-size:10.5px" x-text="discountNote ? '(' + discountNote + ')' : '(' + effectiveDiscountPercent + '%)'"></span>
                        </div>
                        <button type="button" class="btn-close ms-2" style="font-size:9px" @click="clearDiscount()" title="Hapus Diskon"></button>
                    </div>
                </template>

                <!-- Discount Form Panel (Collapsible) -->
                <div x-show="showDiscountInput" x-transition style="padding:10px;background:var(--bg-muted);border:1px solid var(--border);border-radius:8px;margin-bottom:10px">
                    <!-- Type Selector Mode: % vs Rp -->
                    <div style="display:flex;gap:6px;margin-bottom:8px">
                        <button type="button" class="btn btn-sm" style="flex:1;font-size:11px;font-weight:700"
                                :class="discountType==='percent'?'btn-primary':'btn-secondary'"
                                @click="discountType='percent'">
                            % Persentase
                        </button>
                        <button type="button" class="btn btn-sm" style="flex:1;font-size:11px;font-weight:700"
                                :class="discountType==='nominal'?'btn-primary':'btn-secondary'"
                                @click="discountType='nominal'">
                            Rp Nominal
                        </button>
                    </div>

                    <!-- Quick Preset Pills -->
                    <div style="display:flex;gap:4px;margin-bottom:8px">
                        <template x-if="discountType==='percent'">
                            <div style="display:flex;gap:4px;width:100%">
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountPercent(5)">5%</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountPercent(10)">10%</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountPercent(15)">15%</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountPercent(20)">20%</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountPercent(50)">50%</button>
                            </div>
                        </template>
                        <template x-if="discountType==='nominal'">
                            <div style="display:flex;gap:4px;width:100%">
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountNominal(5000)">5k</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountNominal(10000)">10k</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountNominal(15000)">15k</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountNominal(20000)">20k</button>
                                <button type="button" class="quick-cash-btn" style="flex:1;font-size:10.5px;padding:3px" @click="setDiscountNominal(50000)">50k</button>
                            </div>
                        </template>
                    </div>

                    <!-- Input Value & Catatan -->
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <div>
                            <input x-show="discountType==='percent'" x-model.number="discountPercent" type="number" min="0" max="100" step="1"
                                   class="form-input" placeholder="Persentase diskon (%)" style="font-size:12px;padding:5px 8px;font-weight:700" />
                            <input x-show="discountType==='nominal'" x-model.number="discountNominal" type="number" min="0" step="1000"
                                   class="form-input" placeholder="Nominal diskon (Rp)" style="font-size:12px;padding:5px 8px;font-weight:700" />
                        </div>

                        <input x-model="discountNote" class="form-input" placeholder="Catatan Diskon (mis. Member, Promo Ojol)"
                               style="font-size:11px;padding:5px 8px" />

                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px">
                            <button type="button" class="btn btn-secondary btn-sm" style="font-size:11px;padding:3px 8px" @click="clearDiscount()">Reset</button>
                            <button type="button" class="btn btn-primary btn-sm" style="font-size:11px;padding:3px 10px" @click="showDiscountInput=false">Selesai</button>
                        </div>
                    </div>
                </div>

                <!-- Totals Breakdown -->
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span>Subtotal</span><span x-text="'Rp '+cartSubtotal().toLocaleString('id-ID')"></span>
                </div>
                <template x-if="calculatedDiscount > 0">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--clr-danger);font-weight:600">
                        <span>Diskon <small style="font-size:10.5px" x-text="discountNote ? '(' + discountNote + ')' : '(' + effectiveDiscountPercent + '%)'"></small></span>
                        <span x-text="'- Rp '+calculatedDiscount.toLocaleString('id-ID')"></span>
                    </div>
                </template>
                <div x-show="tax.is_service_charge_enabled" style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--txt-muted)">
                    <span x-text="(tax.service_charge_name||'Service Charge')+' ('+tax.service_charge_rate+'%)'"></span>
                    <span x-text="'Rp '+cartServiceCharge().toLocaleString('id-ID')"></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;color:var(--txt-muted)">
                    <span x-show="tax.is_enabled" x-text="(tax.tax_name||'Pajak Resto')+' ('+tax.tax_rate+'%)'"></span>
                    <span x-show="!tax.is_enabled" style="color:var(--clr-success);font-weight:600">Bebas Pajak</span>
                    <span x-show="tax.is_enabled && tax.tax_type!=='charged_to_hpp'" x-text="'Rp '+cartTax().toLocaleString('id-ID')"></span>
                    <span x-show="tax.is_enabled && tax.tax_type==='charged_to_hpp'" style="font-size:11px">Dibebankan ke HPP</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:17px;font-weight:800;color:var(--clr-primary);margin:8px 0">
                    <span>Total Bayar</span>
                    <span x-text="'Rp '+cartTotal().toLocaleString('id-ID')"></span>
                </div>
                <button class="btn btn-success btn-lg w-100 justify-content-center mb-2"
                    style="font-size:15px" :disabled="cart.length===0||loading"
                    @click="openPayModal()">
                    💳 Bayar (Checkout)
                </button>
                <button class="btn btn-warning btn-lg w-100 justify-content-center"
                    style="font-size:15px; color: #fff; font-weight: bold;" :disabled="cart.length===0||loading"
                    @click="processSaveBill()">
                    💾 Simpan Bill
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL: Pembayaran ══════════════════════════════════════════════ -->
    <div x-show="showPayModal" class="modal-overlay" @click.self="showPayModal=false" style="display:flex">
        <div class="modal-box" style="max-width:440px">
            <div class="modal-header">
                <h3 style="margin:0;font-size:17px;font-weight:700">💳 Pembayaran Kasir</h3>
                <button class="btn btn-secondary btn-sm" @click="showPayModal=false">✕</button>
            </div>
            <div class="modal-body">
                <!-- Total Breakdown Box -->
                <div style="margin-bottom:16px;background:var(--bg-muted);padding:14px;border-radius:8px">
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--txt-muted);margin-bottom:2px">
                        <span>Subtotal:</span>
                        <span x-text="'Rp '+cartSubtotal().toLocaleString('id-ID')"></span>
                    </div>
                    <template x-if="calculatedDiscount > 0">
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--clr-danger);margin-bottom:2px">
                            <span>Diskon <small x-text="discountNote ? '(' + discountNote + ')' : '(' + effectiveDiscountPercent + '%)'"></small>:</span>
                            <span x-text="'- Rp '+calculatedDiscount.toLocaleString('id-ID')"></span>
                        </div>
                    </template>
                    <div style="text-align:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
                        <div style="font-size:11.5px;color:var(--txt-muted);font-weight:600">Total Tagihan (Nett):</div>
                        <div style="font-size:26px;font-weight:800;color:var(--clr-primary)"
                             x-text="'Rp '+cartTotal().toLocaleString('id-ID')"></div>
                    </div>
                </div>
                <!-- Payment Method -->
                <div class="mb-3">
                    <label class="form-label">Metode Pembayaran</label>
                    <div style="display:flex;gap:8px">
                        <button class="pay-method-btn" :class="payMethod==='cash'?'active':''" @click="payMethod='cash'">💵 Cash</button>
                        <button class="pay-method-btn" :class="payMethod==='qris'?'active':''" @click="payMethod='qris'">📲 QRIS</button>
                        <button class="pay-method-btn" :class="payMethod==='debit_card'?'active':''" @click="payMethod='debit_card'">💳 Debit</button>
                    </div>
                </div>
                <!-- Cash Tendered -->
                <div x-show="payMethod==='cash'" class="mb-3">
                    <label class="form-label">Uang Diterima (Cash)</label>
                    <input x-model.number="tendered" type="number" min="0" step="1000"
                        class="form-input" style="font-size:18px;font-weight:700" />
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-top:8px">
                        <button class="quick-cash-btn" @click="tendered=cartTotal()">Pas</button>
                        <button class="quick-cash-btn" @click="tendered=50000">50.000</button>
                        <button class="quick-cash-btn" @click="tendered=100000">100.000</button>
                        <button class="quick-cash-btn" @click="tendered=200000">200.000</button>
                    </div>
                    <div style="margin-top:12px;font-size:15px;font-weight:700;color:var(--clr-success)"
                         x-text="'Kembalian: Rp '+Math.max(0,(tendered||0)-cartTotal()).toLocaleString('id-ID')"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showPayModal=false">Batal</button>
                <button class="btn btn-success" :disabled="loading" @click="processPay()">
                    <span x-show="loading">⏳ Memproses...</span>
                    <span x-show="!loading">✅ Konfirmasi Lunas &amp; Cetak Struk</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL: Struk / Receipt Thermal Preview ═══════════════════════ -->
    <div x-show="showReceipt" class="receipt-preview-overlay" @click.self="showReceipt=false" x-cloak>
        <div>
            <div class="receipt-paper" id="receipt-print-area" :style="'--receipt-width:' + (localStorage.getItem('thermal_printer_paper') || '58mm')">
                <!-- Header Toko -->
                <div class="receipt-header">
                    <div style="font-size:24px">☕</div>
                    <div class="receipt-store-name" x-text="receiptStore.name || brandName"></div>
                    <template x-if="receiptStore.address">
                        <div class="receipt-store-info" x-text="receiptStore.address"></div>
                    </template>
                    <template x-if="receiptStore.phone">
                        <div class="receipt-store-info" x-text="'Telp: ' + receiptStore.phone"></div>
                    </template>
                </div>

                <hr class="receipt-divider">

                <!-- Info Order -->
                <div class="receipt-row"><span class="receipt-row-label">No. Order</span><span x-text="doneOrder?.order_number ?? '-'"></span></div>
                <template x-if="doneOrder?.queue_number">
                    <div class="receipt-row"><span class="receipt-row-label">Antrean</span><strong x-text="doneOrder.queue_number"></strong></div>
                </template>
                <div class="receipt-row"><span class="receipt-row-label">Kasir</span><span x-text="doneOrder?.cashier?.name ?? 'Kasir'"></span></div>
                <div class="receipt-row"><span class="receipt-row-label">Waktu</span><span x-text="doneOrder?.created_at ? new Date(doneOrder.created_at).toLocaleString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-'"></span></div>
                <div class="receipt-row"><span class="receipt-row-label">Tipe</span><span x-text="doneOrder?.order_type === 'takeaway' ? 'Takeaway' : 'Dine In'"></span></div>
                <template x-if="doneOrder?.table_number">
                    <div class="receipt-row"><span class="receipt-row-label">Meja</span><span x-text="doneOrder.table_number"></span></div>
                </template>

                <hr class="receipt-divider">

                <!-- Item Menu -->
                <template x-for="line in doneOrder?.lines" :key="line.id">
                    <div style="margin-bottom: 4px">
                        <div class="receipt-item-name" x-text="line.product_name"></div>
                        <div class="receipt-item-detail receipt-row">
                            <span x-text="line.qty + 'x  Rp ' + Number(line.unit_price||0).toLocaleString('id-ID')"></span>
                            <span x-text="'Rp ' + Number(line.subtotal||0).toLocaleString('id-ID')"></span>
                        </div>
                    </div>
                </template>

                <hr class="receipt-divider">

                <!-- Totals -->
                <div class="receipt-row"><span>Subtotal</span><span x-text="'Rp '+Number(doneOrder?.subtotal||0).toLocaleString('id-ID')"></span></div>
                <template x-if="Number(doneOrder?.discount_amount||0) > 0">
                    <div class="receipt-row" style="color:#dc2626">
                        <span>Diskon</span>
                        <span x-text="'- Rp '+Number(doneOrder?.discount_amount||0).toLocaleString('id-ID')"></span>
                    </div>
                </template>
                <template x-if="Number(doneOrder?.tax_amount||0) > 0">
                    <div class="receipt-row">
                        <span x-text="(tax.tax_name||'Pajak')+':'"></span>
                        <span x-text="tax.tax_type==='charged_to_hpp'?'Termasuk HPP':'Rp '+Number(doneOrder?.tax_amount||0).toLocaleString('id-ID')"></span>
                    </div>
                </template>

                <hr class="receipt-divider" style="border-top-style: double; border-top-width: 3px">
                <div class="receipt-row receipt-total-row">
                    <span>TOTAL</span><span x-text="'Rp '+Number(doneOrder?.total_amount||0).toLocaleString('id-ID')"></span>
                </div>
                <hr class="receipt-divider" style="border-top-style: double; border-top-width: 3px">

                <!-- Pembayaran -->
                <template x-if="doneOrder?.payment">
                    <div>
                        <div class="receipt-row"><span class="receipt-row-label">Bayar</span><span x-text="receiptPayMethodLabel(doneOrder.payment.method)"></span></div>
                        <template x-if="doneOrder.payment.method === 'cash'">
                            <div>
                                <div class="receipt-row"><span class="receipt-row-label">Diterima</span><span x-text="'Rp ' + Number(doneOrder.payment.tendered||0).toLocaleString('id-ID')"></span></div>
                                <div class="receipt-row"><span class="receipt-row-label">Kembali</span><span x-text="'Rp ' + Number(doneOrder.payment.change_amount||0).toLocaleString('id-ID')"></span></div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Catatan -->
                <template x-if="doneOrder?.notes">
                    <div style="margin-top:8px;font-size:11px;color:#555">
                        <hr class="receipt-divider">
                        <span>Catatan: </span><span x-text="doneOrder.notes"></span>
                    </div>
                </template>

                <!-- Footer -->
                <div class="receipt-footer">
                    <hr class="receipt-divider">
                    <div>Terima Kasih atas Kunjungan Anda</div>
                    <div x-text="receiptStore.website || 'www.ratiocoffee.my.id'"></div>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="receipt-actions">
                <button class="btn-print-browser" @click="tryPrintReceipt(doneOrder, true)">🖨️ Cetak Struk</button>
                <button class="btn-close-receipt" @click="closeReceipt()">Selesai &amp; Transaksi Baru</button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="{{ env('MIDTRANS_IS_PRODUCTION', false) ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>
<script>
function posKasir() {
    return {
        // state
        products: [], categories: [], cart: [],
        search: '', selCat: '',
        orderType: 'dine_in', tableNumber: '',
        discountType: 'percent',
        discountPercent: 0,
        discountNominal: 0,
        discountNote: '',
        showDiscountInput: false,
        pkg: { cup: 0, food: 0 },
        cupCost: 100, foodCost: 500,
        tax: {
            tax_name:'Pajak Resto (PB1)', tax_rate:0, is_enabled:false,
            tax_type:'charged_to_hpp', service_charge_name:'Service Charge',
            service_charge_rate:0, is_service_charge_enabled:false
        },
        loading: false,
        showPayModal: false, showReceipt: false,
        payMethod: 'cash', tendered: 0,
        doneOrder: null,
        brandName: localStorage.getItem('erp_brand_name') || 'RATIO COFFEE HQ',
        receiptStore: { name: '', address: '', phone: '', website: 'www.ratiocoffee.my.id' },
        printerStatus: {
            connected: Boolean(localStorage.getItem('thermal_printer_device_id')),
            name: localStorage.getItem('thermal_printer_device') || ''
        },

        async init() {
            this.loading = true;
            await Promise.all([
                this.fetchProducts(),
                this.fetchCategories(),
                this.fetchTax(),
                this.fetchPackaging(),
            ]);
            this.loading = false;

            // Sinkronisasi status printer real-time
            window.addEventListener('printer:status', (e) => {
                if (e.detail && e.detail.connected) {
                    this.printerStatus.connected = true;
                    this.printerStatus.name = e.detail.deviceName || localStorage.getItem('thermal_printer_device') || 'Printer';
                } else if (e.detail && !e.detail.connected) {
                    this.printerStatus.connected = false;
                    this.printerStatus.name = '';
                }
            });
            window.addEventListener('storage', () => {
                const id = localStorage.getItem('thermal_printer_device_id');
                this.printerStatus.connected = Boolean(id);
                this.printerStatus.name = localStorage.getItem('thermal_printer_device') || '';
            });
        },

        async fetchProducts() {
            try {
                const r = await axios.get('/api/v1/products?per_page=1000&available_for_pos=1&status=active');
                this.products = r.data.data ?? r.data ?? [];
            } catch(e) { console.error(e); }
        },

        async fetchCategories() {
            try {
                const r = await axios.get('/api/v1/product-categories');
                this.categories = r.data.data ?? r.data ?? [];
            } catch(e) {}
        },

        async fetchTax() {
            try {
                const r = await axios.get('/api/v1/tax-settings');
                const d = r.data?.data || r.data;
                if (d) this.tax = {
                    ...this.tax, ...d,
                    is_enabled: Boolean(d.is_enabled),
                    is_service_charge_enabled: Boolean(d.is_service_charge_enabled),
                    tax_rate: parseFloat(d.tax_rate)||0,
                    service_charge_rate: parseFloat(d.service_charge_rate)||0,
                };
            } catch(e) {}
        },

        async fetchPackaging() {
            try {
                const r = await axios.get('/api/v1/inventory/ingredients?per_page=100');
                const list = r.data.data ?? [];
                const cup  = list.find(i => i.name.toLowerCase().includes('cup'));
                const food = list.find(i => i.name.toLowerCase().includes('packaging') || i.name.toLowerCase().includes('box') || i.name.toLowerCase().includes('kotak'));
                if (cup)  { this.cupCost  = parseFloat(cup.recipe_unit_cost  || cup.average_cost  || 100); this.pkg.cup  = parseFloat(cup.current_stock  || 0); }
                if (food) { this.foodCost = parseFloat(food.recipe_unit_cost || food.average_cost || 500); this.pkg.food = parseFloat(food.current_stock || 0); }
            } catch(e) {}
        },

        get filteredProducts() {
            return this.products.filter(p => {
                const matchSearch = !this.search || p.name.toLowerCase().includes(this.search.toLowerCase());
                const matchCat = !this.selCat
                    || (this.selCat === '__bundling__' && p.is_bundling)
                    || (this.selCat !== '__bundling__' && String(p.category_id) === String(this.selCat));
                return matchSearch && matchCat;
            });
        },

        get calculatedDiscount() {
            const sub = this.cartSubtotal();
            if (sub <= 0) return 0;
            let amt = 0;
            if (this.discountType === 'percent') {
                const p = Math.min(100, Math.max(0, parseFloat(this.discountPercent) || 0));
                amt = (sub * p) / 100;
            } else {
                amt = Math.max(0, parseFloat(this.discountNominal) || 0);
            }
            return Math.min(amt, sub);
        },

        get effectiveDiscountPercent() {
            const sub = this.cartSubtotal();
            if (sub <= 0) return 0;
            if (this.discountType === 'percent') return parseFloat(this.discountPercent) || 0;
            return Math.round((this.calculatedDiscount / sub) * 100);
        },

        setDiscountPercent(p) {
            this.discountType = 'percent';
            this.discountPercent = p;
            this.discountNominal = 0;
        },

        setDiscountNominal(val) {
            this.discountType = 'nominal';
            this.discountNominal = val;
            this.discountPercent = 0;
        },

        clearDiscount() {
            this.discountPercent = 0;
            this.discountNominal = 0;
            this.discountNote = '';
            this.showDiscountInput = false;
        },

        isBev(p) {
            const cat = (p.category?.name || p.category_name || '').toLowerCase();
            return ['beverage','coffee','kopi','minuman','drink','tea','teh'].some(k => cat.includes(k));
        },

        addToCart(p) {
            const pName = (p.name || '').toLowerCase();
            const isIced = pName.includes('ice') || pName.includes('iced') || pName.includes('cold') || pName.includes('dingin') || pName.includes('es ');
            const defaultTemp = isIced ? 'iced' : 'hot';
            const existing = this.cart.find(i => i.product_id === p.id && i.order_type === this.orderType && i.temperature === defaultTemp);
            if (existing) { existing.qty++; return; }
            this.cart.push({
                product_id: p.id, name: p.name,
                is_beverage: this.isBev(p),
                unit_price: parseFloat(p.selling_price),
                qty: 1, notes: '',
                temperature: defaultTemp,
                order_type: this.orderType,
            });
        },

        updateQty(idx, delta) {
            this.cart[idx].qty += delta;
            if (this.cart[idx].qty <= 0) this.cart.splice(idx, 1);
        },

        clearCart() {
            this.cart = [];
            this.tableNumber = '';
            this.clearDiscount();
        },

        setOrderType(t) { this.orderType = t; this.cart.forEach(i => i.order_type = t); },

        cartSubtotal()     { return this.cart.reduce((s,i) => s + i.unit_price * i.qty, 0); },
        cartServiceCharge(){ if (!this.tax.is_service_charge_enabled) return 0; return Math.max(0, this.cartSubtotal() - this.calculatedDiscount) * ((this.tax.service_charge_rate||0)/100); },
        cartTax()          { if (!this.tax.is_enabled || ['included_in_price','charged_to_hpp'].includes(this.tax.tax_type)) return 0; return Math.max(0, this.cartSubtotal() - this.calculatedDiscount) * ((this.tax.tax_rate||0)/100); },
        cartTotal()        { return Math.max(0, this.cartSubtotal() - this.calculatedDiscount + this.cartServiceCharge() + this.cartTax()); },

        openPayModal() { this.tendered = this.cartTotal(); this.showPayModal = true; },

        async processPay() {
            this.loading = true;
            try {
                let noteParts = [];
                if (this.tableNumber) noteParts.push(`Meja: ${this.tableNumber}`);
                if (this.discountNote) noteParts.push(`Diskon (${this.effectiveDiscountPercent}%): ${this.discountNote}`);
                const fullNotes = noteParts.join(' | ') || undefined;

                const createRes = await axios.post('/api/v1/pos/orders', {
                    order_type: this.orderType,
                    table_number: this.tableNumber || undefined,
                    discount_amount: this.calculatedDiscount,
                    tax_amount: this.cartTax(),
                    notes: fullNotes,
                    items: this.cart.map(i => ({
                        product_id: i.product_id, qty: i.qty,
                        unit_price: i.unit_price, notes: i.notes||undefined,
                        temperature: i.temperature, order_type: i.order_type,
                    })),
                });
                const order = createRes.data.order;

                if (['qris', 'bank_transfer', 'debit_card', 'credit_card'].includes(this.payMethod)) {
                    // Get Midtrans Snap Token
                    const tokenRes = await axios.post(`/api/v1/pos/orders/${order.id}/midtrans-token`);
                    const snapToken = tokenRes.data.snap_token;
                    
                    this.showPayModal = false;
                    
                    window.snap.pay(snapToken, {
                        onSuccess: async (result) => {
                            this.doneOrder = order;
                            this.doneOrder.payment = { method: this.payMethod, tendered: this.cartTotal() };
                            await this.tryPrintReceipt(this.doneOrder);
                            this.clearCart();
                            await this.fetchPackaging();
                            toast('Pembayaran berhasil! Pesanan diproses. 🎉', 'success');
                        },
                        onPending: (result) => {
                            toast('Menunggu pembayaran pelanggan...', 'info');
                            this.clearCart();
                        },
                        onError: (result) => {
                            toast('Pembayaran gagal atau terjadi kesalahan.', 'error');
                        },
                        onClose: () => {
                            toast('Pop-up pembayaran ditutup sebelum selesai.', 'warning');
                        }
                    });
                } else {
                    // Manual Cash / Other payment
                    const payRes = await axios.post(`/api/v1/pos/orders/${order.id}/pay`, {
                        method: this.payMethod, tendered: this.tendered,
                    });
                    this.doneOrder = payRes.data.order;
                    this.showPayModal = false;

                    // Coba cetak via printer server, jika gagal tampilkan preview browser
                    await this.tryPrintReceipt(this.doneOrder);

                    this.clearCart();
                    await this.fetchPackaging();
                    toast('Transaksi berhasil! Pesanan masuk ke antrean barista. 🎉');
                }
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memproses transaksi.', 'error');
            } finally { this.loading = false; }
        },

        async tryPrintReceipt(order, manual = false) {
            this.doneOrder = order;
            this.showReceipt = true;
            
            if (manual) {
                await this.doPrint(order);
            }
        },

        async doPrint(ord) {
            if (!ord) return;
            const tpm = window.ThermalPrinterManager;
            const savedId = localStorage.getItem('thermal_printer_device_id');

            if (tpm && savedId) {
                try {
                    const res = await tpm.printReceipt(ord, this.receiptStore);
                    toast(res?.message || 'Struk berhasil dicetak ke printer thermal! 🖨️', 'success');
                    return;
                } catch (e) {
                    console.error('[KasirPrint] Error:', e);
                    toast('Gagal kirim ke printer: ' + e.message, 'warning');
                    return;
                }
            }

            // Fallback ke browser print jika belum mengatur printer thermal
            await this.$nextTick();
            window.print();
        },

        receiptPayMethodLabel(method) {
            const map = { cash:'Tunai (Cash)', qris:'QRIS', bank_transfer:'Transfer Bank', debit_card:'Kartu Debit', credit_card:'Kartu Kredit' };
            return map[method] || (method ? method.charAt(0).toUpperCase() + method.slice(1) : 'Cash');
        },

        browserPrint() {
            window.print();
        },

        closeReceipt() {
            this.showReceipt = false;
        },

        async processSaveBill() {
            this.loading = true;
            try {
                let noteParts = [];
                if (this.tableNumber) noteParts.push(`Meja: ${this.tableNumber}`);
                if (this.discountNote) noteParts.push(`Diskon (${this.effectiveDiscountPercent}%): ${this.discountNote}`);
                const fullNotes = noteParts.join(' | ') || undefined;

                const createRes = await axios.post('/api/v1/pos/orders', {
                    order_type: this.orderType,
                    table_number: this.tableNumber || undefined,
                    discount_amount: this.calculatedDiscount,
                    tax_amount: this.cartTax(),
                    notes: fullNotes,
                    items: this.cart.map(i => ({
                        product_id: i.product_id, qty: i.qty,
                        unit_price: i.unit_price, notes: i.notes||undefined,
                        temperature: i.temperature, order_type: i.order_type,
                    })),
                });
                this.clearCart();
                toast('Bill berhasil disimpan! Pesanan masuk ke antrean. 🎉');
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan bill.', 'error');
            } finally { this.loading = false; }
        },
    }
}
</script>
@endpush