@extends('layouts.erp')
@section('title', 'Inventory Control Center – Ratio Coffee ERP')

@section('page-content')
<div x-data="inventoryApp()" x-init="init()" x-cloak>
    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper revenue-theme">📦</span>
                    <span class="kpi-badge revenue-badge">Total Item</span>
                </div>
                <div class="kpi-val" x-text="kpis.total_ingredients || 0"></div>
                <div class="kpi-label">Master Bahan Baku</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper expense-theme">⚠️</span>
                    <span class="kpi-badge expense-badge">Critical</span>
                </div>
                <div class="kpi-val text-danger" x-text="kpis.critical_count || 0"></div>
                <div class="kpi-label">Stok Kritis</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper asset-theme">📉</span>
                    <span class="kpi-badge asset-badge">Low Stock</span>
                </div>
                <div class="kpi-val text-warning" x-text="kpis.low_stock_count || 0"></div>
                <div class="kpi-label">Stok Rendah</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper profit-theme">💰</span>
                    <span class="kpi-badge profit-badge">Nilai Aset</span>
                </div>
                <div class="kpi-val" x-text="'Rp ' + formatNumber(kpis.total_value || 0)"></div>
                <div class="kpi-label">Total Nilai Persediaan</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="card p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <!-- Left Filters -->
            <div class="d-flex align-items-center flex-wrap gap-2" style="flex: 1; min-width: 280px;">
                <div style="min-width: 180px; flex: 1;">
                    <input x-model="search" @input.debounce.300ms="load()" class="form-input" style="padding: 8px 14px; font-size: 13px;" placeholder="🔍 Cari nama/kode barang..." />
                </div>
                <div style="flex: 0 0 auto;">
                    <select x-model="filterCategory" @change="load()" class="form-input" style="padding: 8px 28px 8px 12px; font-size: 13px; min-width: 152px;">
                        <option value="">Semua Kategori</option>
                        <template x-for="c in categories" :key="c.id">
                            <option :value="c.id" x-text="c.name"></option>
                        </template>
                    </select>
                </div>
                <div style="flex: 0 0 auto;">
                    <select x-model="filterSupplier" @change="load()" class="form-input" style="padding: 8px 28px 8px 12px; font-size: 13px; min-width: 160px;">
                        <option value="">Semua Supplier</option>
                        <template x-for="s in suppliers" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </div>
                <div style="flex: 0 0 auto;">
                    <select x-model="filterStatus" @change="load()" class="form-input" style="padding: 8px 28px 8px 12px; font-size: 13px; min-width: 132px;">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <div class="form-check m-0 ms-1 d-flex align-items-center" style="white-space: nowrap;">
                    <input type="checkbox" x-model="filterCritical" @change="load()" class="form-check-input" id="checkCrit">
                    <label class="form-check-label text-danger fw-bold ms-1" for="checkCrit" style="font-size:12px; cursor:pointer">Stok Kritis</label>
                </div>
            </div>

            <!-- Right Action Buttons -->
            <div class="d-flex align-items-center gap-2">
                <a href="/inventory/stock-movements" class="btn btn-slate btn-sm">
                    📋 Riwayat Stok
                </a>
                <button type="button" class="btn btn-info btn-sm" @click="openCatModal()">
                    🏷️ Kelola Kategori
                </button>
                <button type="button" class="btn btn-primary btn-sm" @click="openForm()">
                    📦 + Tambah Item
                </button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card p-0 shadow-sm border-0" style="border-radius:12px; overflow:hidden">
        <div class="table-responsive">
            <table class="data-table align-middle">
                <thead>
                    <tr style="background:var(--bg-muted, #f8fafc)">
                        <th class="ps-3 py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Kode</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Nama Item</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Kategori</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Master Supplier</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Stok Saat Ini</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Min Stok</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Harga Pembelian</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Nilai Aset</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="font-size:11px; letter-spacing:0.5px">Status</th>
                        <th class="pe-3 py-3 text-uppercase text-muted small fw-bold text-end" style="font-size:11px; letter-spacing:0.5px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="10" class="text-center py-5 text-muted">⏳ Memuat data inventory...</td></tr>
                    </template>
                    <template x-if="!loading && items.length === 0">
                        <tr><td colspan="10" class="text-center py-5 text-muted">Belum ada item inventory terdaftar.</td></tr>
                    </template>
                    <template x-for="row in items" :key="row.id">
                        <tr class="border-bottom" style="transition: background 0.15s">
                            <td class="ps-3"><span class="fw-bold" style="color:var(--clr-primary, #ea580c); font-size:12px;" x-text="row.code"></span></td>
                            <td class="py-2.5">
                                <div class="fw-bold" x-text="row.name"></div>
                                <span x-show="row.is_critical" class="badge bg-danger text-white mt-0.5" style="font-size:10px; font-weight:600">⚠️ Stok Kritis!</span>
                            </td>
                            <td><span class="badge badge-info text-uppercase" style="font-size:11px; font-weight:600; padding:4px 8px" x-text="row.category?.name || '—'"></span></td>
                            <td>
                                <template x-if="row.default_supplier || row.default_supplier_id">
                                    <div>
                                        <div class="fw-bold" style="font-size:12px; color:var(--txt-primary)" x-text="row.default_supplier?.name || getSupplierName(row.default_supplier_id)"></div>
                                        <div class="text-muted small" style="font-size:10px" x-text="row.default_supplier?.phone ? ('📱 ' + row.default_supplier.phone) : (row.default_supplier?.contact_person ? ('👤 ' + row.default_supplier.contact_person) : '')"></div>
                                    </div>
                                </template>
                                <template x-if="!row.default_supplier && !row.default_supplier_id">
                                    <span class="text-muted small">— Belum Ada —</span>
                                </template>
                            </td>
                            <td>
                                <span :class="row.is_critical ? 'text-danger fw-bold fs-6' : 'fw-bold'"
                                      x-text="formatQty(row.current_stock) + ' ' + (row.unit?.symbol || '')"></span>
                            </td>
                            <td><span class="text-muted small" x-text="formatQty(row.min_stock) + ' ' + (row.unit?.symbol || '')"></span></td>
                            <td>
                                <div class="fw-bold" style="color:var(--txt-primary)" x-text="'Rp ' + formatNumber(getItemPurchasePrice(row))"></div>
                                <div class="text-muted small" style="font-size:10px" 
                                     x-show="(row.net_content || 1) > 1" 
                                     x-text="'(Rp ' + formatQty(row.average_cost) + '/' + (row.unit?.symbol || 'unit') + ')'"></div>
                            </td>
                            <td class="fw-bold text-primary" x-text="'Rp ' + formatQty(row.stock_value || 0)"></td>
                            <td>
                                <span :class="['badge', row.status === 'active' ? 'badge-success' : 'badge-danger']"
                                      style="font-size:11px; padding:4px 8px; text-transform:uppercase"
                                      x-text="row.status"></span>
                            </td>
                            <td class="pe-3 text-end">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <button class="btn btn-secondary btn-sm px-2 py-1" style="border-radius:6px; font-size:12px" @click="openForm(row)" title="Edit Item">✏️</button>
                                    <button class="btn btn-warning btn-sm text-white fw-bold px-2 py-1" style="background:#ea580c; border:none; border-radius:6px; font-size:12px" @click="openStockIn(row)">🛒 Restock</button>
                                    <button class="btn btn-danger btn-sm px-2 py-1" style="border-radius:6px; font-size:12px" @click="delRow(row)" title="Hapus Item">🗑</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Item -->
    <div x-show="showModal" class="modal-overlay" @click.self="showModal=false" x-cloak :style="showModal ? 'display:flex' : 'display:none'">
        <div class="modal-box" style="max-width:550px">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" x-text="editId ? '✏️ Edit Item Inventory' : '📦 Tambah Item Inventory'"></h5>
                <button class="btn btn-secondary btn-sm" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Bahan Baku / Item *</label>
                    <input x-model="form.name" class="form-input" placeholder="mis. Fresh Milk / Espresso Bean" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Mitra Supplier Utama (Master Supplier)</label>
                    <select x-model="form.default_supplier_id" class="form-input">
                        <option value="">-- Pilih Master Supplier (Opsional) --</option>
                        <template x-for="s in suppliers" :key="s.id">
                            <option :value="s.id" x-text="s.name + (s.contact_person ? ' (PIC: ' + s.contact_person + ')' : '')"></option>
                        </template>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Kategori *</label>
                        <select x-model="form.category_id" class="form-input">
                            <option value="">Pilih Kategori</option>
                            <template x-for="c in categories" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Satuan Beli *</label>
                        <select x-model="form.unit_id" class="form-input">
                            <option value="">Pilih Satuan</option>
                            <template x-for="u in units" :key="u.id">
                                <option :value="u.id" x-text="u.name + ' (' + u.symbol + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Harga Pembelian (Rp) *</label>
                        <input x-model.number="form.purchase_price" type="number" class="form-input" placeholder="24000" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Qty / Isi per Satuan Beli *</label>
                        <input x-model.number="form.net_content" type="number" step="any" min="0.001" class="form-input" placeholder="1000" />
                        <span class="text-muted d-block mt-1" style="font-size:11px">mis. 1000 untuk 1000 ml/g per botol</span>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Min Stok Peringatan *</label>
                        <input x-model.number="form.min_stock" type="number" class="form-input" placeholder="5" />
                    </div>
                    <div class="col-6" x-show="!editId">
                        <label class="form-label">Stok Awal (Kemasan / Unit Beli)</label>
                        <input x-model.number="form.initial_stock" type="number" step="any" min="0" class="form-input" placeholder="1" />
                        <span class="text-muted d-block mt-1" style="font-size:11px">Isi 1 untuk 1 kemasan, atau 0 jika belum ada fisik</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showModal=false">Batal</button>
                <button class="btn btn-primary" :disabled="saving" @click="saveForm()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Item</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Restock -->
    <div x-show="showStockIn" class="modal-overlay" @click.self="showStockIn=false" style="display:flex">
        <div class="modal-box" style="max-width:450px">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">🛒 Restock Bahan Baku</h5>
                <button class="btn btn-secondary btn-sm" @click="showStockIn=false">✕</button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Item</label>
                    <input class="form-input" :value="stkItem?.name" disabled style="background:var(--bg-muted)" />
                </div>
                <div class="mb-3">
                    <label class="form-label">Supplier Pembelian</label>
                    <select x-model="stkForm.supplier_id" class="form-input">
                        <option value="">-- Pilih Supplier --</option>
                        <template x-for="s in suppliers" :key="s.id">
                            <option :value="s.id" x-text="s.name"></option>
                        </template>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Jumlah Kemasan (Qty) *</label>
                        <input x-model.number="stkForm.qty" type="number" step="any" min="0.01" class="form-input" placeholder="1" />
                        <span class="text-muted d-block mt-1" style="font-size:11px" x-show="stkItem?.net_content > 1" x-text="'1 kemasan = ' + formatQty(stkItem?.net_content) + ' ' + (stkItem?.unit?.symbol || '')"></span>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Total Biaya Pembelian (Rp) *</label>
                        <input x-model.number="stkForm.total_cost" type="number" class="form-input" placeholder="23000" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showStockIn=false">Batal</button>
                <button class="btn btn-success" :disabled="saving" @click="saveStockIn()">
                    <span x-show="saving">⏳ Memproses...</span>
                    <span x-show="!saving">Catat Restock</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Kelola Kategori -->
    <div x-show="showCatModal" class="modal-overlay" @click.self="showCatModal=false" x-cloak :style="showCatModal ? 'display:flex' : 'display:none'">
        <div class="modal-box" style="max-width:500px">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">🏷️ Kelola Kategori Inventory</h5>
                <button class="btn btn-secondary btn-sm" @click="showCatModal=false">✕</button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3">
                    <input x-model="newCatName" class="form-input flex-fill" placeholder="Nama Kategori Baru (mis. Kopi, Sirup, Packaging)" @keyup.enter="saveCat()" />
                    <button class="btn btn-primary btn-sm" :disabled="savingCat" @click="saveCat()">
                        <span x-show="!savingCat">+ Tambah</span>
                        <span x-show="savingCat" x-cloak>Menyimpan...</span>
                    </button>
                </div>
                <div class="table-responsive" style="max-height:280px">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama Kategori</th>
                                <th>Item Terkait</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="c in categories" :key="c.id">
                                <tr>
                                    <td>
                                        <template x-if="editCatId !== c.id">
                                            <span class="fw-bold text-primary" x-text="c.name"></span>
                                        </template>
                                        <template x-if="editCatId === c.id">
                                            <input x-model="editCatName" class="form-input form-input-sm w-100" @keyup.enter="updateCat(c)" @keyup.escape="cancelEditCat()" />
                                        </template>
                                    </td>
                                    <td>
                                        <span class="badge badge-info" x-text="(c.ingredients_count || 0) + ' item'"></span>
                                    </td>
                                    <td class="text-end">
                                        <template x-if="editCatId !== c.id">
                                            <div class="d-flex gap-1 justify-content-end">
                                                <button class="btn btn-secondary btn-sm py-1 px-2" @click="startEditCat(c)" title="Edit Kategori">✏️ Edit</button>
                                                <button class="btn btn-danger btn-sm py-1 px-2" @click="deleteCat(c)" title="Hapus Kategori">🗑</button>
                                            </div>
                                        </template>
                                        <template x-if="editCatId === c.id">
                                            <div class="d-flex gap-1 justify-content-end">
                                                <button class="btn btn-success btn-sm py-1 px-2" @click="updateCat(c)" title="Simpan Perubahan">✓ Simpan</button>
                                                <button class="btn btn-secondary btn-sm py-1 px-2" @click="cancelEditCat()" title="Batal">✕ Batal</button>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!categories || categories.length === 0">
                                <td colspan="3" class="text-center text-muted py-3">Belum ada kategori inventory.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showCatModal=false">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function inventoryApp() {
    return {
        loading: false, saving: false,
        items: [], categories: [], units: [], suppliers: [],
        kpis: {},
        search: '', filterCategory: '', filterSupplier: '', filterStatus: '', filterCritical: false,
        showModal: false, editId: null,
        form: { name: '', category_id: '', unit_id: '', default_supplier_id: '', net_content: 1000, purchase_price: 0, min_stock: 5, initial_stock: 0 },
        showStockIn: false, stkItem: null,
        stkForm: { qty: 1, total_cost: 0, supplier_id: '' },
        showCatModal: false, newCatName: '', savingCat: false, editCatId: null, editCatName: '',
        showUnitModal: false, newUnitName: '', newUnitSymbol: '', newUnitType: 'volume', savingUnit: false, editUnitId: null, editUnitName: '', editUnitSymbol: '', editUnitType: 'volume',

        async init() {
            await Promise.all([this.load(), this.loadMeta()]);
        },

        async load() {
            this.loading = true;
            try {
                // safer: gunakan k jika tersedia, atau fallback ke objek kosong
                this.kpis = (typeof k !== 'undefined') ? (k.data ?? k) : {};
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        async loadMeta() {
            try {
                let cRes;
                try {
                    cRes = await axios.get('/api/v1/inventory-categories');
                } catch(err1) {
                    cRes = await axios.get('/api/v1/inventory/categories');
                }
                let catData = cRes.data?.categories ?? cRes.data?.data ?? (Array.isArray(cRes.data) ? cRes.data : []);
                if (catData && typeof catData === 'object' && !Array.isArray(catData)) {
                    catData = Object.values(catData);
                }
                this.categories = Array.isArray(catData) ? catData : [];

                let uRes;
                try {
                    uRes = await axios.get('/api/v1/units');
                } catch(err2) {
                    uRes = await axios.get('/api/v1/inventory/units');
                }
                let unitData = uRes.data?.units ?? uRes.data?.data ?? (Array.isArray(uRes.data) ? uRes.data : []);
                if (unitData && typeof unitData === 'object' && !Array.isArray(unitData)) {
                    unitData = Object.values(unitData);
                }
                this.units = Array.isArray(unitData) ? unitData : [];

                try {
                    const sRes = await axios.get('/api/v1/suppliers', { params: { per_page: 200 } });
                    let supData = sRes.data?.data ?? (Array.isArray(sRes.data) ? sRes.data : []);
                    this.suppliers = Array.isArray(supData) ? supData : [];
                } catch(err3) {
                    console.error('Gagal memuat master supplier', err3);
                }
            } catch(e) { console.error('Gagal memuat metadata', e); }
        },

        getSupplierName(id) {
            if (!id) return '—';
            const found = this.suppliers.find(s => s.id == id);
            return found ? found.name : '—';
        },

        openForm(row = null) {
            if (row) {
                this.editId = row.id;
                this.form = {
                    name: row.name || '',
                    category_id: row.category_id || '',
                    unit_id: row.unit_id || '',
                    default_supplier_id: row.default_supplier_id || row.default_supplier?.id || '',
                    net_content: row.net_content != null ? parseFloat(row.net_content) : 1,
                    purchase_price: this.getItemPurchasePrice(row),
                    min_stock: row.min_stock != null ? parseFloat(row.min_stock) : 0,
                    initial_stock: 0
                };
            } else {
                this.editId = null;
                this.form = {
                    name: '',
                    category_id: this.categories.length > 0 ? this.categories[0].id : '',
                    unit_id: this.units.length > 0 ? this.units[0].id : '',
                    default_supplier_id: '',
                    net_content: 1000,
                    purchase_price: 0,
                    min_stock: 5,
                    initial_stock: 1
                };
            }
            this.showModal = true;
        },

        async saveForm() {
            if (!this.form.name || !this.form.name.trim()) return toast('Nama item wajib diisi!', 'warning');
            if (!this.form.category_id) return toast('Kategori wajib dipilih!', 'warning');
            if (!this.form.unit_id) return toast('Satuan ukur wajib dipilih!', 'warning');
            if (!this.form.net_content || this.form.net_content <= 0) return toast('Isi per satuan beli (net content) wajib diisi minimal 0.001!', 'warning');
            this.saving = true;
            let success = false;
            try {
                if (this.editId) {
                    await axios.put(`/api/v1/inventory/ingredients/${this.editId}`, this.form);
                    toast('Item berhasil diperbarui!');
                } else {
                    await axios.post('/api/v1/inventory/ingredients', this.form);
                    toast('Item baru berhasil ditambahkan!');
                }
                this.showModal = false;
                success = true;
            } catch(e) {
                let msg = 'Gagal menyimpan data';
                if (e.response?.data?.errors) {
                    const errs = Object.values(e.response.data.errors).flat();
                    if (errs.length > 0) msg = errs.join(', ');
                } else if (e.response?.data?.message) {
                    msg = e.response.data.message;
                }
                toast(msg, 'error');
            } finally {
                this.saving = false;
                if (success) {
                    try { await this.load(); } catch(err) {}
                }
            }
        },

        getItemPurchasePrice(row) {
            // pastikan ambil dari properti row yang benar (mis: purchase_price atau price)
            const price = row?.purchase_price ?? row?.price ?? 0;
            return Math.round(price * 100) / 100;
        },

        openStockIn(row) {
            this.stkItem = row;
            const defaultCost = this.getItemPurchasePrice(row);

            this.stkForm = {
                qty: 1,
                total_cost: defaultCost,
                supplier_id: row.default_supplier_id || row.default_supplier?.id || '',
                warehouse_id: row.stocks?.[0]?.warehouse_id || ''
            };
            this.showStockIn = true;
        },

        async saveStockIn() {
            if (!this.stkForm.qty || this.stkForm.qty <= 0) return toast('Jumlah pembelian (Qty) wajib diisi minimal 1!', 'warning');
            this.saving = true;
            try {
                const net = parseFloat(this.stkItem.net_content || 1);
                const packQty = parseFloat(this.stkForm.qty || 1);
                const baseQty = (net > 1 && packQty < net) ? (packQty * net) : packQty;
                const totalCost = parseFloat(this.stkForm.total_cost || 0);
                const unitCost = (baseQty > 0 && totalCost > 0) ? (totalCost / baseQty) : (this.stkItem.average_cost || 0);

                const payload = {
                    ingredient_id: this.stkItem.id,
                    warehouse_id: this.stkForm.warehouse_id || (this.warehouses.length > 0 ? this.warehouses[0].id : 1),
                    qty: baseQty,
                    unit_cost: unitCost,
                    is_base_qty: true,
                    supplier_id: this.stkForm.supplier_id || null,
                    notes: 'Restock / Kulakan Ulang (' + packQty + ' kemasan @ Rp ' + formatNumber(totalCost) + ')'
                };

                await axios.post('/api/v1/inventory/stock-in', payload);
                toast('Restock ' + this.stkItem.name + ' berhasil dicatat!');
                this.showStockIn = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal mencatat restock', 'error');
            } finally { this.saving = false; }
        },

        delRow(row) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/inventory/ingredients/${row.id}`);
                    toast('Item berhasil dihapus!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus item', 'error');
                }
            }, row.name);
        },

        async openCatModal() {
            this.newCatName = '';
            this.editCatId = null;
            this.editCatName = '';
            await this.loadMeta();
            this.showCatModal = true;
        },

        async saveCat() {
            if (!this.newCatName.trim()) return toast('Nama kategori wajib diisi!', 'warning');
            this.savingCat = true;
            try {
                try {
                    await axios.post('/api/v1/inventory-categories', { name: this.newCatName });
                } catch(e1) {
                    await axios.post('/api/v1/inventory/categories', { name: this.newCatName });
                }
                toast('Kategori inventory berhasil ditambahkan!');
                this.newCatName = '';
                await this.loadMeta();
            } catch(e) {
                const msg = e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Gagal menambahkan kategori';
                toast(msg, 'error');
            } finally { this.savingCat = false; }
        },

        startEditCat(c) {
            this.editCatId = c.id;
            this.editCatName = c.name;
        },

        cancelEditCat() {
            this.editCatId = null;
            this.editCatName = '';
        },

        async updateCat(c) {
            if (!this.editCatName.trim()) return toast('Nama kategori tidak boleh kosong!', 'warning');
            try {
                try {
                    await axios.put(`/api/v1/inventory-categories/${c.id}`, { name: this.editCatName });
                } catch(e1) {
                    await axios.put(`/api/v1/inventory/categories/${c.id}`, { name: this.editCatName });
                }
                toast('Kategori inventory berhasil diperbarui!');
                this.editCatId = null;
                this.editCatName = '';
                await this.loadMeta();
            } catch(e) {
                const msg = e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Gagal memperbarui kategori';
                toast(msg, 'error');
            }
        },

        deleteCat(c) {
            confirmDelete(async () => {
                try {
                    try {
                        await axios.delete(`/api/v1/inventory-categories/${c.id}`);
                    } catch(e1) {
                        await axios.delete(`/api/v1/inventory/categories/${c.id}`);
                    }
                    toast('Kategori berhasil dihapus!');
                    await this.loadMeta();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus kategori', 'error');
                }
            }, c.name);
        },

        formatQty(val) {
            // gunakan 'val', bukan 'num'
            return (val ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });
        },

        formatNumber(val) {
            if (val === null || val === undefined || val === '') return '0';
            let num = Number(val);
            if (isNaN(num)) return '0';
            if (Math.abs(num - Math.round(num)) < 0.0001) {
                return Math.round(num).toLocaleString('id-ID');
            }
            return num.toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }
    }
}
</script>
@endpush
