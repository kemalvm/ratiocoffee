@extends('layouts.erp')
@section('title', 'Expense & Pengingat Pembayaran – Ratio Coffee ERP')

@push('styles')
<style>
.expense-page{--expense-red:#ef4444;--expense-green:#10b981;--expense-amber:#f59e0b;--expense-blue:#0ea5e9}
.expense-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
.expense-eyebrow{font-size:11px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:var(--clr-primary);margin-bottom:6px}
.expense-title{font-size:clamp(1.45rem,2vw,2rem);font-weight:800;letter-spacing:-.035em;color:var(--txt-primary);margin:0}
.expense-subtitle{font-size:13px;color:var(--txt-muted);margin:6px 0 0;max-width:660px}
.expense-primary-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:11px;padding:11px 16px;background:linear-gradient(135deg,var(--clr-primary),var(--clr-primary-dark,#c2410c));color:#fff;font-size:12.5px;font-weight:800;box-shadow:0 8px 20px rgba(234,88,12,.2);white-space:nowrap}
.expense-primary-btn:hover{color:#fff;transform:translateY(-1px);box-shadow:0 11px 25px rgba(234,88,12,.28)}
.expense-kpi{position:relative;overflow:hidden;height:100%;min-height:142px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-surface);box-shadow:0 4px 18px rgba(15,23,42,.045)}
.expense-kpi:after{content:"";position:absolute;width:105px;height:105px;border-radius:50%;right:-42px;top:-44px;background:var(--kpi-color);opacity:.08}
.expense-kpi-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.expense-kpi-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:var(--kpi-color);color:#fff;font-size:19px;box-shadow:0 7px 15px color-mix(in srgb,var(--kpi-color) 25%,transparent)}
.expense-kpi-badge{padding:5px 9px;border-radius:999px;background:color-mix(in srgb,var(--kpi-color) 10%,var(--bg-surface));border:1px solid color-mix(in srgb,var(--kpi-color) 25%,transparent);color:var(--kpi-color);font-size:10.5px;font-weight:800}
.expense-kpi-value{font-size:clamp(1.35rem,2vw,1.75rem);font-weight:850;letter-spacing:-.035em;color:var(--txt-primary);line-height:1.1;overflow-wrap:anywhere}
.expense-kpi-label{margin-top:7px;color:var(--txt-muted);font-size:10.5px;font-weight:800;letter-spacing:.045em;text-transform:uppercase}
.expense-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px;margin:22px 0 16px;border:1px solid var(--border);border-radius:16px;background:var(--bg-surface);box-shadow:0 4px 16px rgba(15,23,42,.04)}
.expense-search{position:relative;flex:1;min-width:220px;max-width:430px}.expense-search span{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--txt-muted);pointer-events:none}.expense-search .form-input{padding-left:38px;height:42px;border-radius:11px;background:var(--bg-muted)}
.expense-filters{display:flex;align-items:center;gap:7px;overflow-x:auto;padding-bottom:1px}.expense-filter{border:1px solid var(--border);border-radius:9px;padding:8px 11px;background:var(--bg-muted);color:var(--txt-secondary);font-size:11.5px;font-weight:750;white-space:nowrap}.expense-filter.active{background:var(--clr-primary);border-color:var(--clr-primary);color:#fff;box-shadow:0 5px 13px rgba(234,88,12,.18)}
.expense-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:24px}
.expense-card{position:relative;display:flex;flex-direction:column;min-width:0;min-height:285px;overflow:hidden;border:1px solid var(--border);border-radius:16px;background:var(--bg-surface);box-shadow:0 4px 16px rgba(15,23,42,.045);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}.expense-card:hover{transform:translateY(-3px);border-color:color-mix(in srgb,var(--clr-primary) 35%,var(--border));box-shadow:0 12px 30px rgba(15,23,42,.09)}
.expense-card-accent{height:4px;background:var(--card-accent,var(--clr-primary))}.expense-card-body{display:flex;flex:1;flex-direction:column;padding:17px}.expense-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}.expense-tag{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:999px;background:var(--bg-muted);border:1px solid var(--border);color:var(--txt-secondary);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.025em}.expense-tag-purple{background:rgba(124,58,237,.1);border-color:rgba(124,58,237,.22);color:#7c3aed}
.expense-card-title{font-size:15px;font-weight:800;line-height:1.38;color:var(--txt-primary);margin:0 0 13px;overflow-wrap:anywhere}.expense-amount-row{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;padding:13px;border-radius:12px;background:var(--bg-muted);margin-bottom:12px}.expense-amount-label{font-size:9.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--txt-muted);margin-bottom:3px}.expense-amount{font-size:21px;font-weight:850;letter-spacing:-.035em;color:var(--clr-primary);overflow-wrap:anywhere;text-align:right}.expense-due{font-size:11px;color:var(--txt-secondary);font-weight:700;min-width:82px}
.expense-meta{display:flex;gap:8px;align-items:flex-start;color:var(--txt-secondary);font-size:11.5px;line-height:1.5;margin-bottom:9px;overflow-wrap:anywhere}.expense-meta-icon{flex:0 0 20px;height:20px;display:grid;place-items:center;border-radius:6px;background:var(--bg-muted)}
.expense-card-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:14px;border-top:1px solid var(--border)}.expense-status{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;font-size:9.5px;font-weight:850;text-transform:uppercase;letter-spacing:.025em;white-space:nowrap}.expense-status:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;box-shadow:0 0 0 3px color-mix(in srgb,currentColor 15%,transparent)}.status-paid{background:rgba(245,158,11,.13);color:#b45309}.status-confirmed{background:rgba(16,185,129,.13);color:#047857}.status-unpaid{background:rgba(239,68,68,.12);color:#dc2626}
.expense-actions{display:flex;align-items:center;justify-content:flex-end;gap:5px;flex-wrap:wrap}.expense-action{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-width:32px;height:32px;padding:0 9px;border:1px solid var(--border);border-radius:8px;background:var(--bg-muted);color:var(--txt-secondary);font-size:11px;font-weight:800}.expense-action:hover{border-color:var(--clr-primary);color:var(--clr-primary)}.expense-action.pay{background:var(--expense-green);border-color:var(--expense-green);color:#fff}.expense-action.confirm{background:#7c3aed;border-color:#7c3aed;color:#fff}.expense-action.delete{color:#dc2626}.expense-action.delete:hover{background:#ef4444;border-color:#ef4444;color:#fff}.expense-empty{grid-column:1/-1;padding:56px 20px;text-align:center;border:1px dashed var(--border);border-radius:16px;background:var(--bg-surface);color:var(--txt-muted)}
.expense-modal .modal-box{width:min(560px,calc(100vw - 24px));max-height:90vh;overflow:hidden;border:1px solid var(--border);border-radius:18px;background:var(--bg-surface);box-shadow:0 24px 70px rgba(15,23,42,.22)}.expense-modal .modal-body{overflow-y:auto;max-height:calc(90vh - 135px);padding:20px}.expense-modal .modal-header,.expense-modal .modal-footer{padding:15px 20px}
.expense-page>.row .kpi-card{position:relative;overflow:hidden;height:100%;min-height:142px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--bg-surface);box-shadow:0 4px 18px rgba(15,23,42,.045)}
.expense-page>.row .kpi-header{margin-bottom:13px}.expense-page>.row .kpi-icon-wrapper{width:42px;height:42px;border-radius:12px}.expense-page>.row .kpi-val{font-size:clamp(1.35rem,2vw,1.75rem);letter-spacing:-.035em}.expense-page>.row .kpi-label{margin-top:6px}
.expense-toolbar>.d-flex{display:none!important}.expense-card-head{min-width:0}.expense-card-head>div:first-child{min-width:0;flex:1}.expense-card-head h5{font-size:15px;line-height:1.38;overflow-wrap:anywhere;color:var(--txt-primary)!important}.expense-card-price{flex:0 0 105px;min-width:0}.expense-card-price .fs-5{font-size:18px!important;overflow-wrap:anywhere}.expense-card-note{line-height:1.5;overflow-wrap:anywhere}.expense-card-footer-old{gap:10px}.expense-card-footer-old>div:last-child{flex-wrap:wrap;justify-content:flex-end}
.expense-grid>.col-12{grid-column:1/-1;width:100%;padding:55px 20px!important;border:1px dashed var(--border);border-radius:16px;background:var(--bg-surface)}.expense-card-footer-old .badge{border-radius:999px!important;font-size:9.5px!important;font-weight:800!important;padding:6px 9px!important;white-space:normal}.expense-card-footer-old .btn{min-height:31px;border-radius:8px!important;font-size:10.5px!important;font-weight:800!important}.expense-card-footer-old .btn-secondary{background:var(--bg-muted);border-color:var(--border);color:var(--txt-secondary)}
@media(max-width:1199.98px){.expense-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){.expense-page>.row>.col-md-3{width:50%}.expense-hero{align-items:stretch;flex-direction:column}.expense-primary-btn{width:100%}.expense-toolbar{align-items:stretch;flex-direction:column}.expense-search{max-width:none;width:100%}.expense-filters{width:100%}.expense-filter{flex:0 0 auto}.expense-grid{grid-template-columns:1fr}.expense-card{min-height:0}.expense-card-footer-old{align-items:flex-start!important;flex-direction:column}.expense-card-footer-old>div:last-child{width:100%;justify-content:flex-start}.expense-modal .row>[class*="col-"]{width:100%}}
@media(max-width:420px){.expense-kpi{padding:14px;min-height:132px}.expense-kpi-value{font-size:1.25rem}.expense-amount-row{align-items:flex-start;flex-direction:column}.expense-amount{text-align:left}.expense-actions{display:grid;grid-template-columns:1fr 36px 36px}.expense-action{width:100%}}
</style>
@endpush

@section('page-content')
<div x-data="expensesApp()" x-init="init()" x-cloak class="expense-page">
    <div class="expense-hero">
        <div><div class="expense-eyebrow">Finance Operations</div><h1 class="expense-title">Expense &amp; Pembayaran</h1><p class="expense-subtitle">Pantau tagihan rutin, status pembayaran, dan kebutuhan operasional dalam satu tampilan.</p></div>
        <button class="expense-primary-btn" @click="openCreate()"><span>＋</span> Tambah Pembayaran</button>
    </div>
    <!-- Stats Banner -->
    <div class="row g-3 mb-1">
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper expense-theme">⚠️</span>
                    <span class="kpi-badge expense-badge">Belum Bayar</span>
                </div>
                <div class="kpi-val text-danger" x-text="unpaidCount"></div>
                <div class="kpi-label">Belum Bayar Bulan Ini</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper profit-theme">✅</span>
                    <span class="kpi-badge profit-badge">Sudah Bayar</span>
                </div>
                <div class="kpi-val text-success" x-text="paidCount"></div>
                <div class="kpi-label">Sudah Bayar Bulan Ini</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper revenue-theme">💸</span>
                    <span class="kpi-badge revenue-badge">Terjadwal</span>
                </div>
                <div class="kpi-val" x-text="'Rp ' + formatNumber(totalExpense)"></div>
                <div class="kpi-label">Total Pengeluaran Rutin</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper asset-theme">📅</span>
                    <span class="kpi-badge asset-badge">Jatuh Tempo</span>
                </div>
                <div class="kpi-val text-warning" x-text="dueSoonCount"></div>
                <div class="kpi-label">Jatuh Tempo Minggu Ini</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="expense-toolbar">
        <div class="expense-search"><span>⌕</span><input x-model="search" class="form-input" placeholder="Cari nama, catatan, bank, atau nominal..." /></div>
        <div class="expense-filters">
            <button class="expense-filter" :class="statusFilter === 'all' && 'active'" @click="statusFilter='all'">Semua <span x-text="expenses.length"></span></button>
            <button class="expense-filter" :class="statusFilter === 'unpaid' && 'active'" @click="statusFilter='unpaid'">Belum Bayar <span x-text="unpaidCount"></span></button>
            <button class="expense-filter" :class="statusFilter === 'paid' && 'active'" @click="statusFilter='paid'">Sudah Bayar <span x-text="paidCount"></span></button>
            <button class="expense-filter" :class="statusFilter === 'revenue' && 'active'" @click="statusFilter='revenue'">Revenue Sharing</button>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="fw-bold m-0 text-dark">📋 Daftar Pembayaran Rutin &amp; Operational Expense</h5>
            <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 text-white shadow-sm" @click="openCreate()" style="background:var(--clr-primary, #ea580c); border:none; padding:8px 14px; font-weight:600; font-size:13px; border-radius:8px">
                ➕ Tambah Pembayaran
            </button>
        </div>
    </div>

    <!-- Expense Cards Grid -->
    <div class="expense-grid">
        <template x-if="loading">
            <div class="col-12 text-center py-5">⏳ Memuat daftar pengeluaran...</div>
        </template>
        <template x-if="!loading && filteredExpenses.length === 0">
            <div class="col-12 text-center py-5 text-muted">Belum ada pengeluaran rutin terdaftar.</div>
        </template>
        <template x-for="exp in filteredExpenses" :key="exp.id">
            <div style="display:contents">
                <div class="expense-card" :style="exp.is_revenue_sharing ? '--card-accent:#7c3aed' : '--card-accent:var(--clr-primary)'">
                    <div class="expense-card-accent"></div>
                    <div class="expense-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2 expense-card-head">
                        <div>
                            <span x-show="exp.is_revenue_sharing" class="badge mb-1" style="background:#7c3aed;color:#fff;font-size:10px; font-weight:600">💼 Revenue Sharing Admin</span>
                            <span class="badge badge-info mb-1" x-text="freqLabel(exp.frequency)"></span>
                            <h5 class="fw-bold text-dark mt-1 mb-0" x-text="exp.name"></h5>
                        </div>
                        <div class="text-end expense-card-price">
                            <div class="fw-bold text-primary fs-5" x-text="'Rp ' + formatNumber(exp.amount)"></div>
                            <small class="text-muted d-block" x-text="'📅 ' + (exp.next_due || 'Tiap Bulan')"></small>
                        </div>
                    </div>

                    <div x-show="exp.bank_name" class="small text-muted mb-2">
                        🏦 <span x-text="exp.bank_name"></span> • <span x-text="exp.bank_account"></span> (a.n. <strong x-text="exp.account_holder || '-'"></strong>)
                    </div>
                    <div x-show="exp.notes || exp.description" class="small text-muted p-2 rounded mb-3" style="background:var(--bg-muted, #f1f5f9)" x-text="'📝 ' + (exp.notes || exp.description)"></div>

                    <div class="border-top pt-2.5 mt-auto d-flex justify-content-between align-items-center expense-card-footer-old">
                        <div>
                            <template x-if="exp.status === 'confirmed' || exp.latest_payment?.status === 'confirmed'">
                                <span class="badge bg-success text-white" style="font-size:11px; padding:5px 9px">🟢 Confirmed</span>
                            </template>
                            <template x-if="(exp.status === 'paid' || exp.latest_payment?.status === 'paid') && exp.status !== 'confirmed'">
                                <span class="badge bg-warning text-dark" style="font-size:11px; padding:5px 9px">🟡 Paid (Menunggu Confirm)</span>
                            </template>
                            <template x-if="exp.status !== 'paid' && exp.status !== 'confirmed' && exp.latest_payment?.status !== 'paid'">
                                <span class="badge bg-danger text-white" style="font-size:11px; padding:5px 9px">❌ Belum Bayar</span>
                            </template>
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            <!-- If Paid & Revenue Sharing: allow Operator to Confirm -->
                            <button x-show="exp.status === 'paid' && exp.is_revenue_sharing" class="btn btn-success btn-sm fw-bold text-white px-2 py-1" style="font-size:12px; border-radius:6px" @click="confirmExpense(exp)" title="Konfirmasi Pembayaran Operator">✓ Confirm</button>
                            
                            <!-- CEO Pay Button -->
                            <button x-show="exp.status !== 'paid' && exp.status !== 'confirmed' && exp.latest_payment?.status !== 'paid'" class="btn btn-success btn-sm fw-bold px-2 py-1" style="font-size:12px; border-radius:6px" @click="openPay(exp)">💰 Bayar</button>
                            
                            <button x-show="exp.status === 'paid' && !exp.is_revenue_sharing" class="btn btn-secondary btn-sm px-2 py-1" style="font-size:12px; border-radius:6px" @click="unmarkPaid(exp)">↩ Batal</button>
                            <button class="btn btn-secondary btn-sm px-2 py-1" style="font-size:12px; border-radius:6px" @click="openEdit(exp)">✏️</button>
                            <button class="btn btn-danger btn-sm px-2 py-1" style="font-size:12px; border-radius:6px" @click="delExpense(exp)">🗑</button>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Modal Form Expense -->
    <div x-show="showModal" class="modal-overlay expense-modal" @click.self="showModal=false" style="display:flex">
        <div class="modal-box" style="max-width:550px; border-radius:14px">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold" x-text="editId ? '✏️ Edit Pembayaran' : '➕ Tambah Pembayaran Rutin'"></h5>
                <button class="btn btn-secondary btn-sm" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label">Nama Pembayaran *</label>
                    <input x-model="form.name" class="form-input" placeholder="mis. Sewa Tempat / Listrik / Gaji Karyawan" />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Nominal (Rp) *</label>
                        <input x-model.number="form.amount" type="number" class="form-input" placeholder="500000" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Frekuensi *</label>
                        <select x-model="form.frequency" class="form-input">
                            <option value="daily">📆 Harian</option>
                            <option value="weekly">📅 Mingguan</option>
                            <option value="monthly">🗓️ Bulanan</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Nama Bank</label>
                        <input x-model="form.bank_name" class="form-input" placeholder="BCA / Mandiri" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Nomor Rekening</label>
                        <input x-model="form.bank_account" class="form-input" placeholder="1234567890" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Atas Nama Rekening</label>
                    <input x-model="form.account_holder" class="form-input" placeholder="PT Ratio Coffee" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan Tambahan</label>
                    <textarea x-model="form.notes" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" x-model="form.is_revenue_sharing" class="form-check-input" id="checkRs">
                    <label class="form-check-label fw-bold text-primary" for="checkRs">💼 Ini adalah Pembayaran Revenue Sharing Operator</label>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" @click="showModal=false">Batal</button>
                <button class="btn btn-primary fw-bold" :disabled="saving" @click="saveForm()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Expense</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Pay -->
    <div x-show="showPayModal" class="modal-overlay expense-modal" @click.self="showPayModal=false" style="display:flex">
        <div class="modal-box" style="max-width:440px; border-radius:14px">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold">💰 Catat Pembayaran Expense</h5>
                <button class="btn btn-secondary btn-sm" @click="showPayModal=false">✕</button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label">Pembayaran</label>
                    <input class="form-input" :value="payTarget?.name" disabled style="background:var(--bg-muted)" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Bayar / Jatuh Tempo *</label>
                    <input x-model="payForm.due_date" type="date" class="form-input" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Nominal Bayar (Rp)</label>
                    <input x-model.number="payForm.amount" type="number" class="form-input" />
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Referensi / Bukti Transfer</label>
                    <input x-model="payForm.proof" class="form-input" placeholder="Ref #102938 / Bukti Transfer" />
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" @click="showPayModal=false">Batal</button>
                <button class="btn btn-success fw-bold text-white" :disabled="saving" @click="submitPay()">
                    <span x-show="saving">⏳ Memproses...</span>
                    <span x-show="!saving">Tandai Sudah Bayar</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function expensesApp() {
    return {
        loading: false, saving: false,
        expenses: [], showModal: false, editId: null,
        search: '', statusFilter: 'all',
        form: { name: '', amount: 0, frequency: 'monthly', bank_name: '', bank_account: '', account_holder: '', notes: '', is_revenue_sharing: false },
        showPayModal: false, payTarget: null,
        payForm: { due_date: new Date().toISOString().substr(0,10), amount: 0, proof: '' },

        get unpaidCount() { return this.expenses.filter(e => e.status !== 'paid' && e.status !== 'confirmed' && e.latest_payment?.status !== 'paid').length; },
        get paidCount() { return this.expenses.filter(e => e.status === 'paid' || e.status === 'confirmed' || e.latest_payment?.status === 'paid').length; },
        get totalExpense() { return this.expenses.reduce((s,e) => s + Number(e.amount||0), 0); },
        get dueSoonCount() { return this.expenses.filter(e => e.frequency === 'daily' || e.frequency === 'weekly').length; },
        get filteredExpenses() {
            const keyword = this.search.trim().toLowerCase();
            return this.expenses.filter(exp => {
                const paid = exp.status === 'paid' || exp.status === 'confirmed' || exp.latest_payment?.status === 'paid' || exp.latest_payment?.status === 'confirmed';
                const statusMatch = this.statusFilter === 'all'
                    || (this.statusFilter === 'paid' && paid)
                    || (this.statusFilter === 'unpaid' && !paid)
                    || (this.statusFilter === 'revenue' && Boolean(exp.is_revenue_sharing));
                const haystack = [exp.name, exp.description, exp.notes, exp.bank_name, exp.bank_account, exp.amount]
                    .filter(Boolean).join(' ').toLowerCase();
                return statusMatch && (!keyword || haystack.includes(keyword));
            });
        },

        async init() { await this.load(); },

        async load() {
            this.loading = true;
            try {
                const r = await axios.get('/api/v1/expenses');
                this.expenses = r.data.data ?? r.data ?? [];
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        freqLabel(f) { return { daily:'Harian', weekly:'Mingguan', monthly:'Bulanan' }[f] || f; },

        openCreate() {
            this.editId = null;
            this.form = { name: '', amount: 0, frequency: 'monthly', bank_name: '', bank_account: '', account_holder: '', notes: '', is_revenue_sharing: false };
            this.showModal = true;
        },

        openEdit(exp) {
            this.editId = exp.id;
            this.form = { ...exp, name: exp.name || exp.description || '' };
            this.showModal = true;
        },

        async saveForm() {
            this.saving = true;
            try {
                if (this.editId) {
                    await axios.put(`/api/v1/expenses/${this.editId}`, this.form);
                    toast('Expense berhasil diperbarui!');
                } else {
                    await axios.post('/api/v1/expenses', this.form);
                    toast('Expense baru ditambahkan!');
                }
                this.showModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan expense', 'error');
            } finally { this.saving = false; }
        },

        openPay(exp) {
            this.payTarget = exp;
            this.payForm = { due_date: new Date().toISOString().substr(0,10), amount: exp.amount, proof: '' };
            this.showPayModal = true;
        },

        async submitPay() {
            this.saving = true;
            try {
                await axios.post(`/api/v1/expenses/${this.payTarget.id}/mark-paid`, this.payForm);
                toast('Pembayaran berhasil dicatat! Status: Paid');
                this.showPayModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal mencatat pembayaran', 'error');
            } finally { this.saving = false; }
        },

        async confirmExpense(exp) {
            try {
                await axios.post(`/api/v1/expenses/${exp.id}/confirm`);
                toast('Pembayaran Revenue Sharing berhasil dikonfirmasi Operator!');
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal mengonfirmasi pembayaran', 'error');
            }
        },

        async unmarkPaid(exp) {
            try {
                await axios.post(`/api/v1/expenses/${exp.id}/mark-unpaid`);
                toast('Status pembayaran diubah ke Belum Bayar!');
                await this.load();
            } catch(e) {
                toast('Gagal mengubah status', 'error');
            }
        },

        delExpense(exp) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/expenses/${exp.id}`);
                    toast('Expense berhasil dihapus!');
                    await this.load();
                } catch(e) {
                    toast('Gagal menghapus expense', 'error');
                }
            }, exp.name || exp.description);
        },

        formatNumber
    }
}
</script>
@endpush
