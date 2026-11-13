@extends('layouts.erp')
@section('title', 'Antrean Pesanan Barista & KDS – Ratio Coffee ERP')

@push('styles')
<style>
/* ── Thermal Receipt Preview Modal ──────────────────────────────── */
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

/* ── @media print — hanya cetak area struk ──────────────────────── */
@media print {
    /* 1. Hilangkan header/footer bawaan browser (URL, Tanggal, dll) */
    @page { margin: 0; }
    html, body, .erp-layout, .main-content, .kds-container { 
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
    
    /* 5. Di dalam kontainer KDS, sembunyikan semua KECUALI struk */
    .kds-container > *:not(.receipt-preview-overlay) { display: none !important; }

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
<div x-data="posQueueApp()" x-init="init()" x-cloak class="kds-container">

    <!-- Top Page Header & Controls -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    ⏱️ Kitchen Display System (KDS) Live
                </h4>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5" style="font-size:11.5px">
                    <span class="spinner-grow spinner-grow-sm text-success" style="width:8px; height:8px" role="status"></span>
                    Live Sync
                </span>
            </div>
            <p class="text-muted small m-0 mt-1">Antrean pembuatan pesanan barista &amp; dapur terintegrasi secara real-time.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm fw-semibold d-inline-flex align-items-center gap-1.5 px-3 py-2"
                    :class="printerStatus.connected ? 'btn-outline-success text-success bg-success-subtle border-success' : 'btn-outline-secondary text-muted'"
                    style="border-radius:10px"
                    onclick="showPrinterSettingsModal()"
                    :title="printerStatus.connected ? 'Printer Terhubung: ' + printerStatus.name : 'Belum Ada Printer Dipilih'">
                <span x-text="printerStatus.connected ? '🟢' : '⚙️'"></span>
                <span x-text="printerStatus.connected ? ('🖨️ ' + (printerStatus.name || 'Printer Terhubung')) : 'Pengaturan Printer'"></span>
            </button>
            <button class="btn btn-secondary btn-sm fw-semibold d-inline-flex align-items-center gap-1.5 px-3 py-2" style="border-radius:10px" @click="load()" :disabled="loading">
                <span :class="loading ? 'spin' : ''">🔄</span> Refresh Antrean
            </button>
        </div>
    </div>

    <!-- Quick Metric Cards Overview -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-revenue">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper revenue-theme">🧾</span>
                    <span class="kpi-badge revenue-badge">Aktif</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Antrean Aktif</div>
                    <div class="kpi-val" x-text="countAll"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-profit">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper profit-theme">⏱️</span>
                    <span class="kpi-badge profit-badge">Proses Barista</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Sedang Diproses</div>
                    <div class="kpi-val text-warning" x-text="countPreparing"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-asset">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper asset-theme">✅</span>
                    <span class="kpi-badge asset-badge">Siap Saji</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Pesanan Selesai</div>
                    <div class="kpi-val text-success" x-text="countCompleted"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Segmented Filter Navigation Bar -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="kds-tab-bar">
            <button class="kds-tab-item" :class="{ 'active': activeTab === 'all' }" @click="activeTab = 'all'">
                <span>📌 Semua Antrean</span>
                <span class="kds-count-badge" x-text="countAll"></span>
            </button>
            <button class="kds-tab-item" :class="{ 'active': activeTab === 'preparing' }" @click="activeTab = 'preparing'">
                <span>⏳ Diproses Barista</span>
                <span class="kds-count-badge" x-text="countPreparing"></span>
            </button>
            <button class="kds-tab-item" :class="{ 'active': activeTab === 'completed' }" @click="activeTab = 'completed'">
                <span>✅ Pesanan Selesai</span>
                <span class="kds-count-badge" x-text="countCompleted"></span>
            </button>
        </div>
        <div class="text-muted small fw-medium ms-auto">
            <span>Menampilkan </span>
            <strong class="text-dark" x-text="filteredOrders.length"></strong>
            <span> pesanan</span>
        </div>
    </div>

    <!-- Orders Grid -->
    <div class="row g-3">
        <template x-if="loading && orders.length === 0">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
                <div class="text-muted fw-medium">Memuat antrean pesanan real-time...</div>
            </div>
        </template>

        <template x-if="!loading && filteredOrders.length === 0">
            <div class="col-12">
                <div class="card p-5 text-center shadow-sm" style="border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
                    <div style="font-size:56px" class="mb-3">☕</div>
                    <h5 class="fw-bold text-dark mb-2">Tidak Ada Antrean dalam Kategori Ini</h5>
                    <p class="text-muted small m-0 max-w-md mx-auto">Semua pesanan pelanggan pada kategori ini sudah bersih atau belum ada pesanan baru yang masuk.</p>
                </div>
            </div>
        </template>

        <template x-for="ord in filteredOrders" :key="ord.id">
            <div class="col-md-6 col-lg-4">
                <div class="kds-card"
                     :class="{
                        'kds-card-preparing': ord.status === 'preparing' || ord.status === 'processing',
                        'kds-card-completed': ord.status === 'completed',
                        'kds-card-pending': ord.status !== 'preparing' && ord.status !== 'completed'
                     }">
                    
                    <!-- Ticket Header: Queue Number & Real-time Timer -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="kds-ticket-num">
                            <span>🎫</span>
                            <span x-text="'Antrean #' + formatQueueNum(ord)"></span>
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge" :class="ord.payment ? 'bg-success text-white' : 'bg-danger text-white'" style="font-size:11px; padding:3px 7px; border-radius:6px; font-weight:600" x-text="ord.payment ? '✅ Lunas' : '⚠️ Belum Bayar'"></span>
                            <span class="kds-timer-pill" :class="getTimerClass(ord)">
                                ⏱️ <span x-text="getElapsedTime(ord)"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Order Meta Info -->
                    <div class="kds-meta-row">
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="kds-type-chip" x-text="ord.order_type === 'takeaway' ? '🛍️ Takeaway' : '🍽️ Dine In (' + (ord.table_number ? 'Meja ' + ord.table_number : 'Meja -') + ')'"></span>
                            <button class="btn btn-xs btn-outline-secondary py-0 px-2 fw-semibold" style="font-size:11.5px; border-radius:6px" @click="openEditModal(ord)">
                                ✏️ Edit Tipe
                            </button>
                            <button class="btn btn-xs btn-outline-primary py-0 px-2 fw-semibold" style="font-size:11.5px; border-radius:6px" @click="openAddItemModal(ord)">
                                ➕ Tambah Menu
                            </button>
                            <template x-if="ord.customer">
                                <span class="kds-customer-name" x-text="'• ' + ord.customer.name"></span>
                            </template>
                        </div>
                        <span class="kds-time-stamp" x-text="formatDate(ord.created_at, 'time')"></span>
                    </div>

                    <!-- Status Banner -->
                    <div class="kds-status-banner"
                         :class="{
                            'kds-status-preparing': ord.status === 'preparing' || ord.status === 'processing',
                            'kds-status-completed': ord.status === 'completed',
                            'kds-status-pending': ord.status !== 'preparing' && ord.status !== 'completed'
                         }">
                        <span x-text="(ord.status === 'preparing' || ord.status === 'processing') ? '⏳ SEDANG DIPROSES BARISTA' : (ord.status === 'completed' ? '✅ SELESAI DIPROSES' : '📋 MENUNGGU KONFIRMASI')"></span>
                    </div>

                    <!-- Menu Items List -->
                    <div class="kds-items-box">
                        <template x-for="item in (ord.lines || ord.items || [])" :key="item.id">
                            <div class="kds-item-row">
                                <div class="kds-item-header">
                                    <span class="kds-item-qty" x-text="'×' + item.qty"></span>
                                    <span class="kds-item-name" x-text="item.product_name"></span>
                                    <div class="d-flex align-items-center gap-1 ms-auto flex-shrink-0">
                                        <span class="badge" :class="item.order_type === 'takeaway' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-info-subtle text-info-emphasis border border-info-subtle'" style="font-size:11px; padding:3px 7px; border-radius:6px; font-weight:600" x-text="item.order_type === 'takeaway' ? '🛍️ Takeaway' : '🍽️ Dine In'"></span>
                                        <span class="kds-temp-chip" :class="(item.temperature === 'iced' || item.temperature === 'cold') ? 'kds-temp-iced' : 'kds-temp-hot'" x-text="(item.temperature === 'iced' || item.temperature === 'cold') ? '❄️ Ice' : '🔥 Hot'"></span>
                                    </div>
                                </div>
                                <template x-if="getCustomNotes(item.notes)">
                                    <div class="kds-item-note" x-text="'Catatan: ' + getCustomNotes(item.notes)"></div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <!-- Order Level Notes -->
                    <template x-if="ord.notes">
                        <div class="kds-order-note">
                            <span>📝</span>
                            <span x-text="'Catatan Pesanan: ' + ord.notes"></span>
                        </div>
                    </template>

                    <!-- Action Footer -->
                    <div class="mt-auto d-flex flex-column gap-2 pt-2 border-top">
                        <template x-if="!ord.payment && ord.status !== 'void' && ord.status !== 'cancelled'">
                            <button class="btn btn-primary btn-sm w-100 py-2.5 fw-bold text-white shadow-sm d-flex align-items-center justify-content-center gap-1.5"
                                    style="border-radius:10px;"
                                    @click="openPayModal(ord)">
                                <span>💰</span> Bayar (Verifikasi)
                            </button>
                        </template>

                        <template x-if="ord.status === 'preparing' || ord.status === 'processing' || ord.status === 'confirmed' || ord.status === 'pending'">
                            <button class="btn btn-success btn-sm w-100 py-2.5 fw-bold text-white shadow-sm d-flex align-items-center justify-content-center gap-1.5"
                                    style="border-radius:10px; background:linear-gradient(135deg, #10b981, #059669); border:none"
                                    @click="updateStatus(ord, 'completed')">
                                <span>✅</span> Tandai Selesai Diproses
                            </button>
                        </template>
                        <template x-if="ord.status === 'completed'">
                            <button class="btn btn-secondary btn-sm w-100 py-2 fw-semibold text-muted d-flex align-items-center justify-content-center gap-1.5"
                                    style="border-radius:10px"
                                    @click="updateStatus(ord, 'preparing')">
                                <span>↩️</span> Kembalikan ke Diproses
                            </button>
                        </template>

                        <!-- Print Receipt Action -->
                        <button class="btn btn-info btn-sm w-100 py-1.5 fw-semibold text-white d-flex align-items-center justify-content-center gap-1"
                                style="border-radius:8px; font-size:12px"
                                @click="printReceipt(ord)">
                            <span>🖨️</span> Cetak Struk
                        </button>

                        <!-- Cancel Order Action -->
                        <button class="btn btn-outline-danger btn-sm w-100 py-1.5 fw-semibold d-flex align-items-center justify-content-center gap-1"
                                style="border-radius:8px; font-size:12px"
                                @click="deleteOrder(ord)">
                            <span>❌</span> Batalkan Pesanan
                        </button>
                    </div>

                </div>
            </div>
        </template>
    </div>

    <!-- ══ MODAL: Edit Order Type & Varian Per Produk ═══════════════════ -->
    <div x-show="showEditModal" class="modal-overlay" @click.self="showEditModal=false" style="display:flex" x-cloak>
        <div class="modal-box" style="max-width:520px; width:92%">
            <div class="modal-header">
                <h3 style="margin:0;font-size:16px;font-weight:700" class="d-flex align-items-center gap-2">
                    ✏️ Edit Tipe & Varian Per Produk
                    <template x-if="editingOrder">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" x-text="'#' + formatQueueNum(editingOrder)"></span>
                    </template>
                </h3>
                <button class="btn btn-secondary btn-sm" @click="showEditModal=false">✕</button>
            </div>
            <div class="modal-body" style="max-height:65vh; overflow-y:auto">
                <p class="text-muted fs-7 mb-3">Atur tipe pesanan (Dine In / Takeaway) dan temperatur untuk setiap produk:</p>
                
                <div class="d-flex flex-column gap-3 mb-3">
                    <template x-for="(it, idx) in editItems" :key="it.id">
                        <div class="p-3 border rounded-3 bg-light-subtle shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold fs-6 text-dark" x-text="'×' + it.qty + ' ' + it.product_name"></span>
                            </div>

                            <div class="row g-2">
                                <!-- Order Type Switcher -->
                                <div class="col-6">
                                    <label class="form-label text-muted fs-7 mb-1 font-semibold">Tipe Pesanan</label>
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-sm fw-bold"
                                                :class="it.order_type==='dine_in'?'btn-primary':'btn-outline-secondary'"
                                                @click="it.order_type='dine_in'">
                                            🍽️ Dine In
                                        </button>
                                        <button type="button" class="btn btn-sm fw-bold"
                                                :class="it.order_type==='takeaway'?'btn-warning text-dark':'btn-outline-secondary'"
                                                @click="it.order_type='takeaway'">
                                            🛍️ Takeaway
                                        </button>
                                    </div>
                                </div>

                                <!-- Temperature Switcher -->
                                <div class="col-6">
                                    <label class="form-label text-muted fs-7 mb-1 font-semibold">Suhu / Temperatur</label>
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-sm fw-bold"
                                                :class="it.temperature==='hot'?'btn-danger':'btn-outline-secondary'"
                                                @click="it.temperature='hot'">
                                            🔥 Hot
                                        </button>
                                        <button type="button" class="btn btn-sm fw-bold"
                                                :class="(it.temperature==='iced'||it.temperature==='cold')?'btn-info text-white':'btn-outline-secondary'"
                                                @click="it.temperature='iced'">
                                            ❄️ Ice
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mb-2">
                    <label class="form-label font-bold">Nomor Meja (jika ada Dine In)</label>
                    <input x-model="editTableNumber" class="form-input" placeholder="mis. Meja 04" />
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showEditModal=false">Batal</button>
                <button class="btn btn-success fw-bold" :disabled="submitting" @click="saveOrderType()">
                    <span x-show="submitting">⏳ Menyimpan...</span>
                    <span x-show="!submitting">💾 Simpan Perubahan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL: Add Menu ══════════════════════════════════════════════ -->
    <div x-show="showAddItemModal" class="modal-overlay" @click.self="showAddItemModal=false" style="display:flex" x-cloak>
        <div class="modal-box" style="max-width:520px; width:92%">
            <div class="modal-header">
                <h3 style="margin:0;font-size:16px;font-weight:700" class="d-flex align-items-center gap-2">
                    ➕ Tambah Menu ke Antrean
                    <template x-if="addingItemOrder">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" x-text="'#' + formatQueueNum(addingItemOrder)"></span>
                    </template>
                </h3>
                <button class="btn btn-secondary btn-sm" @click="showAddItemModal=false">✕</button>
            </div>
            <div class="modal-body" style="max-height:65vh; overflow-y:auto">
                <div class="mb-3">
                    <label class="form-label font-bold">Pilih Produk</label>
                    <select class="form-select" x-model="addItemData.product_id">
                        <option value="">-- Pilih Produk --</option>
                        <template x-for="prod in products" :key="prod.id">
                            <option :value="prod.id" x-text="prod.name + ' - Rp ' + Number(prod.selling_price).toLocaleString('id-ID')"></option>
                        </template>
                    </select>
                </div>
                
                <div class="mb-3 row g-2">
                    <div class="col-6">
                        <label class="form-label font-bold">Qty</label>
                        <input type="number" class="form-input" x-model.number="addItemData.qty" min="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label font-bold">Suhu / Temperatur</label>
                        <select class="form-select" x-model="addItemData.temperature">
                            <option value="hot">🔥 Hot</option>
                            <option value="iced">❄️ Ice</option>
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label font-bold">Catatan Khusus</label>
                    <input type="text" class="form-input" x-model="addItemData.notes" placeholder="Contoh: Less sugar">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showAddItemModal=false">Batal</button>
                <button class="btn btn-primary fw-bold" :disabled="submittingAdd || !addItemData.product_id || addItemData.qty < 1" @click="submitAddItem()">
                    <span x-show="submittingAdd">⏳ Menambahkan...</span>
                    <span x-show="!submittingAdd">➕ Tambah ke Pesanan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL: Pembayaran ══════════════════════════════════════════════ -->
    <div x-show="showPayModal" class="modal-overlay" @click.self="showPayModal=false" style="display:flex" x-cloak>
        <div class="modal-box" style="max-width:440px">
            <div class="modal-header">
                <h3 style="margin:0;font-size:17px;font-weight:700">💳 Verifikasi Pembayaran</h3>
                <button class="btn btn-secondary btn-sm" @click="showPayModal=false">✕</button>
            </div>
            <div class="modal-body">
                <div style="text-align:center;margin-bottom:16px;padding:14px;background:var(--bg-muted);border-radius:8px">
                    <div style="font-size:11.5px;color:var(--txt-muted);font-weight:600">Total Tagihan (Nett):</div>
                    <div style="font-size:26px;font-weight:800;color:var(--clr-primary)"
                         x-text="'Rp '+(payOrder ? Number(payOrder.total_amount).toLocaleString('id-ID') : '0')"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Metode Pembayaran</label>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn-sm" :class="payMethod==='cash'?'btn-primary':'btn-secondary'" style="flex:1;font-weight:700" @click="payMethod='cash'">💵 Cash</button>
                        <button class="btn btn-sm" :class="payMethod==='qris'?'btn-primary':'btn-secondary'" style="flex:1;font-weight:700" @click="payMethod='qris'">📲 QRIS</button>
                        <button class="btn btn-sm" :class="payMethod==='debit_card'?'btn-primary':'btn-secondary'" style="flex:1;font-weight:700" @click="payMethod='debit_card'">💳 Debit</button>
                    </div>
                </div>
                <div x-show="payMethod==='cash'" class="mb-3">
                    <label class="form-label">Uang Diterima (Cash)</label>
                    <input x-model.number="tendered" type="number" min="0" step="1000" class="form-input" style="font-size:18px;font-weight:700" />
                    <div style="display:flex;gap:6px;margin-top:8px">
                        <button class="btn btn-sm btn-secondary" style="flex:1" @click="tendered=payOrder?Number(payOrder.total_amount):0">Pas</button>
                        <button class="btn btn-sm btn-secondary" style="flex:1" @click="tendered=50000">50.000</button>
                        <button class="btn btn-sm btn-secondary" style="flex:1" @click="tendered=100000">100.000</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showPayModal=false">Batal</button>
                <button class="btn btn-success fw-bold" :disabled="submittingPay" @click="processPay()">
                    <span x-show="submittingPay">⏳ Memproses...</span>
                    <span x-show="!submittingPay">✅ Konfirmasi Lunas</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ══ MODAL: Preview Struk Thermal (Browser Print Fallback) ═══════ -->
    <div x-show="showReceiptPreview" class="receipt-preview-overlay" @click.self="showReceiptPreview=false" x-cloak>
        <div>
            <div class="receipt-paper" id="receipt-print-area" :style="'--receipt-width:' + (localStorage.getItem('thermal_printer_paper') || '58mm')">
                <!-- Header Toko -->
                <div class="receipt-header">
                    <div class="receipt-store-name" x-text="receiptStore.name"></div>
                    <template x-if="receiptStore.address">
                        <div class="receipt-store-info" x-text="receiptStore.address"></div>
                    </template>
                    <template x-if="receiptStore.phone">
                        <div class="receipt-store-info" x-text="'Telp: ' + receiptStore.phone"></div>
                    </template>
                </div>

                <hr class="receipt-divider">

                <!-- Info Order -->
                <div class="receipt-row"><span class="receipt-row-label">No. Order</span><span x-text="receiptOrder?.order_number ?? '-'"></span></div>
                <template x-if="receiptOrder?.queue_number">
                    <div class="receipt-row"><span class="receipt-row-label">Antrean</span><strong x-text="receiptOrder.queue_number"></strong></div>
                </template>
                <div class="receipt-row"><span class="receipt-row-label">Kasir</span><span x-text="receiptOrder?.cashier?.name ?? 'Kasir'"></span></div>
                <div class="receipt-row"><span class="receipt-row-label">Waktu</span><span x-text="receiptOrder?.created_at ? formatDate(receiptOrder.created_at, 'datetime') : '-'"></span></div>
                <div class="receipt-row"><span class="receipt-row-label">Tipe</span><span x-text="receiptOrder?.order_type === 'takeaway' ? 'Takeaway' : 'Dine In'"></span></div>
                <template x-if="receiptOrder?.table_number">
                    <div class="receipt-row"><span class="receipt-row-label">Meja</span><span x-text="receiptOrder.table_number"></span></div>
                </template>
                <template x-if="receiptOrder?.customer?.name">
                    <div class="receipt-row"><span class="receipt-row-label">Customer</span><span x-text="receiptOrder.customer.name"></span></div>
                </template>

                <hr class="receipt-divider">

                <!-- Item Menu -->
                <template x-for="item in (receiptOrder?.lines || receiptOrder?.items || [])" :key="item.id">
                    <div style="margin-bottom: 4px">
                        <div class="receipt-item-name" x-text="item.product_name"></div>
                        <div class="receipt-item-detail receipt-row">
                            <span x-text="item.qty + 'x  Rp ' + Number(item.unit_price||0).toLocaleString('id-ID')"></span>
                            <span x-text="'Rp ' + Number(item.subtotal||0).toLocaleString('id-ID')"></span>
                        </div>
                    </div>
                </template>

                <hr class="receipt-divider">

                <!-- Totals -->
                <div class="receipt-row"><span>Subtotal</span><span x-text="'Rp ' + Number(receiptOrder?.subtotal||0).toLocaleString('id-ID')"></span></div>
                <template x-if="Number(receiptOrder?.discount_amount||0) > 0">
                    <div class="receipt-row" style="color:#dc2626"><span>Diskon</span><span x-text="'- Rp ' + Number(receiptOrder?.discount_amount||0).toLocaleString('id-ID')"></span></div>
                </template>
                <template x-if="Number(receiptOrder?.tax_amount||0) > 0">
                    <div class="receipt-row"><span>Pajak</span><span x-text="'Rp ' + Number(receiptOrder?.tax_amount||0).toLocaleString('id-ID')"></span></div>
                </template>

                <hr class="receipt-divider" style="border-top-style: double; border-top-width: 3px">
                <div class="receipt-row receipt-total-row"><span>TOTAL</span><span x-text="'Rp ' + Number(receiptOrder?.total_amount||0).toLocaleString('id-ID')"></span></div>
                <hr class="receipt-divider" style="border-top-style: double; border-top-width: 3px">

                <!-- Pembayaran -->
                <template x-if="receiptOrder?.payment">
                    <div>
                        <div class="receipt-row"><span class="receipt-row-label">Bayar</span><span x-text="receiptPayMethodLabel(receiptOrder.payment.method)"></span></div>
                        <template x-if="receiptOrder.payment.method === 'cash'">
                            <div>
                                <div class="receipt-row"><span class="receipt-row-label">Diterima</span><span x-text="'Rp ' + Number(receiptOrder.payment.tendered||0).toLocaleString('id-ID')"></span></div>
                                <div class="receipt-row"><span class="receipt-row-label">Kembali</span><span x-text="'Rp ' + Number(receiptOrder.payment.change_amount||0).toLocaleString('id-ID')"></span></div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Catatan -->
                <template x-if="receiptOrder?.notes">
                    <div style="margin-top:8px;font-size:11px;color:#555">
                        <hr class="receipt-divider">
                        <span>Catatan: </span><span x-text="receiptOrder.notes"></span>
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
                <button class="btn-print-browser" @click="browserPrint()">🖨️ Cetak Struk</button>
                <button class="btn-close-receipt" @click="showReceiptPreview=false">✕ Tutup</button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function posQueueApp() {
    return {
        loading: false,
        activeTab: 'all',
        orders: [],
        nowTime: Date.now(),
        timerInterval: null,

        showEditModal: false,
        editingOrder: null,
        editTableNumber: '',
        editItems: [],
        submitting: false,

        showPayModal: false,
        payOrder: null,
        payMethod: 'cash',
        tendered: 0,
        submittingPay: false,

        showAddItemModal: false,
        addingItemOrder: null,
        products: [],
        addItemData: {
            product_id: '',
            qty: 1,
            temperature: 'iced',
            notes: ''
        },
        submittingAdd: false,

        // Receipt Preview State
        showReceiptPreview: false,
        receiptOrder: null,
        receiptStore: { name: 'RATIO COFFEE', address: '', phone: '', website: 'www.ratiocoffee.my.id' },
        printerStatus: {
            connected: Boolean(localStorage.getItem('thermal_printer_device_id')),
            name: localStorage.getItem('thermal_printer_device') || ''
        },

        async init() {
            await Promise.all([this.load(), this.loadProducts()]);
            this.timerInterval = setInterval(() => {
                this.nowTime = Date.now();
            }, 1000);
            setInterval(() => this.load(true), 8000);

            // Dengarkan sinkronisasi status printer (event lokal, tab lain & wake)
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

        async loadProducts() {
            try {
                const r = await axios.get('/api/v1/products?per_page=1000&status=active');
                this.products = r.data.data ?? r.data ?? [];
            } catch(e) {
                console.error(e);
            }
        },

        async load(silent = false) {
            if (!silent) this.loading = true;
            try {
                const r = await axios.get('/api/v1/pos/orders?per_page=100');
                const rawOrders = r.data.data ?? r.data ?? [];
                // Sort newest orders to the far left (created_at DESC)
                this.orders = rawOrders.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            } catch(e) {
                console.error(e);
            } finally {
                if (!silent) this.loading = false;
            }
        },

        get filteredOrders() {
            if (this.activeTab === 'preparing') {
                return this.orders.filter(o => ['preparing', 'processing', 'pending', 'confirmed'].includes(o.status));
            } else if (this.activeTab === 'completed') {
                return this.orders.filter(o => o.status === 'completed');
            }
            return this.orders.filter(o => o.status !== 'void' && o.status !== 'cancelled');
        },

        get countAll() {
            return this.orders.filter(o => o.status !== 'void' && o.status !== 'cancelled').length;
        },
        get countPreparing() {
            return this.orders.filter(o => ['preparing', 'processing', 'pending', 'confirmed'].includes(o.status)).length;
        },
        get countCompleted() {
            return this.orders.filter(o => o.status === 'completed').length;
        },

        formatQueueNum(ord) {
            if (ord.queue_number) {
                if (/^A\d{5}$/.test(ord.queue_number)) return ord.queue_number;
                const numOnly = ord.queue_number.replace(/\D/g, '');
                if (numOnly) return 'A' + numOnly.padStart(5, '0');
            }
            return 'A' + String(ord.id || 1).padStart(5, '0');
        },

        getElapsedTime(ord) {
            if (!ord.created_at) return '00m 00s';
            const start = new Date(ord.created_at).getTime();
            let end = this.nowTime;
            if (ord.status === 'completed' && (ord.completed_at || ord.updated_at)) {
                end = new Date(ord.completed_at || ord.updated_at).getTime();
            }
            const diffSec = Math.max(0, Math.floor((end - start) / 1000));
            const m = Math.floor(diffSec / 60);
            const s = diffSec % 60;
            return `${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
        },

        getTimerClass(ord) {
            if (ord.status === 'completed') return 'kds-timer-completed';
            const start = new Date(ord.created_at).getTime();
            const diffMin = Math.floor((this.nowTime - start) / 60000);
            if (diffMin >= 10) return 'kds-timer-danger';
            if (diffMin >= 5) return 'kds-timer-warning';
            return 'kds-timer-normal';
        },

        async updateStatus(ord, status) {
            try {
                await axios.post(`/api/v1/pos/orders/${ord.id}/update-status`, { status });
                toast('Status pesanan berhasil diperbarui!');
                await this.load(true);
            } catch(e) {
                toast('Gagal memperbarui status pesanan', 'error');
            }
        },

        async deleteOrder(ord) {
            const qNum = this.formatQueueNum(ord);
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/pos/orders/${ord.id}`);
                    toast(`Pesanan Antrean #${qNum} berhasil dibatalkan.`);
                    await this.load(true);
                } catch(e) {
                    toast('Gagal menghapus pesanan', 'error');
                }
            }, `Batalkan & Hapus Antrean #${qNum}`);
        },



        async printReceipt(ord) {
            this.receiptOrder = ord;
            this.showReceiptPreview = true;
        },

        async doPrint(ord) {
            if (!ord) return;
            const tpm = window.ThermalPrinterManager;
            const savedId = localStorage.getItem('thermal_printer_device_id');

            // Jika kasir sudah mengatur printer thermal (USB, Bluetooth, atau Bridge)
            if (tpm && savedId) {
                try {
                    const res = await tpm.printReceipt(ord, this.receiptStore);
                    toast(res?.message || 'Struk berhasil dicetak ke printer thermal! 🖨️', 'success');
                    return;
                } catch (e) {
                    console.error('[QueuePrint] Error:', e);
                    toast('Gagal kirim ke printer: ' + e.message, 'warning');
                    return;
                }
            }

            // Fallback ke browser print jika belum ada printer thermal yang dipilih
            await this.$nextTick();
            window.print();
        },

        receiptPayMethodLabel(method) {
            const map = { cash:'Tunai (Cash)', qris:'QRIS', bank_transfer:'Transfer Bank', debit_card:'Kartu Debit', credit_card:'Kartu Kredit' };
            return map[method] || (method ? method.charAt(0).toUpperCase() + method.slice(1) : 'Cash');
        },

        browserPrint() {
            this.doPrint(this.receiptOrder);
        },

        openEditModal(ord) {
            this.editingOrder = ord;
            this.editTableNumber = ord.table_number || '';
            this.editItems = (ord.lines || ord.items || []).map(line => ({
                id: line.id,
                product_name: line.product_name,
                qty: line.qty,
                order_type: line.order_type || ord.order_type || 'dine_in',
                temperature: line.temperature || 'hot'
            }));
            this.showEditModal = true;
        },

        async saveOrderType() {
            if (!this.editingOrder) return;
            this.submitting = true;
            try {
                await axios.put(`/api/v1/pos/orders/${this.editingOrder.id}/update-type`, {
                    table_number: this.editTableNumber || null,
                    items: this.editItems.map(i => ({
                        id: i.id,
                        order_type: i.order_type,
                        temperature: i.temperature
                    }))
                });
                toast('Tipe & varian pesanan per produk berhasil diperbarui!');
                this.showEditModal = false;
                await this.load(true);
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memperbarui tipe pesanan', 'error');
            } finally {
                this.submitting = false;
            }
        },

        getCustomNotes(notes) {
            if (!notes) return '';
            const clean = notes.replace(/\[(Takeaway|Dine In|Hot|Iced|Cold)\]/gi, '').trim();
            return clean;
        },

        openPayModal(ord) {
            this.payOrder = ord;
            this.payMethod = 'cash';
            this.tendered = ord.total_amount;
            this.showPayModal = true;
        },

        async processPay() {
            if (!this.payOrder) return;
            this.submittingPay = true;
            try {
                const payRes = await axios.post(`/api/v1/pos/orders/${this.payOrder.id}/pay`, {
                    method: this.payMethod, tendered: this.tendered,
                });
                toast('Pembayaran berhasil diproses dan disahkan!');
                this.showPayModal = false;
                await this.load(true);
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memproses pembayaran.', 'error');
            } finally {
                this.submittingPay = false;
            }
        },

        async openAddItemModal(ord) {
            this.addingItemOrder = ord;
            this.addItemData = {
                product_id: '',
                qty: 1,
                temperature: 'iced',
                notes: ''
            };
            await this.loadProducts();
            this.showAddItemModal = true;
        },

        async submitAddItem() {
            if (!this.addingItemOrder || !this.addItemData.product_id) return;
            this.submittingAdd = true;
            try {
                const payload = {
                    items: [
                        {
                            product_id: this.addItemData.product_id,
                            qty: this.addItemData.qty,
                            temperature: this.addItemData.temperature,
                            notes: this.addItemData.notes
                        }
                    ]
                };
                await axios.post(`/api/v1/pos/orders/${this.addingItemOrder.id}/add-items`, payload);
                toast('Menu berhasil ditambahkan ke pesanan!');
                this.showAddItemModal = false;
                await this.load(true);
            } catch (e) {
                toast(e.response?.data?.message || 'Gagal menambahkan menu.', 'error');
            } finally {
                this.submittingAdd = false;
            }
        },

        formatDate
    }
}
</script>
@endpush
