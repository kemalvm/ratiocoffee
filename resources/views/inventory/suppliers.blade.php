@extends('layouts.erp')
@section('title', 'Master Supplier – Ratio Coffee ERP')

@push('styles')
<style>
/* Action buttons in Master Supplier */
.btn-supplier-tempo {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    border: 1.5px solid rgba(239, 68, 68, 0.45) !important;
    background-color: rgba(239, 68, 68, 0.06);
    color: #dc2626 !important;
    transition: all 0.2s ease-in-out;
}
.btn-supplier-tempo:hover {
    border-color: rgba(239, 68, 68, 0.75) !important;
    background-color: rgba(239, 68, 68, 0.16) !important;
    color: #b91c1c !important;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15);
}

.btn-supplier-edit {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 11px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    border: 1.5px solid rgba(148, 163, 184, 0.6) !important;
    background-color: var(--bg-surface, #ffffff);
    color: var(--txt-primary, #334155) !important;
    transition: all 0.2s ease-in-out;
}
.btn-supplier-edit:hover {
    border-color: rgba(59, 130, 246, 0.7) !important;
    background-color: rgba(59, 130, 246, 0.1) !important;
    color: #2563eb !important;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.15);
}

.btn-supplier-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 9px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    border: 1.5px solid rgba(239, 68, 68, 0.35) !important;
    background-color: rgba(239, 68, 68, 0.05);
    color: #ef4444 !important;
    transition: all 0.2s ease-in-out;
}
.btn-supplier-del:hover {
    border-color: rgba(239, 68, 68, 0.75) !important;
    background-color: rgba(239, 68, 68, 0.2) !important;
    color: #b91c1c !important;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.15);
}

/* Dark Mode Responsive Overrides */
[data-theme="dark"] .btn-supplier-tempo {
    border-color: rgba(248, 113, 113, 0.5) !important;
    background-color: rgba(239, 68, 68, 0.15);
    color: #fca5a5 !important;
}
[data-theme="dark"] .btn-supplier-tempo:hover {
    border-color: rgba(248, 113, 113, 0.85) !important;
    background-color: rgba(239, 68, 68, 0.3) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

[data-theme="dark"] .btn-supplier-edit {
    border-color: rgba(148, 163, 184, 0.45) !important;
    background-color: rgba(30, 41, 59, 0.8);
    color: #e2e8f0 !important;
}
[data-theme="dark"] .btn-supplier-edit:hover {
    border-color: rgba(96, 165, 250, 0.8) !important;
    background-color: rgba(59, 130, 246, 0.25) !important;
    color: #93c5fd !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
}

[data-theme="dark"] .btn-supplier-del {
    border-color: rgba(248, 113, 113, 0.45) !important;
    background-color: rgba(239, 68, 68, 0.15);
    color: #fca5a5 !important;
}
[data-theme="dark"] .btn-supplier-del:hover {
    border-color: rgba(248, 113, 113, 0.85) !important;
    background-color: rgba(239, 68, 68, 0.3) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

[data-theme="dark"] .due-breakdown-item {
    background: rgba(239, 68, 68, 0.14) !important;
    border-color: rgba(239, 68, 68, 0.35) !important;
    color: #fecdd3 !important;
}
[data-theme="dark"] .due-breakdown-item span strong {
    color: #ffffff !important;
}

[data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(1.8) hue-rotate(180deg);
    cursor: pointer;
}
</style>
@endpush

@section('page-content')
<div x-data="suppliersApp()" x-init="init()" x-cloak class="d-flex flex-column gap-4">

    <!-- Page Title & Header Banner -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                🏢 Master Supplier &amp; Mitra Vendor
            </h4>
            <p class="text-muted small m-0 mt-1">Kelola data mitra supplier, preferensi pembayaran (Tunai/Tempo), dan penjadwalan jatuh tempo secara terpusat.</p>
        </div>
        <div>
            <button class="btn btn-primary fw-bold d-inline-flex align-items-center gap-2 px-3.5 py-2 shadow-sm" style="border-radius:10px; background:linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark)); border:none" @click="openForm()">
                <span>➕</span> Tambah Supplier Baru
            </button>
        </div>
    </div>

    <!-- Quick Metric Cards Overview -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-revenue">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper revenue-theme">🏢</span>
                    <span class="kpi-badge revenue-badge">Terdaftar</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Supplier</div>
                    <div class="kpi-val" x-text="suppliers.length"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-expense">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper expense-theme">🗓️</span>
                    <span class="kpi-badge expense-badge">Kredit &amp; PO</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Mitra Pembayaran Tempo</div>
                    <div class="kpi-val text-danger" x-text="countTempo"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-profit">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper profit-theme">💵</span>
                    <span class="kpi-badge profit-badge">Cash / COD</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Mitra Pembayaran Tunai</div>
                    <div class="kpi-val text-success" x-text="countCash"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar Filters & Search Bar -->
    <div class="card p-3 shadow-sm" style="border-radius:14px; background:var(--bg-surface); border:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Segmented Filter Pills -->
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterMethod === 'all' ? 'btn-primary' : 'btn-outline-secondary'" style="border-radius:8px" @click="filterMethod = 'all'">
                    Semua Supplier
                </button>
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterMethod === 'tempo' ? 'btn-danger' : 'btn-outline-secondary'" style="border-radius:8px" @click="filterMethod = 'tempo'">
                    🗓️ Pembayaran Tempo
                </button>
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterMethod === 'cash' ? 'btn-success' : 'btn-outline-secondary'" style="border-radius:8px" @click="filterMethod = 'cash'">
                    💵 Tunai / COD
                </button>
            </div>
            <!-- Live Search Box -->
            <div class="position-relative" style="width:280px">
                <input x-model="search" @input.debounce.300ms="load()" class="form-control form-control-sm ps-4" style="border-radius:10px; background:var(--bg-muted); border:1px solid var(--border)" placeholder="Cari supplier / PIC / WA..." />
                <span class="position-absolute top-50 start-0 translate-middle-y ms-2.5 text-muted" style="font-size:12px">🔍</span>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card p-0 shadow-sm" style="border-radius:16px; overflow:hidden; background:var(--bg-surface); border:1px solid var(--border)">
        <div class="table-responsive">
            <table class="data-table mb-0">
                <thead>
                    <tr>
                        <th style="padding-left:20px">Nama Supplier</th>
                        <th>Produk &amp; Harga Beli</th>
                        <th>Kontak &amp; PIC</th>
                        <th>Chat &amp; WA</th>
                        <th>Tipe Pembayaran</th>
                        <th>Tenggat Waktu &amp; Tagihan Tempo</th>
                        <th>Alamat Vendor</th>
                        <th class="text-end" style="padding-right:20px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                <span>Memuat data supplier...</span>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && filteredSuppliers.length === 0">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div style="font-size:40px" class="mb-2">🏢</div>
                                <div class="fw-bold">Belum Ada Supplier Terdaftar</div>
                                <div class="small">Tidak ada data supplier yang sesuai dengan kriteria pencarian.</div>
                            </td>
                        </tr>
                    </template>
                    <template x-for="s in filteredSuppliers" :key="s.id">
                        <tr class="align-middle">
                            <td style="padding-left:20px">
                                <div class="fw-bold text-dark" style="font-size:14px" x-text="s.name"></div>
                                <div class="text-muted small" x-text="s.code ? 'Kode: ' + s.code : 'SUP-00' + s.id"></div>
                            </td>
                            <td>
                                <template x-if="s.ingredients && s.ingredients.length > 0">
                                    <div>
                                        <div class="d-flex flex-column gap-1" style="max-height:100px; overflow-y:auto">
                                            <template x-for="ing in s.ingredients" :key="ing.id">
                                                <div class="d-flex align-items-center justify-content-between gap-2 p-1 px-2 rounded" style="background:var(--bg-muted); font-size:11px">
                                                    <span class="fw-bold text-dark" x-text="ing.name"></span>
                                                    <span class="fw-semibold text-primary" x-text="formatMoney(ing.last_purchase_price || (ing.average_cost * (ing.net_content || 1))) + ' / ' + (ing.unit?.symbol || 'unit')"></span>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="mt-1 pt-1 border-top d-flex justify-content-between align-items-center">
                                            <span class="text-muted" style="font-size:10px">Total Pembelian:</span>
                                            <span class="fw-bold text-success" style="font-size:11px" x-text="formatMoney(getSupplierTotalBill(s))"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!s.ingredients || s.ingredients.length === 0">
                                    <span class="text-muted small">— Belum Ada Produk —</span>
                                </template>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark" x-text="s.contact_person || '—'"></div>
                                <div class="text-muted small" x-text="s.phone || '—'"></div>
                            </td>
                            <td>
                                <template x-if="s.phone">
                                    <a :href="getWaLink(s.phone)" target="_blank" class="btn fw-semibold text-white d-inline-flex align-items-center gap-1.5 shadow-sm" style="background:linear-gradient(135deg, #25D366, #128C7E); border:none; border-radius:20px; font-size:11.5px; padding:4px 12px; text-decoration:none">
                                        💬 Chat WA
                                    </a>
                                </template>
                                <template x-if="!s.phone">
                                    <span class="text-muted small">No WA —</span>
                                </template>
                            </td>
                            <td>
                                <template x-if="s.payment_method === 'credit' || s.payment_method === 'tempo'">
                                    <span class="kpi-badge expense-badge">🗓️ Tempo (Kredit)</span>
                                </template>
                                <template x-if="s.payment_method !== 'credit' && s.payment_method !== 'tempo'">
                                    <span class="kpi-badge profit-badge">💵 Tunai (Cash)</span>
                                </template>
                            </td>
                            <td>
                                <template x-if="s.payment_method === 'credit' || s.payment_method === 'tempo'">
                                    <div>
                                        <div :class="getDueDateInfo(s).badgeClass" x-text="getDueDateInfo(s).text"></div>
                                        <div class="mt-1 d-flex flex-column gap-1">
                                            <template x-for="(item, idx) in getDueDateBreakdown(s)" :key="idx">
                                                <div class="d-flex align-items-center justify-content-between gap-1.5 p-1 px-2 border rounded" style="background:rgba(239,68,68,0.04); border-color:rgba(239,68,68,0.2) !important; font-size:10.5px">
                                                    <span>📅 <strong x-text="item.formattedDate"></strong></span>
                                                    <span class="badge bg-danger text-white" x-text="item.formattedAmount"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="s.payment_method !== 'credit' && s.payment_method !== 'tempo'">
                                    <span class="text-muted">—</span>
                                </template>
                            </td>
                            <td>
                                <small class="text-muted" style="max-width:180px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap" x-text="s.address || '—'"></small>
                            </td>
                            <td class="text-end" style="padding-right:20px">
                                <div class="d-flex justify-content-end gap-1.5">
                                    <template x-if="s.payment_method === 'credit' || s.payment_method === 'tempo'">
                                        <button class="btn btn-supplier-tempo" @click="openExtendModal(s)" title="Perpanjang Tanggal Jatuh Tempo">
                                            ⏰ + Tempo
                                        </button>
                                    </template>
                                    <button class="btn btn-supplier-edit" @click="openForm(s)" title="Edit Supplier">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-supplier-del" @click="delSupplier(s)" title="Hapus Supplier">
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Tambah / Edit Supplier -->
    <div x-show="showModal" class="modal-overlay" @click.self="showModal=false" style="display:flex">
        <div class="modal-box" style="max-width:560px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark" x-text="editId ? '✏️ Edit Supplier' : '🏢 Tambah Supplier Baru'"></h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body py-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Supplier / PT *</label>
                    <input x-model="form.name" class="form-input" placeholder="mis. PT Diamond Dairy Products" />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Nama PIC Kontak</label>
                        <input x-model="form.contact_person" class="form-input" placeholder="mis. Rina / Hendra" />
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Telepon / WhatsApp *</label>
                        <input x-model="form.phone" class="form-input" placeholder="mis. 085711223344" />
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Email Supplier</label>
                        <input x-model="form.email" type="email" class="form-input" placeholder="sales@vendor.com" />
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tipe Pembayaran *</label>
                        <select x-model="form.payment_method" class="form-input">
                            <option value="cash">💵 Tunai (Cash / COD)</option>
                            <option value="credit">🗓️ Tempo (Jatuh Tempo / Kredit)</option>
                        </select>
                    </div>
                </div>

                <!-- Section Input Tempo bila Tipe Pembayaran = credit -->
                <div x-show="form.payment_method === 'credit'" x-transition class="p-3 mb-3 border rounded" style="background: rgba(239, 68, 68, 0.03); border-color: rgba(239, 68, 68, 0.2) !important">
                    <div class="d-flex align-items-center justify-content-between mb-2.5">
                        <h6 class="fw-bold text-danger m-0" style="font-size:13px">🗓️ Pengaturan Pembayaran &amp; Multi-Tanggal Tempo</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-2 py-1 d-inline-flex align-items-center gap-1" style="font-size:11px; border-radius:6px" @click="addDueDate()">
                            ➕ Tambah Tanggal Tempo
                        </button>
                    </div>

                    <!-- Summary calculation preview -->
                    <div class="p-2 mb-3 rounded border bg-white d-flex justify-content-between align-items-center" style="font-size:11.5px">
                        <div>
                            <span class="text-muted">Total Pembayaran Produk:</span>
                            <strong class="text-dark ms-1" x-text="formatMoney(getFormTotalBill())"></strong>
                        </div>
                        <div>
                            <span class="text-muted">Hitungan per Tanggal:</span>
                            <strong class="text-danger ms-1" x-text="formatMoney(getFormTotalBill() / (form.payment_due_dates.length || 1)) + ' / tanggal'"></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Tenggat Waktu Utama (Jumlah Hari Default)</label>
                        <input x-model.number="form.payment_term_days" @input="onTermDaysChange()" type="number" class="form-input" placeholder="mis. 7, 14, 30" />
                    </div>

                    <label class="form-label small fw-semibold text-muted mb-1">Daftar Tanggal Jatuh Tempo &amp; Nominal Angsuran (Hitung Otomatis):</label>
                    <div class="d-flex flex-column gap-2 mb-2">
                        <template x-for="(dDate, idx) in form.payment_due_dates" :key="idx">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary" style="font-size:10px; width:44px" x-text="'Tgl ' + (idx + 1)"></span>
                                <input type="date" class="form-input" :value="dDate" @input="updateDueDate(idx, $event.target.value)" />
                                <span class="badge bg-danger text-white px-2 py-1.5 fw-bold" style="font-size:11px; white-space:nowrap" x-text="formatMoney(getFormTotalBill() / form.payment_due_dates.length)"></span>
                                <button type="button" class="btn btn-outline-danger btn-sm px-2 py-1" @click="removeDueDate(idx)" :disabled="form.payment_due_dates.length <= 1" title="Hapus Tanggal Ini">
                                    🗑
                                </button>
                            </div>
                        </template>
                    </div>
                    <small class="text-muted d-block" style="font-size:11px">
                        💡 Setiap tanggal jatuh tempo yang ditambahkan secara otomatis menghitung pembagian total harga pembayaran.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Alamat Lengkap</label>
                    <textarea x-model="form.address" class="form-input" rows="2" placeholder="Alamat kantor / gudang supplier"></textarea>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showModal=false">Batal</button>
                <button class="btn btn-primary fw-bold" style="border-radius:8px; background:linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark)); border:none" :disabled="saving" @click="saveSupplier()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Supplier</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Perpanjang Tenggat Waktu (Extend Due Date) -->
    <div x-show="showExtendModal" class="modal-overlay" @click.self="showExtendModal=false" style="display:flex">
        <div class="modal-box" style="max-width:480px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                    ⏰ Perpanjang Tenggat Waktu Tempo
                </h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showExtendModal=false">✕</button>
            </div>
            <div class="modal-body py-3">
                <template x-if="selectedSupplier">
                    <div>
                        <div class="p-3 mb-3 border rounded" style="background:var(--bg-muted); border-radius:10px">
                            <div class="fw-bold text-dark" x-text="selectedSupplier.name"></div>
                            <small class="text-muted d-block" x-text="'Tanggal Tempo Saat Ini: ' + (selectedSupplier.next_payment_due_date || 'Belum diatur')"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Tanggal Jatuh Tempo Baru *</label>
                            <input x-model="extendForm.new_due_date" type="date" class="form-input" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Atau Tambahkan Hari Tambahan:</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" style="border-radius:6px" @click="addDaysToDueDate(7)">+7 Hari</button>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" style="border-radius:6px" @click="addDaysToDueDate(14)">+14 Hari</button>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" style="border-radius:6px" @click="addDaysToDueDate(30)">+30 Hari</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Catatan / Alasan Perpanjangan</label>
                            <textarea x-model="extendForm.reason" class="form-input" rows="2" placeholder="mis. Kesepakatan restock tambahan / negosiasi tempo vendor"></textarea>
                        </div>
                    </div>
                </template>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showExtendModal=false">Batal</button>
                <button class="btn btn-danger fw-bold" style="border-radius:8px" :disabled="saving" @click="saveExtendDueDate()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Perpanjangan</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function suppliersApp() {
    return {
        loading: false, saving: false, search: '', filterMethod: 'all',
        suppliers: [], showModal: false, showExtendModal: false, editId: null, selectedSupplier: null,
        form: { name: '', contact_person: '', phone: '', email: '', payment_method: 'cash', payment_term_days: 30, next_payment_due_date: '', payment_due_dates: [], address: '' },
        extendForm: { new_due_date: '', reason: '' },

        async init() {
            const token = localStorage.getItem('erp_token');
            if (token) {
                axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
            }
            await this.load();
        },

        async load() {
            this.loading = true;
            try {
                const r = await axios.get('/api/v1/suppliers', { params: { search: this.search } });
                this.suppliers = r.data.data ?? r.data ?? [];
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        get filteredSuppliers() {
            if (this.filterMethod === 'tempo') {
                return this.suppliers.filter(s => s.payment_method === 'credit' || s.payment_method === 'tempo');
            } else if (this.filterMethod === 'cash') {
                return this.suppliers.filter(s => s.payment_method !== 'credit' && s.payment_method !== 'tempo');
            }
            return this.suppliers;
        },

        get countTempo() {
            return this.suppliers.filter(s => s.payment_method === 'credit' || s.payment_method === 'tempo').length;
        },

        get countCash() {
            return this.suppliers.filter(s => s.payment_method !== 'credit' && s.payment_method !== 'tempo').length;
        },

        getSupplierTotalBill(s) {
            if (!s || !s.ingredients || s.ingredients.length === 0) return 0;
            return s.ingredients.reduce((total, ing) => {
                const price = parseFloat(ing.last_purchase_price) || (parseFloat(ing.average_cost) * (parseFloat(ing.net_content) || 1)) || 0;
                const qty = parseFloat(ing.current_stock) > 0 ? parseFloat(ing.current_stock) : 1;
                return total + (price * qty);
            }, 0);
        },

        getFormTotalBill() {
            if (this.editId) {
                const s = this.suppliers.find(x => x.id === this.editId);
                if (s) return this.getSupplierTotalBill(s);
            }
            return 0;
        },

        formatMoney(val) {
            if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
            return 'Rp ' + Math.round(Number(val)).toLocaleString('id-ID');
        },

        getDueDateBreakdown(s) {
            let dates = s.payment_due_dates;
            if (typeof dates === 'string') {
                try { dates = JSON.parse(dates); } catch(e) { dates = []; }
            }
            if (!Array.isArray(dates) || dates.length === 0) {
                if (s.next_payment_due_date) {
                    dates = [s.next_payment_due_date];
                } else {
                    return [];
                }
            }
            const cleanDates = dates.map(d => (typeof d === 'object' && d !== null ? (d.date || '') : d)).filter(Boolean);
            if (cleanDates.length === 0) return [];

            const totalBill = this.getSupplierTotalBill(s);
            const perDateAmount = totalBill > 0 ? Math.round(totalBill / cleanDates.length) : 0;

            return cleanDates.map((dStr) => ({
                date: dStr,
                formattedDate: this.formatDate(dStr),
                amount: perDateAmount,
                formattedAmount: this.formatMoney(perDateAmount)
            }));
        },

        getWaLink(phone) {
            if (!phone) return '#';
            let clean = phone.replace(/[^0-9]/g, '');
            if (clean.startsWith('0')) {
                clean = '62' + clean.substring(1);
            }
            return `https://wa.me/${clean}`;
        },

        getDueDateInfo(s) {
            if (!s.next_payment_due_date) {
                if (s.payment_term_days) {
                    return { text: s.payment_term_days + ' Hari', badgeClass: 'fw-bold text-danger' };
                }
                return { text: 'Belum Diatur', badgeClass: 'text-muted' };
            }
            const today = new Date();
            today.setHours(0,0,0,0);
            const due = new Date(s.next_payment_due_date);
            due.setHours(0,0,0,0);
            const diffTime = due.getTime() - today.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays < 0) {
                return { text: '🚨 Lewat ' + Math.abs(diffDays) + ' Hari', badgeClass: 'fw-bold text-danger' };
            } else if (diffDays === 0) {
                return { text: '⚠️ Jatuh Tempo Hari Ini!', badgeClass: 'fw-bold text-danger' };
            } else {
                return { text: diffDays + ' Hari', badgeClass: 'fw-bold text-danger' };
            }
        },

        getExtraDueDates(s) {
            let dates = s.payment_due_dates;
            if (typeof dates === 'string') {
                try { dates = JSON.parse(dates); } catch(e) { dates = []; }
            }
            if (!Array.isArray(dates) || dates.length <= 1) return [];
            return dates.slice(1);
        },

        getExtraDueDatesFormatted(s) {
            const extras = this.getExtraDueDates(s);
            return extras.map(d => this.formatDate(d)).join(', ');
        },

        formatDate(str) {
            if (!str) return '—';
            const d = new Date(str);
            if (isNaN(d)) return str;
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        getDefaultDueDate(days = 30) {
            const d = new Date();
            d.setDate(d.getDate() + parseInt(days));
            const pad = (n) => n.toString().padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        },

        addDueDate() {
            let base = new Date();
            if (this.form.payment_due_dates.length > 0) {
                const last = this.form.payment_due_dates[this.form.payment_due_dates.length - 1];
                if (last) {
                    base = new Date(last);
                    base.setDate(base.getDate() + 14);
                }
            } else {
                base.setDate(base.getDate() + (this.form.payment_term_days || 30));
            }
            const pad = (n) => n.toString().padStart(2, '0');
            const newDateStr = `${base.getFullYear()}-${pad(base.getMonth() + 1)}-${pad(base.getDate())}`;
            this.form.payment_due_dates.push(newDateStr);
            this.syncNextPaymentDueDate();
        },

        removeDueDate(idx) {
            if (this.form.payment_due_dates.length > 1) {
                this.form.payment_due_dates.splice(idx, 1);
                this.syncNextPaymentDueDate();
            } else {
                toast('Minimal harus ada 1 tanggal jatuh tempo untuk pembayaran tempo.', 'warning');
            }
        },

        updateDueDate(idx, val) {
            this.form.payment_due_dates[idx] = val;
            this.syncNextPaymentDueDate();
        },

        syncNextPaymentDueDate() {
            const valid = this.form.payment_due_dates.filter(d => d);
            if (valid.length > 0) {
                const sorted = [...valid].sort();
                this.form.next_payment_due_date = sorted[0];
            } else {
                this.form.next_payment_due_date = '';
            }
        },

        onTermDaysChange() {
            if (!this.form.payment_term_days || this.form.payment_term_days <= 0) return;
            const firstDate = this.getDefaultDueDate(this.form.payment_term_days);
            if (!this.form.payment_due_dates || this.form.payment_due_dates.length === 0) {
                this.form.payment_due_dates = [firstDate];
            } else {
                this.form.payment_due_dates[0] = firstDate;
            }
            this.syncNextPaymentDueDate();
        },

        openForm(s = null) {
            if (s) {
                this.editId = s.id;
                let dueDates = s.payment_due_dates;
                if (typeof dueDates === 'string') {
                    try { dueDates = JSON.parse(dueDates); } catch(e) { dueDates = []; }
                }
                if (!Array.isArray(dueDates) || dueDates.length === 0) {
                    dueDates = s.next_payment_due_date ? [s.next_payment_due_date] : [];
                }
                const defaultDate = this.getDefaultDueDate(s.payment_term_days || 30);
                this.form = {
                    name: s.name,
                    contact_person: s.contact_person || '',
                    phone: s.phone || '',
                    email: s.email || '',
                    payment_method: s.payment_method || 'cash',
                    payment_term_days: s.payment_term_days || 30,
                    next_payment_due_date: s.next_payment_due_date || defaultDate,
                    payment_due_dates: dueDates.length ? dueDates : [defaultDate],
                    address: s.address || ''
                };
            } else {
                this.editId = null;
                const defaultDate = this.getDefaultDueDate(30);
                this.form = {
                    name: '',
                    contact_person: '',
                    phone: '',
                    email: '',
                    payment_method: 'cash',
                    payment_term_days: 30,
                    next_payment_due_date: defaultDate,
                    payment_due_dates: [defaultDate],
                    address: ''
                };
            }
            this.showModal = true;
        },

        openExtendModal(s) {
            this.selectedSupplier = s;
            let current = s.next_payment_due_date ? new Date(s.next_payment_due_date) : new Date();
            current.setDate(current.getDate() + 14); // default extend +14 days
            const pad = (n) => n.toString().padStart(2, '0');
            const defaultNewDate = `${current.getFullYear()}-${pad(current.getMonth() + 1)}-${pad(current.getDate())}`;

            this.extendForm = {
                new_due_date: defaultNewDate,
                reason: ''
            };
            this.showExtendModal = true;
        },

        addDaysToDueDate(days) {
            let base = this.selectedSupplier?.next_payment_due_date ? new Date(this.selectedSupplier.next_payment_due_date) : new Date();
            base.setDate(base.getDate() + parseInt(days));
            const pad = (n) => n.toString().padStart(2, '0');
            this.extendForm.new_due_date = `${base.getFullYear()}-${pad(base.getMonth() + 1)}-${pad(base.getDate())}`;
        },

        async saveSupplier() {
            if (!this.form.name || !this.form.name.trim()) {
                toast('Nama supplier wajib diisi', 'error'); return;
            }
            this.saving = true;
            try {
                const payload = {
                    ...this.form,
                    email: this.form.email ? this.form.email.trim() : null,
                    phone: this.form.phone ? this.form.phone.trim() : null,
                    contact_person: this.form.contact_person ? this.form.contact_person.trim() : null,
                    address: this.form.address ? this.form.address.trim() : null,
                };
                if (this.editId) {
                    await axios.put(`/api/v1/suppliers/${this.editId}`, payload);
                    toast('Data supplier berhasil diperbarui!');
                } else {
                    await axios.post('/api/v1/suppliers', payload);
                    toast('Supplier baru berhasil ditambahkan!');
                }
                this.showModal = false;
                await this.load();
            } catch(e) {
                const msg = e.response?.data?.errors 
                    ? Object.values(e.response.data.errors).flat().join(', ') 
                    : (e.response?.data?.message || 'Gagal menyimpan supplier');
                toast(msg, 'error');
            } finally { this.saving = false; }
        },

        async saveExtendDueDate() {
            if (!this.extendForm.new_due_date) {
                toast('Tanggal jatuh tempo baru wajib dipilih', 'error'); return;
            }
            this.saving = true;
            try {
                await axios.post(`/api/v1/suppliers/${this.selectedSupplier.id}/extend-due-date`, this.extendForm);
                toast('Tenggat waktu jatuh tempo supplier berhasil diperpanjang!');
                this.showExtendModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memperpanjang tenggat waktu', 'error');
            } finally { this.saving = false; }
        },

        delSupplier(s) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/suppliers/${s.id}`);
                    toast('Supplier berhasil dihapus!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus supplier', 'error');
                }
            }, s.name);
        }
    }
}
</script>
@endpush
