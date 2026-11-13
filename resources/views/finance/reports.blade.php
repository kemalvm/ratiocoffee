@extends('layouts.erp')
@section('title', 'Laporan Bisnis & Analisis Performa – Ratio Coffee ERP')

@section('page-content')
<div x-data="reportsApp()" x-init="init()" x-cloak class="container-fluid p-0">
    
    <!-- Top Header & Global Filter -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-bold m-0 text-primary d-flex align-items-center gap-2" style="font-size: 1.4rem;">
                <span>📈</span> Laporan Bisnis & Analisis Performa
            </h3>
            <p class="text-muted small m-0 mt-1">Analisis menyeluruh tentang penjualan, laba rugi, valuasi stok, dan performa menu Ratio Coffee</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="trend-filter-pills me-2">
                <button class="trend-pill-btn" :class="{active: range==='1_day'}" @click="setRange('1_day')">1 Hari</button>
                <button class="trend-pill-btn" :class="{active: range==='7_days'}" @click="setRange('7_days')">7 Hari</button>
                <button class="trend-pill-btn" :class="{active: range==='1_month'}" @click="setRange('1_month')">Bulan Ini</button>
                <button class="trend-pill-btn" :class="{active: range==='1_year'}" @click="setRange('1_year')">1 Tahun</button>
            </div>
            <button class="btn btn-primary btn-sm px-3" @click="exportReport()">
                <span>📊</span> Export Report
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs border-bottom mb-4 gap-2">
        <li class="nav-item">
            <button class="nav-link py-2 px-3 fw-semibold" :class="{active: activeTab==='sales'}" @click="activeTab='sales'">
                <span>📊</span> Penjualan & Transaksi
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 px-3 fw-semibold" :class="{active: activeTab==='pl'}" @click="activeTab='pl'">
                <span>💰</span> Laba Rugi (P&L)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 px-3 fw-semibold" :class="{active: activeTab==='inventory'}" @click="activeTab='inventory'">
                <span>📦</span> Valuasi Stok
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-2 px-3 fw-semibold" :class="{active: activeTab==='top-menu'}" @click="activeTab='top-menu'">
                <span>⭐</span> Menu Terlaris
            </button>
        </li>
    </ul>

    <!-- Loading Indicator -->
    <div x-show="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="text-muted small mt-2">Memuat data laporan bisnis...</div>
    </div>

    <!-- TAB 1: PENJUALAN & TRANSAKSI -->
    <div x-show="!loading && activeTab==='sales'" x-transition>
        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Total Omzet Penjualan</span>
                    <div class="fs-3 fw-bold text-primary mt-1" x-text="'Rp ' + formatNumber(salesData.summary?.total_sales || 0)"></div>
                    <span class="text-muted small mt-1">Bruto Kasir POS</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Total Transaksi Selesai</span>
                    <div class="fs-3 fw-bold text-success mt-1" x-text="(salesData.summary?.total_orders || 0) + ' Struk'"></div>
                    <span class="text-muted small mt-1">Order Berhasil</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Laba Kotor Sales</span>
                    <div class="fs-3 fw-bold text-warning mt-1" x-text="'Rp ' + formatNumber(salesData.summary?.gross_profit || 0)"></div>
                    <span class="text-muted small mt-1">Omzet dikurangi HPP Menu</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Rata-Rata Nilai Struk (AOV)</span>
                    <div class="fs-3 fw-bold text-info mt-1" x-text="'Rp ' + formatNumber(salesData.summary?.aov || 0)"></div>
                    <span class="text-muted small mt-1">Average Basket Size</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sales Items Table -->
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0">🛍️ Rincian Penjualan Per Produk</h6>
                        <span class="badge badge-muted" x-text="(salesData.items?.length || 0) + ' Produk Terjual'"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Qty Terjual</th>
                                    <th>Total Omzet</th>
                                    <th>Estimasi Profit</th>
                                    <th class="text-end pe-4">Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="!salesData.items || salesData.items.length === 0">
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data penjualan pada periode ini.</td></tr>
                                </template>
                                <template x-for="item in salesData.items" :key="item.name">
                                    <tr>
                                        <td class="ps-4 fw-semibold" x-text="item.name"></td>
                                        <td><span class="badge badge-info" x-text="item.category"></span></td>
                                        <td x-text="item.qty + ' pcs'"></td>
                                        <td class="fw-bold text-primary" x-text="'Rp ' + formatNumber(item.revenue)"></td>
                                        <td class="text-success" x-text="'Rp ' + formatNumber(item.profit)"></td>
                                        <td class="text-end pe-4">
                                            <span class="badge badge-success" x-text="item.margin + '%'"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment Method Breakdown -->
            <div class="col-12 col-lg-4">
                <div class="card p-4 border-0 shadow-sm h-100">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">💳 Breakdown Metode Pembayaran</h6>
                    <template x-if="!salesData.payment_breakdown || salesData.payment_breakdown.length === 0">
                        <div class="text-muted small py-4 text-center">Belum ada data metode pembayaran.</div>
                    </template>
                    <div class="d-flex flex-column gap-3">
                        <template x-for="p in salesData.payment_breakdown" :key="p.method">
                            <div class="p-3 rounded border" style="background: var(--bg-muted, #f8fafc);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-primary" x-text="p.method"></span>
                                    <span class="badge badge-muted" x-text="p.count + ' Tx'"></span>
                                </div>
                                <div class="fs-5 fw-bold" x-text="'Rp ' + formatNumber(p.total)"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: LABA RUGI (P&L) -->
    <div x-show="!loading && activeTab==='pl'" x-transition>
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Total Pendapatan (Revenue)</span>
                    <div class="fs-3 fw-bold text-primary mt-1" x-text="'Rp ' + formatNumber(plData.summary?.revenue || 0)"></div>
                    <span class="text-muted small mt-1">Total Kasir POS</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Total HPP (COGS)</span>
                    <div class="fs-3 fw-bold text-danger mt-1" x-text="'Rp ' + formatNumber(plData.summary?.cogs || 0)"></div>
                    <span class="text-muted small mt-1">Biaya Bahan Baku Terpakai</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Beban Operasional</span>
                    <div class="fs-3 fw-bold text-warning mt-1" x-text="'Rp ' + formatNumber(plData.summary?.operating_expense || 0)"></div>
                    <span class="text-muted small mt-1">Biaya Listrik, Sewa, Gaji, dll</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
                    <span class="text-muted small fw-semibold text-uppercase">Net Profit (Laba Bersih)</span>
                    <div class="fs-3 fw-bold text-success mt-1" x-text="'Rp ' + formatNumber(plData.summary?.net_profit || 0)"></div>
                    <span class="badge badge-success mt-1 w-auto" x-text="'Net Margin: ' + (plData.summary?.net_margin_pct || 0) + '%'"></span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Monthly Trend -->
            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="p-3 border-bottom">
                        <h6 class="fw-bold m-0">📅 Tren Laba Rugi Per Bulan</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Bulan</th>
                                    <th>Revenue</th>
                                    <th>HPP (COGS)</th>
                                    <th>Beban</th>
                                    <th class="text-end pe-4">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="!plData.monthly_trend || plData.monthly_trend.length === 0">
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada tren data.</td></tr>
                                </template>
                                <template x-for="m in plData.monthly_trend" :key="m.label">
                                    <tr>
                                        <td class="ps-4 fw-bold" x-text="m.label"></td>
                                        <td class="text-primary" x-text="'Rp ' + formatNumber(m.revenue)"></td>
                                        <td class="text-danger" x-text="'Rp ' + formatNumber(m.cogs)"></td>
                                        <td class="text-warning" x-text="'Rp ' + formatNumber(m.expenses)"></td>
                                        <td class="text-end pe-4 fw-bold" :class="m.net_profit >= 0 ? 'text-success' : 'text-danger'" x-text="'Rp ' + formatNumber(m.net_profit)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Expense Breakdown -->
            <div class="col-12 col-lg-5">
                <div class="card p-4 border-0 shadow-sm h-100">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">💸 Rincian Beban Operasional</h6>
                    <template x-if="!plData.expense_breakdown || plData.expense_breakdown.length === 0">
                        <div class="text-muted small py-4 text-center">Belum ada data beban pengeluaran operasional.</div>
                    </template>
                    <div class="d-flex flex-column gap-3">
                        <template x-for="exp in plData.expense_breakdown" :key="exp.category">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded border" style="background: var(--bg-muted, #f8fafc);">
                                <span class="fw-bold" x-text="exp.category"></span>
                                <span class="fw-bold text-danger fs-6" x-text="'Rp ' + formatNumber(exp.amount)"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: VALUASI STOK INVENTORY -->
    <div x-show="!loading && activeTab==='inventory'" x-transition>
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Total Item Bahan</span>
                    <div class="fs-3 fw-bold text-primary mt-1" x-text="(invData.summary?.total_items || 0) + ' SKU'"></div>
                    <span class="text-muted small mt-1">Bahan Baku & Kemasan</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Total Valuasi Stok (Rp)</span>
                    <div class="fs-3 fw-bold text-success mt-1" x-text="'Rp ' + formatNumber(invData.summary?.total_valuation || 0)"></div>
                    <span class="text-muted small mt-1">Nilai Aset Inventaris</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Stok Menipis (Warning)</span>
                    <div class="fs-3 fw-bold text-warning mt-1" x-text="(invData.summary?.low_stock_count || 0) + ' Item'"></div>
                    <span class="text-muted small mt-1">Perlu Restock</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card p-3 border-0 shadow-sm">
                    <span class="text-muted small fw-semibold text-uppercase">Stok Habis (Out of Stock)</span>
                    <div class="fs-3 fw-bold text-danger mt-1" x-text="(invData.summary?.out_of_stock_count || 0) + ' Item'"></div>
                    <span class="text-muted small mt-1">Stok 0</span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="data-table align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Kode / Nama Bahan</th>
                            <th>Kategori</th>
                            <th>Stok Saat Ini</th>
                            <th>Harga Beli / Unit</th>
                            <th>Total Valuasi (Rp)</th>
                            <th class="text-end pe-4">Status Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="!invData.items || invData.items.length === 0">
                            <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data inventaris.</td></tr>
                        </template>
                        <template x-for="item in invData.items" :key="item.id">
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold" x-text="item.name"></div>
                                    <div class="text-muted small" x-text="item.code"></div>
                                </td>
                                <td><span class="badge badge-info" x-text="item.category"></span></td>
                                <td class="fw-semibold" x-text="item.current_stock + ' ' + item.unit"></td>
                                <td class="text-muted" x-text="'Rp ' + formatNumber(item.unit_cost)"></td>
                                <td class="fw-bold text-primary" x-text="'Rp ' + formatNumber(item.valuation)"></td>
                                <td class="text-end pe-4">
                                    <span x-show="item.status==='normal'" class="badge badge-success">Normal</span>
                                    <span x-show="item.status==='low_stock'" class="badge badge-warning">Stok Menipis</span>
                                    <span x-show="item.status==='out_of_stock'" class="badge badge-danger">Stok Habis</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: MENU TERLARIS -->
    <div x-show="!loading && activeTab==='top-menu'" x-transition>
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0">⭐ Ranking Performa Produk & Menu Terlaris</h6>
                <span class="badge badge-primary" x-text="(topMenuData.summary?.total_menu_sold || 0) + ' Menu Terjual'"></span>
            </div>
            <div class="table-responsive">
                <table class="data-table align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Rank</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th>Qty Terjual</th>
                            <th>Total Omzet</th>
                            <th>Profit Kotor</th>
                            <th class="text-end pe-4">Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="!topMenuData.items || topMenuData.items.length === 0">
                            <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data menu terlaris.</td></tr>
                        </template>
                        <template x-for="item in topMenuData.items" :key="item.name">
                            <tr>
                                <td class="ps-4">
                                    <span :class="{
                                        'badge bg-warning text-dark fs-6 px-3': item.rank === 1,
                                        'badge bg-secondary text-white fs-6 px-3': item.rank === 2,
                                        'badge bg-danger text-white fs-6 px-3': item.rank === 3,
                                        'fw-bold text-muted ps-2': item.rank > 3
                                    }" x-text="'#' + item.rank"></span>
                                </td>
                                <td class="fw-bold" x-text="item.name"></td>
                                <td><span class="badge badge-info" x-text="item.category"></span></td>
                                <td class="fw-bold text-success" x-text="item.total_qty + ' porsi'"></td>
                                <td class="fw-bold text-primary" x-text="'Rp ' + formatNumber(item.total_revenue)"></td>
                                <td class="text-muted" x-text="'Rp ' + formatNumber(item.profit)"></td>
                                <td class="text-end pe-4">
                                    <span class="badge badge-success" x-text="item.margin + '%'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function reportsApp() {
    return {
        activeTab: 'sales',
        loading: false,
        range: '1_month',
        startDate: '',
        endDate: '',
        
        salesData: { summary: {}, items: [], payment_breakdown: [] },
        plData: { summary: {}, expense_breakdown: [], monthly_trend: [] },
        invData: { summary: {}, items: [] },
        topMenuData: { summary: {}, items: [] },

        async init() {
            this.setRange('1_month');
        },

        setRange(r) {
            this.range = r;
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            
            this.endDate = `${year}-${month}-${day}`;
            
            let start = new Date();
            if (r === '1_day') {
                start = now;
            } else if (r === '7_days') {
                start.setDate(now.getDate() - 6);
            } else if (r === '1_year') {
                start.setFullYear(now.getFullYear() - 1);
            } else { // 1_month
                start.setDate(now.getDate() - 29);
            }
            
            const sYear = start.getFullYear();
            const sMonth = String(start.getMonth() + 1).padStart(2, '0');
            const sDay = String(start.getDate()).padStart(2, '0');
            this.startDate = `${sYear}-${sMonth}-${sDay}`;
            
            this.loadAll();
        },

        async loadAll() {
            this.loading = true;
            try {
                const params = { start_date: this.startDate, end_date: this.endDate, range: this.range };
                const [rSales, rPl, rInv, rTop] = await Promise.all([
                    axios.get('/api/v1/reports/sales', { params }),
                    axios.get('/api/v1/reports/profit-loss', { params }),
                    axios.get('/api/v1/reports/inventory', { params }),
                    axios.get('/api/v1/reports/top-menu', { params })
                ]);
                
                this.salesData = rSales.data;
                this.plData = rPl.data;
                this.invData = rInv.data;
                this.topMenuData = rTop.data;
            } catch(e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        exportReport() {
            if (typeof toast === 'function') {
                toast('Mengunduh laporan Excel...');
            }
            window.location.href = `/api/v1/reports/export?start_date=${this.startDate}&end_date=${this.endDate}&range=${this.range}`;
        },

        formatNumber,
        formatDate
    }
}
</script>
@endpush
