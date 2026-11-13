@extends('layouts.erp')
@section('title', 'Dashboard – Ratio Coffee ERP')

@section('page-content')
<div x-data="dashboardCeo()" x-init="init()" x-cloak>

    <!-- Welcome Banner -->
    <div class="welcome-banner mb-4">
        <div class="welcome-info">
            <div class="welcome-badge">
                <span class="live-indicator"><span class="pulse-dot"></span> HQ CONTROL CENTER</span>
                <span class="dot-sep">•</span>
                <span x-text="(brand?.brand_name || brand?.name || $parent?.brand?.name || 'RATIO COFFEE').toUpperCase() + ' (' + (brand?.operating_hours_open || brand?.operatingHoursOpen || $parent?.brand?.operating_hours_open || '10:00') + ' - ' + (brand?.operating_hours_close || brand?.operatingHoursClose || $parent?.brand?.operating_hours_close || '22:00') + ')'"></span>
            </div>
            <h2 class="welcome-title" x-text="roleDashTitle()"></h2>
            <p class="welcome-sub">
                Hari ini <span x-text="todayDate"></span>. Total
                <strong style="color:#fff" x-text="data?.kpi?.today?.orders ?? 0"></strong> transaksi,
                pendapatan <strong style="color:var(--clr-primary-light)" x-text="'Rp ' + formatNumber(data?.kpi?.today?.revenue ?? 0)"></strong>.
            </p>
        </div>
        <div class="welcome-summary">
            <div class="summary-box">
                <div class="summary-label">REVENUE HARI INI</div>
                <div class="summary-val" x-text="'Rp ' + formatNumber(data?.kpi?.today?.revenue ?? 0)"></div>
            </div>
            <div class="summary-box">
                <div class="summary-label">GROSS MARGIN</div>
                <div class="summary-val status-green" x-text="(data?.kpi?.month?.margin ?? 42) + '%'"></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions (Hidden for Partner) -->
    <div class="quick-actions-bar mb-4" x-show="user?.role?.slug !== 'partner'">
        <div class="quick-action-title">🚀 QUICK ACCESS:</div>
        <div class="quick-action-buttons">
            <a href="/products" class="qa-btn"><span>➕</span> Tambah Produk</a>
            <a href="/inventory" class="qa-btn"><span>📦</span> Tambah Inventory</a>
            <a href="/pos/kasir" class="qa-btn primary-qa"><span>💳</span> POS Kasir (Live)</a>
            <a href="/suppliers" class="qa-btn"><span>🏢</span> Tambah Supplier</a>
            <a href="/users" class="qa-btn"><span>👤</span> Tambah Karyawan</a>
            <a href="/finance" class="qa-btn"><span>💸</span> Catat Expense</a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="section-heading mb-3">
        <h3 class="section-title">📊 Key Performance Indicators (KPI)</h3>
        <span class="section-sub">Ringkasan performa finansial &amp; operasional toko secara real-time</span>
    </div>
    <div class="kpi-grid mb-4">
        <div class="kpi-card kpi-card-revenue">
            <div class="kpi-card-glow"></div>
            <div class="kpi-header">
                <span class="kpi-icon-wrapper revenue-theme">💰</span>
                <span class="kpi-badge revenue-badge" x-text="(data?.kpi?.today?.orders ?? 0) + ' Transaksi'"></span>
            </div>
            <div class="kpi-body">
                <div class="kpi-label">Revenue Hari Ini</div>
                <div class="kpi-val" x-text="'Rp ' + formatNumber(data?.kpi?.today?.revenue ?? 0)"></div>
            </div>
        </div>
        <div class="kpi-card kpi-card-profit">
            <div class="kpi-card-glow"></div>
            <div class="kpi-header">
                <span class="kpi-icon-wrapper profit-theme">📈</span>
                <span class="kpi-badge profit-badge" x-text="'Margin ' + (data?.kpi?.today?.margin ?? 0) + '%'"></span>
            </div>
            <div class="kpi-body">
                <div class="kpi-label">Gross Profit Hari Ini</div>
                <div class="kpi-val" x-text="'Rp ' + formatNumber(data?.kpi?.today?.profit ?? 0)"></div>
            </div>
        </div>
        <div class="kpi-card kpi-card-expense">
            <div class="kpi-card-glow"></div>
            <div class="kpi-header">
                <span class="kpi-icon-wrapper expense-theme">💸</span>
                <span class="kpi-badge expense-badge">Inventory &amp; Expenses</span>
            </div>
            <div class="kpi-body">
                <div class="kpi-label">Pengeluaran Bulan Ini</div>
                <div class="kpi-val" x-text="'Rp ' + formatNumber(data?.kpi?.month?.expense ?? 0)"></div>
            </div>
        </div>
        <div class="kpi-card kpi-card-asset">
            <div class="kpi-card-glow"></div>
            <div class="kpi-header">
                <span class="kpi-icon-wrapper asset-theme">📦</span>
                <span class="kpi-badge asset-badge">Aset Inventori</span>
            </div>
            <div class="kpi-body">
                <div class="kpi-label">Nilai Persediaan Bahan Baku</div>
                <div class="kpi-val" x-text="'Rp ' + formatNumber(data?.kpi?.month?.inventory ?? 0)"></div>
            </div>
        </div>
    </div>

    <!-- Average Stats Card -->
    <div class="chart-card mb-4" x-show="user?.role?.slug !== 'partner'">
        <div class="chart-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
                <h4 class="chart-title">📊 Rata-rata Transaksi &amp; Pendapatan</h4>
                <div class="chart-sub">Kalkulasi rata-rata pergerakan bisnis berdasarkan data historis</div>
            </div>
            <div class="trend-filter-pills">
                <button type="button" class="trend-pill-btn" :class="avgFilter==='week' ? 'active' : ''" @click="changeAvgFilter('week')">Per Minggu</button>
                <button type="button" class="trend-pill-btn" :class="avgFilter==='month' ? 'active' : ''" @click="changeAvgFilter('month')">Per Bulan</button>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:20px;padding:20px 0;">
            <div style="background:rgba(234, 88, 12, 0.05);padding:24px;border-radius:12px;border:1px solid rgba(234, 88, 12, 0.2);text-align:center;">
                <div style="font-size:14px;color:var(--txt-muted);margin-bottom:8px;font-weight:600;">Rata-rata Transaksi</div>
                <div style="font-size:32px;font-weight:800;color:#ea580c;" x-text="formatNumber(avgStats?.average_transactions ?? 0) + ' Order'"></div>
            </div>
            <div style="background:rgba(16, 185, 129, 0.05);padding:24px;border-radius:12px;border:1px solid rgba(16, 185, 129, 0.2);text-align:center;">
                <div style="font-size:14px;color:var(--txt-muted);margin-bottom:8px;font-weight:600;">Rata-rata Pendapatan</div>
                <div style="font-size:32px;font-weight:800;color:#10b981;" x-text="'Rp ' + formatNumber(avgStats?.average_revenue ?? 0)"></div>
            </div>
        </div>
    </div>

    <!-- Revenue Trend Chart -->
    <div class="chart-card mb-4">
        <div class="chart-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
                <h4 class="chart-title">📈 Tren Revenue &amp; Profit</h4>
                <div class="chart-sub">Perbandingan pendapatan kotor vs profit bersih</div>
            </div>
            <div class="trend-filter-pills">
                <template x-for="opt in rangeOptions" :key="opt.value">
                    <button type="button" class="trend-pill-btn" :class="selectedRange===opt.value ? 'active' : ''"
                        @click="changeRange(opt.value)" x-text="opt.label"></button>
                </template>
            </div>
        </div>
        <div class="chart-container" style="position:relative;height:300px;width:100%;padding:10px 0">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Two-column Charts -->
    <div class="charts-grid-two mb-4">
        <!-- Top Products Bar -->
        <div class="chart-card">
            <div class="chart-header">
                <h4 class="chart-title">☕ Top 5 Produk Terlaris</h4>
                <div class="chart-sub">Menu paling diminati berdasarkan total unit terjual</div>
            </div>
            <div class="chart-container" style="position:relative;height:260px;padding:16px">
                <canvas id="productsChart"></canvas>
            </div>
        </div>
        <!-- Sold Products List -->
        <div class="chart-card">
            <div class="chart-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <div>
                    <h4 class="chart-title">📦 Keseluruhan Produk Terjual</h4>
                    <div class="chart-sub">Rincian seluruh item menu yang berhasil dijual</div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="badge badge-info" style="font-size:10px;font-weight:700" x-text="soldProducts.length + ' Item'"></span>
                    <div style="display:flex;gap:4px;align-items:center">
                        <input type="date" x-model="soldStartDate" class="form-control" style="padding:4px 8px;font-size:12px;height:auto;width:auto;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--txt-main);" @change="fetchSoldProducts">
                        <span style="font-size:12px;color:var(--txt-muted)">s/d</span>
                        <input type="date" x-model="soldEndDate" class="form-control" style="padding:4px 8px;font-size:12px;height:auto;width:auto;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--txt-main);" @change="fetchSoldProducts">
                    </div>
                </div>
            </div>
            <div style="height:260px;overflow-y:auto">
                <template x-if="soldProducts.length">
                    <div>
                        <template x-for="(p,i) in soldProducts" :key="i">
                            <div class="sold-product-item">
                                <div class="sold-prod-info">
                                    <span class="sold-rank" x-text="'#'+(i+1)"></span>
                                    <div>
                                        <div class="sold-prod-name" x-text="p.product_name"></div>
                                        <div class="sold-prod-sub" x-text="'Revenue: Rp ' + formatNumber(p.total_sales)"></div>
                                    </div>
                                </div>
                                <span class="qty-badge" x-text="formatNumber(p.total_qty) + ' terjual'"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <div x-show="!soldProducts.length" class="chart-empty" style="height:260px">📦 Belum ada produk terjual pada periode ini.</div>
            </div>
        </div>
    </div>

    <!-- Category & Payment -->
    <div class="charts-grid-two mb-4">
        <div class="chart-card">
            <div class="chart-header">
                <h4 class="chart-title">🍕 Penjualan Per Kategori Menu</h4>
                <div class="chart-sub">Proporsi kontribusi pendapatan per kategori</div>
            </div>
            <div class="chart-container" style="position:relative;height:260px;padding:16px">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <div>
                    <h4 class="chart-title">💳 Metode Pembayaran Kasir</h4>
                    <div class="chart-sub">Pecahan transaksi Cash, QRIS, Transfer, &amp; EDC</div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="badge badge-info" style="font-size:10px;font-weight:700" x-text="pmTotal + ' Transaksi'"></span>
                    <div style="display:flex;gap:4px;align-items:center">
                        <input type="date" x-model="pmStartDate" class="form-control" style="padding:4px 8px;font-size:12px;height:auto;width:auto;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--txt-main);" @change="fetchPaymentMethods">
                        <span style="font-size:12px;color:var(--txt-muted)">s/d</span>
                        <input type="date" x-model="pmEndDate" class="form-control" style="padding:4px 8px;font-size:12px;height:auto;width:auto;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--txt-main);" @change="fetchPaymentMethods">
                    </div>
                </div>
            </div>
            <div style="padding:10px 0">
                <template x-if="pmList.length">
                    <div class="payment-methods-list">
                        <template x-for="pm in pmList" :key="pm.method">
                            <div class="pm-list-item">
                                <div class="pm-info">
                                    <span class="pm-icon" x-text="getPaymentIcon(pm.method)"></span>
                                    <div>
                                        <div class="pm-name" x-text="pm.method"></div>
                                        <div class="pm-count" x-text="(pm.count??0) + ' Transaksi'"></div>
                                    </div>
                                </div>
                                <div class="pm-total-badge" x-text="'Rp ' + formatNumber(pm.total)"></div>
                            </div>
                        </template>
                    </div>
                </template>
                <div x-show="!pmList.length" class="chart-empty" style="height:200px">💳 Belum ada data metode pembayaran.</div>
            </div>
        </div>
    </div>

    <!-- Queue + Insights (Hidden for Partner) -->
    <div class="ops-grid mb-4" x-show="user?.role?.slug !== 'partner'">
        <div class="ops-card">
            <div class="ops-header">
                <h4 class="ops-title">⏳ Antrean Dapur &amp; Barista (KDS Live)</h4>
                <span class="badge badge-info pulse-animation">Real-time</span>
            </div>
            <div class="queue-status-boxes">
                <div class="q-box preparing"><div class="q-val" x-text="data?.queue?.preparing??0"></div><div class="q-lbl">⏳ Dipproses Barista</div></div>
                <div class="q-box completed"><div class="q-val" x-text="data?.queue?.completed??0"></div><div class="q-lbl">✅ Selesai Hari Ini</div></div>
                <div class="q-box waiting"><div class="q-val" x-text="data?.queue?.total??0"></div><div class="q-lbl">🧾 Total Antrean</div></div>
                <div class="q-box ready"><div class="q-val" x-text="(data?.queue?.completion_rate??0)+'%'"></div><div class="q-lbl">⚡ Rate Selesai</div></div>
            </div>
        </div>
        <div class="ops-card">
            <div class="ops-header">
                <h4 class="ops-title">🤖 Ratio Business Intelligence Insights</h4>
                <span class="badge badge-success">Automated AI</span>
            </div>
            <div class="insights-list">
                <template x-if="data?.insights?.length">
                    <template x-for="(ins,i) in data.insights" :key="i">
                        <div class="insight-item">
                            <span class="insight-icon" x-text="ins.icon"></span>
                            <div class="insight-text" x-text="ins.text"></div>
                        </div>
                    </template>
                </template>
                <div x-show="!data?.insights?.length" class="insight-item">
                    <span class="insight-icon">✨</span>
                    <div class="insight-text">Seluruh indikator operasional toko berjalan normal &amp; optimal.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Grid (Hidden for Partner) -->
    <div class="tables-grid" x-show="user?.role?.slug !== 'partner'">
        <!-- Recent Transactions -->
        <div class="table-card">
            <div class="card-header">
                <h4 class="card-title">🧾 10 Transaksi Kasir Terbaru</h4>
                <a href="/pos/kasir" class="btn btn-secondary btn-sm">Buka POS →</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>No. Order</th><th>Waktu</th><th>Kasir</th><th>Metode</th><th>Total</th><th style="text-align:right">Status</th></tr></thead>
                    <tbody>
                        <template x-if="data?.recent_transactions?.length">
                            <template x-for="ord in data.recent_transactions" :key="ord.id">
                                <tr class="tx-row">
                                    <td><span class="order-badge" x-text="'#'+ord.order_number"></span></td>
                                    <td><span style="font-weight:600;font-size:12.5px" x-text="formatDate(ord.created_at,'time')"></span></td>
                                    <td>
                                        <div class="cashier-info">
                                            <span class="cashier-avatar" x-text="getInitials(ord.cashier?.name||'K')"></span>
                                            <span class="cashier-name" x-text="ord.cashier?.name||'Kasir'"></span>
                                        </div>
                                    </td>
                                    <td><span class="pay-badge" x-text="getPaymentIcon(ord.payment_method)+' '+(ord.payment_method||'CASH').toUpperCase()"></span></td>
                                    <td><strong class="total-amount" x-text="'Rp '+formatNumber(ord.total_amount)"></strong></td>
                                    <td style="text-align:right">
                                        <span :class="['status-badge', ord.status==='completed'?'completed':'pending']">
                                            <span class="dot"></span>
                                            <span x-text="ord.status==='completed'?'Selesai':ord.status"></span>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <tr x-show="!data?.recent_transactions?.length">
                            <td colspan="6" class="empty-tx-cell">🧾 Belum ada transaksi kasir hari ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Alerts -->
        <div class="table-card">
            <div class="card-header">
                <h4 class="card-title">⚠️ Peringatan Stok Minimum
                    <span x-show="data?.stock_alerts?.length" class="badge badge-danger ms-2" style="font-size:10px" x-text="data.stock_alerts.length+' Item'"></span>
                </h4>
                <a href="/inventory" class="btn btn-secondary btn-sm">Ke Inventory →</a>
            </div>
            <div class="stock-alerts-list">
                <template x-if="data?.stock_alerts?.length">
                    <template x-for="a in data.stock_alerts" :key="a.id">
                        <div class="alert-item-futuristic" :style="{borderLeft: a.status==='out_of_stock'?'3px solid #ef4444':'3px solid #f59e0b'}">
                            <div style="flex:1">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-weight:700;font-size:13.5px" x-text="a.name"></span>
                                    <span :class="['badge',a.status==='out_of_stock'?'badge-danger':'badge-warning']" style="font-size:9.5px"
                                          x-text="a.status==='out_of_stock'?'🚨 STOK HABIS':'⚠️ STOK RENDAH'"></span>
                                </div>
                                <div style="font-size:11.5px;color:var(--txt-muted);margin-top:3px" x-text="'Kode: '+a.code+' • Min: '+formatNumber(a.min_stock)"></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:800;font-size:14px" :style="{color:a.status==='out_of_stock'?'var(--clr-danger)':'var(--clr-warning)'}" x-text="formatNumber(a.stock)"></div>
                                <a href="/inventory" style="font-size:11px;font-weight:700;color:var(--clr-primary);text-decoration:none">🛒 Restock →</a>
                            </div>
                        </div>
                    </template>
                </template>
                <div x-show="!data?.stock_alerts?.length" style="padding:20px;text-align:center;color:var(--txt-muted);font-size:12.5px">
                    ✅ Seluruh stok bahan baku dalam kondisi aman &amp; mencukupi.
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Store charts outside Alpine Proxy to prevent Chart.js instances from disappearing / breaking
window._dashCharts = window._dashCharts || {};

function dashboardCeo() {
    return {
        data: null,
        user: JSON.parse(localStorage.getItem('erp_user') || '{}'),
        brand: { brand_name: '', name: '', operating_hours_open: '', operating_hours_close: '' },
        todayDate: new Date().toLocaleDateString('id-ID', {weekday:'long',day:'numeric',month:'long',year:'numeric'}),
        selectedRange: '7_days',
        soldRange: 'custom',
        soldStartDate: new Date().toISOString().split('T')[0],
        soldEndDate: new Date().toISOString().split('T')[0],
        pmRange: 'custom',
        pmStartDate: new Date().toISOString().split('T')[0],
        pmEndDate: new Date().toISOString().split('T')[0],
        soldProducts: [],
        pmList: [],
        pmTotal: 0,
        rangeOptions: [
            {value:'1_day',label:'1 Hari'},{value:'7_days',label:'7 Hari'},
            {value:'1_month',label:'1 Bulan'},{value:'1_year',label:'1 Tahun'},{value:'all',label:'Semua'}
        ],
        pmRangeOpts: [{value:'1_day',label:'1 hr'},{value:'7_days',label:'7 hr'},{value:'1_month',label:'1 bln'},{value:'all',label:'Semua'}],
        
        avgFilter: 'week',
        avgStats: null,

        async init() {
            if (this.$parent && this.$parent.brand) {
                this.brand = { ...this.$parent.brand, ...this.brand };
            }
            window.addEventListener('brand-updated', (e) => {
                if (e.detail) {
                    const b = e.detail;
                    this.brand = {
                        ...this.brand,
                        ...b,
                        brand_name: b.brand_name || b.name || this.brand.brand_name || 'RATIO COFFEE',
                        name: b.brand_name || b.name || this.brand.name || 'RATIO COFFEE',
                        operating_hours_open: b.operating_hours_open || this.brand.operating_hours_open || '10:00',
                        operating_hours_close: b.operating_hours_close || this.brand.operating_hours_close || '22:00',
                    };
                }
            });
            await this.fetchDashboard();
            await this.fetchSoldProducts();
            await this.fetchPaymentMethods();
            await this.fetchAverageStats();
        },

        async fetchDashboard() {
            try {
                const r = await axios.get('/api/v1/dashboard/ceo?trend_range=' + this.selectedRange);
                this.data = r.data;
                const b = r.data?.brand || {};
                this.brand = {
                    ...this.brand,
                    ...b,
                    brand_name: b.brand_name || b.name || this.brand.brand_name || 'RATIO COFFEE',
                    name: b.brand_name || b.name || this.brand.name || 'RATIO COFFEE',
                    operating_hours_open: b.operating_hours_open || b.operatingHoursOpen || this.brand.operating_hours_open || '10:00',
                    operating_hours_close: b.operating_hours_close || b.operatingHoursClose || this.brand.operating_hours_close || '22:00',
                };
                setTimeout(() => this.buildCharts(), 100);
            } catch(e) { console.error(e); }
        },

        async fetchSoldProducts() {
            try {
                let url = '/api/v1/dashboard/sold-products?range=' + this.soldRange;
                if (this.soldStartDate && this.soldEndDate) {
                    url += `&start_date=${this.soldStartDate}&end_date=${this.soldEndDate}`;
                }
                const r = await axios.get(url);
                this.soldProducts = r.data.all_products_sold || [];
            } catch(e) {
                this.soldProducts = [];
            }
        },

        async fetchPaymentMethods() {
            try {
                let url = '/api/v1/dashboard/payment-methods?range=' + this.pmRange;
                if (this.pmStartDate && this.pmEndDate) {
                    url += `&start_date=${this.pmStartDate}&end_date=${this.pmEndDate}`;
                }
                const r = await axios.get(url);
                this.pmList = r.data.payment_methods || [];
                this.pmTotal = this.pmList.reduce((s,p) => s + (p.count||0), 0);
            } catch(e) {
                this.pmList = [];
                this.pmTotal = 0;
            }
        },

        async fetchAverageStats() {
            try {
                const r = await axios.get('/api/v1/dashboard/average-stats?filter=' + this.avgFilter);
                this.avgStats = r.data;
            } catch(e) {
                console.error('Failed to fetch average stats', e);
            }
        },

        async changeRange(v) { this.selectedRange = v; await this.fetchDashboard(); },
        async changePmRange(v) { this.pmRange = v; await this.fetchPaymentMethods(); },
        async changeAvgFilter(v) { this.avgFilter = v; await this.fetchAverageStats(); },

        buildCharts() {
            if (typeof Chart === 'undefined') {
                setTimeout(() => this.buildCharts(), 200);
                return;
            }
            const charts = this.data?.charts || {};
            const topProds = this.data?.top_products || [];

            // 1. Revenue & Profit Line Chart
            try {
                const ctx1 = document.getElementById('revenueChart');
                if (ctx1) {
                    if (window._dashCharts.revenue) {
                        window._dashCharts.revenue.destroy();
                        window._dashCharts.revenue = null;
                    }

                    const days = (charts.days && charts.days.length) ? charts.days : ['01 M', '02 M', '03 M'];
                    let revData = charts.revenue_trend || [];
                    let profData = charts.profit_trend || [];

                    window._dashCharts.revenue = new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: days,
                            datasets: [
                                { 
                                    label: 'Revenue (Rp)', 
                                    borderColor: '#ea580c', 
                                    backgroundColor: 'rgba(234, 88, 12, 0.12)', 
                                    borderWidth: 3, 
                                    fill: true, 
                                    tension: 0.35, 
                                    spanGaps: true,
                                    pointBackgroundColor: '#ea580c',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 2,
                                    pointHoverRadius: 7,
                                    pointRadius: 4,
                                    data: revData 
                                },
                                { 
                                    label: 'Profit (Rp)', 
                                    borderColor: '#10b981', 
                                    backgroundColor: 'rgba(16, 185, 129, 0.12)', 
                                    borderWidth: 3, 
                                    fill: true, 
                                    tension: 0.35, 
                                    spanGaps: true,
                                    pointBackgroundColor: '#10b981',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 2,
                                    pointHoverRadius: 7,
                                    pointRadius: 4,
                                    data: profData 
                                }
                            ]
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            animation: { duration: 400 },
                            plugins: { 
                                legend: { display: true, position: 'top', labels: { usePointStyle: true, font: { weight: '600', family: "'Plus Jakarta Sans', sans-serif" } } },
                                tooltip: { mode: 'index', intersect: false }
                            },
                            scales: {
                                y: { beginAtZero: true, grid: { color: 'rgba(226, 232, 240, 0.6)' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch(e) { console.error('Revenue chart error:', e); }

            // 2. Top Products Bar Chart
            try {
                const ctx2 = document.getElementById('productsChart');
                if (ctx2) {
                    if (window._dashCharts.products) {
                        window._dashCharts.products.destroy();
                        window._dashCharts.products = null;
                    }
                    
                    let labels = topProds.map(p => p.product_name);
                    let values = topProds.map(p => p.total_qty);

                    window._dashCharts.products = new Chart(ctx2, {
                        type: 'bar',
                        data: { 
                            labels: labels, 
                            datasets: [{ 
                                label: 'Terjual (Unit)', 
                                backgroundColor: 'rgba(234, 88, 12, 0.85)', 
                                hoverBackgroundColor: '#ea580c',
                                borderRadius: 8, 
                                data: values 
                            }] 
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            plugins: { legend: { display: false } }, 
                            scales: { y: { beginAtZero: true, grid: { color: 'rgba(226, 232, 240, 0.6)' } }, x: { grid: { display: false } } } 
                        }
                    });
                }
            } catch(e) { console.error('Top products chart error:', e); }

            // 3. Category Donut Chart
            try {
                const cats = charts.sales_by_category || [];
                const ctx3 = document.getElementById('categoryChart');
                if (ctx3) {
                    if (window._dashCharts.category) {
                        window._dashCharts.category.destroy();
                        window._dashCharts.category = null;
                    }
                    
                    let catLabels = cats.map(c => c.category);
                    let catValues = cats.map(c => c.total);

                    window._dashCharts.category = new Chart(ctx3, {
                        type: 'doughnut',
                        data: { 
                            labels: catLabels, 
                            datasets: [{ 
                                backgroundColor: ['#ea580c', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#f59e0b'], 
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                data: catValues 
                            }] 
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            plugins: { 
                                legend: { position: 'right', labels: { font: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' } } } 
                            } 
                        }
                    });
                }
            } catch(e) { console.error('Category chart error:', e); }
        },

        formatNumber, formatDate, getInitials, getPaymentIcon,

        roleDashTitle() {
            const r = this.user?.role?.slug || 'ceo';
            const name = this.user?.name || '';
            const greet = name ? `Selamat datang, ${name}! 👋` : 'Selamat datang! 👋';
            const titles = {
                ceo:        'Dashboard CEO & Executive Control Center',
                employee:   'Dashboard CEO & Executive Control Center',
                partner:    'Dashboard Partner & Investor',
                consultant: 'Dashboard Konsultan',
                operator:   'Dashboard Operator HQ',
            };
            const label = titles[r] || 'Dashboard ERP';
            document.title = label + ' – Ratio Coffee ERP';
            return greet;
        },
    }
}
</script>
@endpush
