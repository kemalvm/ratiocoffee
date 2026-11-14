@extends('layouts.erp')
@section('title', 'Keuangan & Cash Flow – Ratio Coffee ERP')

@section('page-content')
<div x-data="financeApp()" x-init="init()" x-cloak>
    <!-- Top Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                💵 Keuangan &amp; Cash Flow
            </h4>
            <p class="text-muted small m-0 mt-1">Pantau perputaran arus kas, pemasukan, pengeluaran, dan net profit bisnis secara real-time.</p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <!-- Global Date Filter -->
            <div class="d-flex align-items-center gap-2 bg-white p-1 rounded-pill shadow-sm border">
                <span class="px-2 small fw-bold text-muted">📅 Periode:</span>
                <input type="date" x-model="startDate" @change="load()" class="form-control border-0 rounded-pill text-dark fw-semibold" style="font-size:12px; width:130px; padding:4px 10px; background:var(--bg-surface)">
                <span class="text-muted small fw-bold">s/d</span>
                <input type="date" x-model="endDate" @change="load()" class="form-control border-0 rounded-pill text-dark fw-semibold" style="font-size:12px; width:130px; padding:4px 10px; background:var(--bg-surface)">
            </div>
            
            <!-- Actions -->
            <div class="d-flex gap-2">
                <button class="btn btn-danger btn-sm text-white fw-bold shadow-sm d-inline-flex align-items-center gap-1.5 px-3 py-2" @click="openExpenseModal('expense')" style="border-radius:10px; background:#ef4444; border:none">
                    💸 + Catat Pengeluaran
                </button>
                <button class="btn btn-success btn-sm text-white fw-bold shadow-sm d-inline-flex align-items-center gap-1.5 px-3 py-2" @click="openExpenseModal('income')" style="border-radius:10px; background:#10b981; border:none">
                    💵 + Catat Pemasukan
                </button>
            </div>
        </div>
    </div>

    <!-- Finance KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-card-profit">
                <div class="kpi-card-glow"></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size:10.5px; letter-spacing:0.5px">TOTAL PEMASUKAN</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1" style="font-size:10.5px" x-text="dateBadgeLabel"></span>
                </div>
                <div class="fs-4 fw-bold text-success mb-1" x-text="'Rp ' + formatNumber(summary.total_revenue || 0)"></div>
                <div class="text-muted small" style="font-size:11.5px">Pemasukan penjualan &amp; kas masuk</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-expense">
                <div class="kpi-card-glow"></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size:10.5px; letter-spacing:0.5px">TOTAL PENGELUARAN</span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1" style="font-size:10.5px" x-text="dateBadgeLabel"></span>
                </div>
                <div class="fs-4 fw-bold text-danger mb-1" x-text="'Rp ' + formatNumber(summary.total_expenses || 0)"></div>
                <div class="text-muted small" style="font-size:11.5px">Pengeluaran belanja &amp; operasional</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-revenue">
                <div class="kpi-card-glow"></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size:10.5px; letter-spacing:0.5px">NET CASH FLOW</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size:10.5px" x-text="dateBadgeLabel"></span>
                </div>
                <div class="fs-4 fw-bold mb-1" :class="((summary.total_revenue || 0) - (summary.total_expenses || 0)) >= 0 ? 'text-success' : 'text-danger'" x-text="'Rp ' + formatNumber((summary.total_revenue || 0) - (summary.total_expenses || 0))"></div>
                <div class="text-muted small" style="font-size:11.5px">Profit / Loss bersih periode ini</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-asset">
                <div class="kpi-card-glow"></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size:10.5px; letter-spacing:0.5px">TOTAL TRANSAKSI</span>
                    <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1" style="font-size:10.5px">Catatan</span>
                </div>
                <div class="fs-4 fw-bold text-dark mb-1" x-text="summary.transaction_count || 0"></div>
                <div class="text-muted small" style="font-size:11.5px">Jumlah catatan transaksi tercatat</div>
            </div>
        </div>
    </div>

    <!-- Filters & Action Toolbar -->
    <div class="card shadow-sm border mb-4" style="padding: 20px; border-radius:16px; background:var(--bg-surface); border-color: var(--border-clr, #e2e8f0) !important">
        <div class="row g-3 align-items-center">
            <!-- Search Input -->
            <div class="col-md-5">
                <label class="form-label text-muted small fw-semibold mb-1.5">🔍 Cari Transaksi</label>
                <input x-model="search" @input.debounce.300ms="load()" class="form-input" placeholder="Cari deskripsi, nominal, atau no ref..." />
            </div>

            <!-- Tipe Transaksi -->
            <div class="col-md-7 d-flex justify-content-end align-items-center gap-2">
                <span class="text-muted small fw-semibold me-1">Tipe Transaksi:</span>
                <button :class="['btn btn-sm px-3 py-1.5', filterType === '' ? 'btn-primary text-white fw-bold shadow-sm' : 'btn-light text-dark border']" @click="filterType = ''; load()" style="border-radius:20px; font-size:12px">
                    Semua
                </button>
                <button :class="['btn btn-sm px-3 py-1.5', filterType === 'income' ? 'btn-success text-white fw-bold shadow-sm' : 'btn-light text-dark border']" @click="filterType = 'income'; load()" style="border-radius:20px; font-size:12px">
                    💵 Pemasukan
                </button>
                <button :class="['btn btn-sm px-3 py-1.5', filterType === 'expense' ? 'btn-danger text-white fw-bold shadow-sm' : 'btn-light text-dark border']" @click="filterType = 'expense'; load()" style="border-radius:20px; font-size:12px">
                    💸 Pengeluaran
                </button>
            </div>
        </div>



    <!-- Cashflow Transactions Table -->
    <div class="card p-0 shadow-sm border" style="border-radius:16px; overflow:hidden">
        <div class="table-responsive">
            <table class="data-table mb-0">
                <thead style="background:var(--bg-card, #f8fafc)">
                    <tr>
                        <th style="padding-left:16px">Waktu &amp; Tanggal</th>
                        <th>Kategori</th>
                        <th>Keterangan / Ref</th>
                        <th>Tipe</th>
                        <th>Jumlah (Rp)</th>
                        <th>Metode Bayar</th>
                        <th>Pencatat</th>
                        <th class="text-end" style="padding-right:16px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="8" class="text-center py-4 text-muted">⏳ Memuat data keuangan...</td></tr>
                    </template>
                    <template x-if="!loading && transactions.length === 0">
                        <tr><td colspan="8" class="text-center py-5 text-muted">Belum ada catatan transaksi keuangan pada periode ini.</td></tr>
                    </template>
                    <template x-for="tx in transactions" :key="tx.id">
                        <tr>
                            <td style="padding-left:16px">
                                <span class="fw-semibold text-dark" x-text="formatDate(tx.created_at || tx.date, 'full')"></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1" style="border-radius:12px; font-weight:600; font-size:11px" x-text="tx.category?.name || tx.category || 'Umum'"></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark" x-text="tx.description || tx.title || 'Catatan Keuangan'"></div>
                                <small class="text-muted" x-text="tx.reference_number ? 'Ref: #' + tx.reference_number : ''"></small>
                            </td>
                            <td>
                                <span :class="['badge px-2.5 py-1', tx.type === 'income' ? 'badge-success' : 'badge-danger']"
                                      style="border-radius:12px; font-size:11px"
                                      x-text="tx.type === 'income' ? '💵 Pemasukan' : '💸 Pengeluaran'"></span>
                            </td>
                            <td class="fw-bold fs-6" :class="tx.type === 'income' ? 'text-success' : 'text-danger'"
                                x-text="(tx.type === 'income' ? '+ ' : '- ') + 'Rp ' + formatNumber(tx.amount || 0)"></td>
                            <td>
                                <span class="badge bg-light text-secondary border px-2 py-1" style="font-size:11px; border-radius:6px" x-text="getPaymentIcon(tx.payment_method) + ' ' + (tx.payment_method || 'CASH').toUpperCase()"></span>
                            </td>
                            <td>
                                <span class="text-muted small fw-semibold" x-text="tx.user?.name || tx.creator?.name || 'Sistem'"></span>
                            </td>
                            <td class="text-end" style="padding-right:16px">
                                <button x-show="tx.can_delete" class="btn btn-danger btn-sm p-1 px-2" style="border-radius:6px" @click="delTx(tx)">🗑</button>
                                <span x-show="!tx.can_delete" class="text-muted small">🔒 Locked</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Catat Pengeluaran / Pemasukan -->
    <div x-show="showModal" class="modal-overlay" @click.self="showModal=false" style="display:flex">
        <div class="modal-box" style="max-width:500px; border-radius:14px">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold" x-text="form.type === 'income' ? '💵 Catat Pemasukan Baru' : '💸 Catat Pengeluaran Baru'"></h5>
                <button class="btn btn-secondary btn-sm" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label">Tipe Transaksi *</label>
                    <select x-model="form.type" class="form-input">
                        <option value="expense">Pengeluaran (Expense)</option>
                        <option value="income">Pemasukan (Income / Injection)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori *</label>
                    <select x-model="form.category_id" class="form-input">
                        <option value="">Pilih Kategori</option>
                        <template x-for="c in categories" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal (Rp) *</label>
                    <input x-model.number="form.amount" type="number" class="form-input" placeholder="mis. 150000" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Keterangan / Detail *</label>
                    <input x-model="form.description" class="form-input" placeholder="mis. Pembelian Listrik / Operasional Barista" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Metode Pembayaran</label>
                    <select x-model="form.payment_method" class="form-input">
                        <option value="cash">💵 Cash / Kas Toko</option>
                        <option value="transfer">🏦 Bank Transfer</option>
                        <option value="qris">📱 QRIS / E-Wallet</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" @click="showModal=false">Batal</button>
                <button class="btn btn-primary fw-bold" :disabled="saving" @click="saveExpense()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Transaksi</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function financeApp() {
    return {
        loading: false, saving: false,
        summary: {}, transactions: [], categories: [],
        search: '', filterType: '', filterCategory: '',
        startDate: new Date().toISOString().split('T')[0], 
        endDate: new Date().toISOString().split('T')[0], 
        isRangeMode: true,
        selectedMonth: '', selectedYear: new Date().getFullYear(),
        showModal: false,
        form: { type: 'expense', category_id: '', amount: 0, description: '', payment_method: 'cash' },

        get dateBadgeLabel() {
            if (this.selectedMonth) {
                const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                return months[parseInt(this.selectedMonth) - 1] || 'Bulan Ini';
            }
            if (this.startDate && this.endDate && this.startDate !== this.endDate) {
                return 'Rentang Waktu';
            }
            if (this.startDate) {
                return this.startDate;
            }
            return 'Semua Periode';
        },

        async init() {
            await Promise.all([this.load(), this.loadMeta()]);
        },

        async load() {
            this.loading = true;
            try {
                const params = {
                    search: this.search,
                    type: this.filterType,
                    category_id: this.filterCategory,
                    start_date: this.startDate,
                    end_date: this.isRangeMode ? this.endDate : this.startDate,
                };
                const r = await axios.get('/api/v1/finance/transactions', { params });
                this.transactions = r.data.data ?? r.data ?? [];
                
                // Use totals from transactions API directly to guarantee consistency with the table
                this.summary = {
                    total_revenue: r.data.total_inflow || 0,
                    total_expenses: r.data.total_outflow || 0,
                    transaction_count: r.data.total || 0
                };
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        async loadMeta() {
            try {
                const c = await axios.get('/api/v1/finance/expense-categories');
                this.categories = c.data.data ?? c.data.categories ?? c.data ?? [];
            } catch(e) {}
        },

        enableRangeMode() {
            this.isRangeMode = true;
            if (!this.endDate && this.startDate) {
                this.endDate = this.startDate;
            }
        },

        disableRangeMode() {
            this.isRangeMode = false;
            this.endDate = '';
            this.load();
        },

        onMonthChange() {
            if (!this.selectedMonth) {
                this.startDate = '';
                this.endDate = '';
                this.isRangeMode = false;
            } else {
                const m = parseInt(this.selectedMonth);
                const y = this.selectedYear;
                const start = new Date(y, m - 1, 1);
                const end = new Date(y, m, 0);

                // Format YYYY-MM-DD
                const pad = (n) => n.toString().padStart(2, '0');
                this.startDate = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-01`;
                this.endDate = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`;
                this.isRangeMode = true;
            }
            this.load();
        },

        resetAllFilters() {
            this.search = '';
            this.filterType = '';
            this.filterCategory = '';
            this.startDate = '';
            this.endDate = '';
            this.selectedMonth = '';
            this.isRangeMode = false;
            this.load();
        },

        openExpenseModal(type = 'expense') {
            this.form = { type: type, category_id: '', amount: 0, description: '', payment_method: 'cash' };
            this.showModal = true;
        },

        async saveExpense() {
            if (!this.form.amount || this.form.amount <= 0) {
                toast('Nominal harus lebih dari 0', 'error'); return;
            }
            this.saving = true;
            try {
                await axios.post('/api/v1/expenses', this.form);
                toast('Catatan keuangan berhasil disimpan!');
                this.showModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan transaksi', 'error');
            } finally { this.saving = false; }
        },

        delTx(tx) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/expenses/${tx.raw_id || tx.id}`);
                    toast('Transaksi berhasil dihapus!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus transaksi', 'error');
                }
            }, tx.description || 'transaksi ini');
        },

        formatNumber, formatDate, getPaymentIcon
    }
}
</script>
@endpush
