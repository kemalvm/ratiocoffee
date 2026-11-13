@extends('layouts.erp')
@section('title', 'Dashboard Employee – Ratio Coffee ERP')

@section('page-content')
<div x-data="employeeDashApp()" x-init="init()" x-cloak>
    <!-- Welcome Header -->
    <div class="welcome-banner mb-4">
        <div class="welcome-info">
            <div class="welcome-badge">
                <span class="live-indicator"><span class="pulse-dot"></span> SHIFT BARISTA &amp; KASIR LIVE</span>
                <span class="dot-sep">•</span>
                <span x-text="todayDate"></span>
            </div>
            <h2 class="welcome-title">Halo, <span x-text="user?.name || 'Staff'"></span>! ☕</h2>
            <p class="welcome-sub">Selamat bertugas! Pantau antrean pesanan kasir, racikan barista, dan ketersediaan stok bahan baku toko hari ini.</p>
        </div>
        <div class="welcome-summary">
            <div class="summary-box">
                <div class="summary-label">TRANSAKSI HARI INI</div>
                <div class="summary-val" x-text="data?.kpi?.today?.orders || 0"></div>
            </div>
            <div class="summary-box">
                <div class="summary-label">STATUS SHIFT</div>
                <div class="summary-val status-green">AKTIF</div>
            </div>
        </div>
    </div>

    <!-- Quick Access Buttons -->
    <div class="quick-actions-bar mb-4">
        <div class="quick-action-title">🚀 OPERATIONAL QUICK ACCESS:</div>
        <div class="quick-action-buttons">
            <a href="/pos/kasir" class="qa-btn primary-qa"><span>💳</span> Buka POS Kasir</a>
            <a href="/pos/queue" class="qa-btn"><span>⏱️</span> Antrean Barista (KDS)</a>
            <a href="/inventory" class="qa-btn"><span>📦</span> Cek Stok Inventory</a>
            <a href="/products" class="qa-btn"><span>🍵</span> Katalog Menu</a>
        </div>
    </div>

    <!-- Shift Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-card-revenue">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper revenue-theme">🧾</span>
                    <span class="kpi-badge revenue-badge">Shift Live</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Transaksi Kasir</div>
                    <div class="kpi-val" x-text="data?.kpi?.today?.orders || 0"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-profit">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper profit-theme">⏱️</span>
                    <span class="kpi-badge profit-badge">KDS Barista</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Pesanan Sedang Diproses</div>
                    <div class="kpi-val text-warning" x-text="data?.queue?.preparing || 0"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-asset">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper asset-theme">✅</span>
                    <span class="kpi-badge asset-badge">Selesai</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Pesanan Selesai Hari Ini</div>
                    <div class="kpi-val text-success" x-text="data?.queue?.completed || 0"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-expense">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper expense-theme">⚠️</span>
                    <span class="kpi-badge expense-badge">Peringatan</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Bahan Baku Stok Kritis</div>
                    <div class="kpi-val text-danger" x-text="data?.stock_alerts?.length || 0"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live KDS Preview & Stock Alerts -->
    <div class="row g-3">
        <!-- Stock Alerts Checklist -->
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0">⚠️ Peringatan Stok Bahan Baku</h5>
                    <a href="/inventory" class="btn btn-secondary btn-sm">Lihat Inventory →</a>
                </div>
                <div style="max-height:300px;overflow-y:auto">
                    <template x-if="data?.stock_alerts?.length">
                        <template x-for="item in data.stock_alerts" :key="item.id">
                            <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold" x-text="item.name"></span>
                                    <small class="text-muted d-block" x-text="'Min Stok: ' + item.min_stock"></small>
                                </div>
                                <span class="badge badge-danger" x-text="'Sisa: ' + item.stock"></span>
                            </div>
                        </template>
                    </template>
                    <div x-show="!data?.stock_alerts?.length" class="text-center py-4 text-muted">
                        ✅ Seluruh stok bahan baku dalam kondisi aman.
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0">🧾 Transaksi Kasir Terakhir</h5>
                    <a href="/pos/kasir" class="btn btn-primary btn-sm">Buka Kasir →</a>
                </div>
                <div style="max-height:300px;overflow-y:auto">
                    <template x-if="data?.recent_transactions?.length">
                        <template x-for="tx in data.recent_transactions" :key="tx.id">
                            <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="order-badge" x-text="'#' + tx.order_number"></span>
                                    <small class="text-muted ms-2" x-text="formatDate(tx.created_at, 'time')"></small>
                                </div>
                                <span class="fw-bold text-primary" x-text="'Rp ' + formatNumber(tx.total_amount)"></span>
                            </div>
                        </template>
                    </template>
                    <div x-show="!data?.recent_transactions?.length" class="text-center py-4 text-muted">
                        Belum ada transaksi di shift ini.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function employeeDashApp() {
    return {
        loading: false, data: null,
        user: JSON.parse(localStorage.getItem('erp_user') || '{}'),
        todayDate: new Date().toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }),

        async init() {
            this.loading = true;
            try {
                const r = await axios.get('/api/v1/dashboard/ceo');
                this.data = r.data;
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        formatNumber, formatDate
    }
}
</script>
@endpush
