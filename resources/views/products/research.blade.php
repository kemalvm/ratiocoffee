@extends('layouts.erp')
@section('title', 'Research Product R&D – Ratio Coffee ERP')

@section('page-content')
<div x-data="productResearchApp()" x-init="init()" x-cloak class="d-flex flex-column gap-4">

    <!-- Page Title & Header Banner -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2" style="font-family:'Plus Jakarta Sans', sans-serif">
                🔬 Research &amp; Development (R&amp;D) Product Lab
            </h4>
            <p class="text-muted small m-0 mt-1">Laboratorium eksperimen formulasi menu, pengujian rasa &amp; potongan stok, serta rilis otomatis ke POS Kasir.</p>
        </div>
        <div>
            <button class="btn btn-sm fw-bold text-white d-inline-flex align-items-center gap-2 px-3.5 shadow-sm" style="height:38px; border-radius:10px; background:linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark)); border:none" @click="openForm()">
                <span>🔬</span> Usulkan Project R&amp;D Baru
            </button>
        </div>
    </div>

    <!-- Quick Metric Cards Overview -->
    <div class="row g-3">
        <div class="col-md-3">
            <div class="kpi-card kpi-card-revenue">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper revenue-theme">🔬</span>
                    <span class="kpi-badge revenue-badge">Formulasi</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Project R&amp;D</div>
                    <div class="kpi-val" x-text="projects.length"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-growth">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper growth-theme">🧪</span>
                    <span class="kpi-badge asset-badge">Uji Rasa</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Tahap Uji Coba</div>
                    <div class="kpi-val text-primary" x-text="projects.filter(p => p.status === 'testing').length"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-profit">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper profit-theme">✅</span>
                    <span class="kpi-badge profit-badge">Approved</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Lolos R&amp;D (Siap POS)</div>
                    <div class="kpi-val text-success" x-text="projects.filter(p => p.status === 'approved').length"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-expense">
                <div class="kpi-card-glow"></div>
                <div class="kpi-header">
                    <span class="kpi-icon-wrapper expense-theme">💰</span>
                    <span class="kpi-badge expense-badge">Modal / Saji</span>
                </div>
                <div class="kpi-body">
                    <div class="kpi-label">Rata-Rata HPP Riset</div>
                    <div class="kpi-val text-warning" x-text="'Rp ' + formatNumber(calcAvgCost())"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar Filters & Search Bar -->
    <div class="card p-3 shadow-sm" style="border-radius:14px; background:var(--bg-surface); border:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Segmented Filter Pills -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterStatus === '' ? 'btn-primary' : 'btn-outline-secondary'" style="border-radius:8px; height:34px" @click="filterStatus = ''">
                    Semua Status
                </button>
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterStatus === 'draft' ? 'btn-warning' : 'btn-outline-secondary'" style="border-radius:8px; height:34px" @click="filterStatus = 'draft'">
                    🟡 Draft (Formulasi)
                </button>
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterStatus === 'testing' ? 'btn-primary' : 'btn-outline-secondary'" style="border-radius:8px; height:34px" @click="filterStatus = 'testing'">
                    🔵 Testing (Uji Coba)
                </button>
                <button class="btn btn-sm fw-semibold px-3 py-1.5" :class="filterStatus === 'approved' ? 'btn-success' : 'btn-outline-secondary'" style="border-radius:8px; height:34px" @click="filterStatus = 'approved'">
                    🟢 Approved (Lolos)
                </button>
            </div>

            <!-- Live Search Box -->
            <div class="position-relative" style="width:260px">
                <input x-model="search" class="form-control form-control-sm ps-4" style="height:34px; border-radius:10px; background:var(--bg-muted); border:1px solid var(--border)" placeholder="Cari nama riset menu..." />
                <span class="position-absolute top-50 start-0 translate-middle-y ms-2.5 text-muted" style="font-size:12px">🔍</span>
            </div>
        </div>
    </div>

    <!-- R&D Projects Grid -->
    <div class="row g-3">
        <template x-if="loading">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
                <div class="text-muted fw-medium">Memuat data riset produk R&amp;D...</div>
            </div>
        </template>

        <template x-if="!loading && filteredProjects().length === 0">
            <div class="col-12">
                <div class="card p-5 text-center shadow-sm" style="border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
                    <div style="font-size:54px" class="mb-3">🧪</div>
                    <h5 class="fw-bold text-dark mb-2">Tidak Ada Project R&amp;D Ditemukan</h5>
                    <p class="text-muted small m-0 max-w-md mx-auto">Silakan tambahkan formulasi riset menu baru untuk memulai eksperimen resep &amp; pengujian rasa.</p>
                </div>
            </div>
        </template>

        <template x-for="p in filteredProjects()" :key="p.id">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 shadow-sm" style="border-radius:16px; background:var(--bg-surface); border:1px solid var(--border); box-shadow:0 4px 16px rgba(0,0,0,0.03); transition:all 0.2s ease" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 16px rgba(0,0,0,0.03)'">
                    <div class="d-flex flex-column h-100 justify-content-between">
                        <div>
                            <!-- Header Badges & Actions -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="kpi-badge revenue-badge" x-text="p.category || 'R&D Coffee'"></span>
                                    <span :class="getStatusBadgeClass(p.status)" x-text="getStatusLabel(p.status)"></span>
                                </div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <button class="btn btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px; height:32px; border-radius:8px; background:rgba(234, 88, 12, 0.08); color:var(--clr-primary); border:1px solid rgba(234, 88, 12, 0.2); transition:all 0.2s" onmouseover="this.style.background='var(--clr-primary)'; this.style.color='#ffffff'" onmouseout="this.style.background='rgba(234, 88, 12, 0.08)'; this.style.color='var(--clr-primary)'" @click="openForm(p)" title="Edit Resep & Riset">
                                        ✏️
                                    </button>
                                    <button class="btn btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px; height:32px; border-radius:8px; background:rgba(239, 68, 68, 0.08); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.2); transition:all 0.2s" onmouseover="this.style.background='#ef4444'; this.style.color='#ffffff'" onmouseout="this.style.background='rgba(239, 68, 68, 0.08)'; this.style.color='#ef4444'" @click="delProject(p)" title="Hapus Project">
                                        🗑️
                                    </button>
                                </div>
                            </div>

                            <!-- Title & Description -->
                            <h5 class="fw-bold text-dark mb-1" style="font-size:16px" x-text="p.name"></h5>
                            <p class="text-muted small mb-3" style="font-size:12.5px; line-height:1.4; min-height:34px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden" x-text="p.description || 'Formulasi resep baru untuk varian menu kopi/minuman.'"></p>

                            <!-- Financial Metrics Grid -->
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <div class="p-2 rounded text-center" style="background:var(--bg-muted); border-radius:10px">
                                        <div class="text-muted fw-bold mb-1" style="font-size:9.5px; letter-spacing:0.5px">ESTIMASI HPP</div>
                                        <div class="fw-bold text-danger" style="font-size:12.5px" x-text="'Rp ' + formatNumber(p.estimated_cost || p.target_cogs || 0)"></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded text-center" style="background:var(--bg-muted); border-radius:10px">
                                        <div class="text-muted fw-bold mb-1" style="font-size:9.5px; letter-spacing:0.5px">TARGET JUAL</div>
                                        <div class="fw-bold text-primary" style="font-size:12.5px" x-text="'Rp ' + formatNumber(p.target_price || 0)"></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded text-center" style="background:var(--bg-muted); border-radius:10px">
                                        <div class="text-muted fw-bold mb-1" style="font-size:9.5px; letter-spacing:0.5px">MARGIN LABA</div>
                                        <div class="fw-bold text-success" style="font-size:12.5px" x-text="calcMarginPct(p)"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ingredients Formulasi Pills -->
                            <div class="mb-2">
                                <div class="text-muted fw-semibold mb-2" style="font-size:12px">🧪 Komposisi Bahan Baku:</div>
                                <template x-if="p.ingredients && p.ingredients.length > 0">
                                    <div class="d-flex flex-wrap gap-1.5">
                                        <template x-for="ing in p.ingredients" :key="ing.id">
                                            <span class="badge bg-light text-dark d-inline-flex align-items-center gap-1" style="font-size:11.5px; padding:5px 11px; border-radius:12px; background:var(--bg-muted) !important">
                                                <span class="text-secondary" x-text="ing.ingredient_name"></span>:
                                                <strong class="text-primary fw-bold" x-text="formatQty(ing.quantity) + ' ' + (ing.unit_symbol || '')"></strong>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!p.ingredients || p.ingredients.length === 0">
                                    <span class="text-muted small italic" style="font-size:12px">Belum ada komposisi bahan baku ditentukan.</span>
                                </template>
                            </div>
                        </div>

                        <!-- Footer Action Buttons (Floating without harsh border line) -->
                        <div class="mt-3">
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm flex-fill fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5" style="height:38px; border-radius:10px; font-size:12.5px; background:rgba(245, 158, 11, 0.1); color:#d97706; border:1px solid rgba(245, 158, 11, 0.3); transition:all 0.2s" onmouseover="this.style.background='#f59e0b'; this.style.color='#ffffff'" onmouseout="this.style.background='rgba(245, 158, 11, 0.1)'; this.style.color='#d97706'" @click="openTestModal(p)">
                                    <span>🧪</span> Uji Coba Stok
                                </button>
                                <button class="btn btn-sm flex-fill fw-bold text-white d-inline-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="height:38px; border-radius:10px; font-size:12.5px; background:linear-gradient(135deg, #10b981, #059669); border:none; transition:transform 0.2s" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'" @click="convertToProduct(p)">
                                    <span>🚀</span> Rilis POS Menu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Modal Form Project R&D + Recipe Builder -->
    <div x-show="showModal" class="modal-overlay" @click.self="showModal=false" style="display:flex">
        <div class="modal-box" style="max-width:680px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark" x-text="editId ? '✏️ Edit Project & Resep R&D' : '🔬 Usulkan Project R&D Menu Baru'"></h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body py-3" style="max-height:72vh; overflow-y:auto">
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Nama Riset Menu *</label>
                        <input x-model="form.name" class="form-input" placeholder="mis. Salted Caramel Oat Cold Brew" />
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Kategori Menu *</label>
                        <select x-model="form.category" class="form-input">
                            <option value="">-- Pilih Kategori --</option>
                            <template x-for="c in categories" :key="c.id">
                                <option :value="c.name" x-text="c.name" :selected="form.category === c.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Target Harga Jual (Rp) *</label>
                        <input x-model.number="form.target_price" type="number" class="form-input" placeholder="28000" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status Project</label>
                        <select x-model="form.status" class="form-input">
                            <option value="draft">🟡 Draft (Formulasi)</option>
                            <option value="testing">🔵 Testing (Uji Coba)</option>
                            <option value="approved">🟢 Approved (Disetujui)</option>
                            <option value="rejected">🔴 Rejected (Ditolak)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Deskripsi &amp; Catatan Formulasi Rasa</label>
                    <textarea x-model="form.description" class="form-input" rows="2" placeholder="Catatan profil rasa, rasio espresso, petunjuk penyajian..."></textarea>
                </div>

                <!-- Live Recipe Ingredient Builder -->
                <div class="p-3 rounded mb-3" style="background:var(--bg-muted); border:1px solid var(--border); border-radius:12px">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <h6 class="fw-bold m-0 text-dark" style="font-size:13px">🧪 Formulasi Bahan Baku (Resep R&amp;D)</h6>
                        <button type="button" class="btn btn-add-ingredient" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 12px !important; padding: 6px 14px !important;" @click="addRecipeRow()">
                            <span>+</span> Tambah Bahan
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table mb-2">
                            <thead>
                                <tr>
                                    <th style="width:40%">Bahan Baku</th>
                                    <th style="width:25%">Qty</th>
                                    <th style="width:20%">Cost/Unit</th>
                                    <th class="text-end" style="width:15%">Subtotal</th>
                                    <th></th> 
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(ingRow, idx) in form.ingredients" :key="idx">
                                    <tr>
                                        <td>
                                            <select x-model="ingRow.ingredient_id" @change="onIngredientSelect(ingRow)" class="form-input" style="font-size:12px; padding:4px 8px">
                                                <option value="">-- Pilih Bahan Baku --</option>
                                                <template x-for="item in availableIngredients" :key="item.id">
                                                    <option :value="item.id" x-text="item.name + ' (' + (item.unit?.symbol || item.unit || 'unit') + ')'"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <input x-model.number="ingRow.quantity" @input="calcSubtotal(ingRow)" type="number" step="any" class="form-input" style="font-size:12px; padding:4px 8px" placeholder="0" />
                                                <span class="small text-muted" x-text="ingRow.unit_symbol"></span>
                                            </div>
                                        </td>
                                        <td class="small text-muted" x-text="'Rp ' + formatNumber(ingRow.unit_cost || 0)"></td>
                                        <td class="text-end fw-bold text-danger" x-text="'Rp ' + formatNumber(ingRow.quantity * ingRow.unit_cost || 0)"></td>
                                        <td class="text-end">
                                            <button class="btn btn-danger btn-sm" style="padding:2px 6px" @click="removeRecipeRow(idx)">✕</button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="form.ingredients.length === 0">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted small py-3">Belum ada bahan baku ditambahkan ke resep ini.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Auto HPP Calculation Footer -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="fw-bold text-dark small">TOTAL ESTIMASI HPP RISÈT:</span>
                        <strong class="fs-5 text-danger" x-text="'Rp ' + formatNumber(calcTotalHpp())"></strong>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showModal=false">Batal</button>
                <button class="btn btn-primary fw-bold" style="border-radius:8px; background:linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark)); border:none" :disabled="saving" @click="saveProject()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Project R&amp;D</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Uji Coba & Potong Stok -->
    <div x-show="showTestModal" class="modal-overlay" @click.self="showTestModal=false" style="display:flex">
        <div class="modal-box" style="max-width:520px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark">🧪 Uji Coba Resep &amp; Potong Stok</h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showTestModal=false">✕</button>
            </div>
            <div class="modal-body py-3" x-if="activeTestProject">
                <div class="p-3 rounded mb-3" style="background:var(--bg-muted); border:1px solid var(--border); border-radius:10px">
                    <h6 class="fw-bold mb-1 text-dark" x-text="activeTestProject?.name"></h6>
                    <div class="text-muted small">Estimasi HPP per Saji: <strong class="text-danger" x-text="'Rp ' + formatNumber(activeTestProject?.estimated_cost || 0)"></strong></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Gudang Pemotongan Stok *</label>
                    <select x-model="testForm.warehouse_id" class="form-input">
                        <template x-for="w in warehouses" :key="w.id">
                            <option :value="w.id" x-text="w.name"></option>
                        </template>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Jumlah Uji Coba (Batch Test Run) *</label>
                    <input x-model.number="testForm.test_runs" type="number" min="1" class="form-input" placeholder="1" />
                    <small class="text-muted">Jumlah porsi saji yang dibuat untuk uji coba rasa &amp; tekstur.</small>
                </div>

                <div class="p-3 border rounded" style="background:var(--bg-surface); border-radius:10px; border-color:var(--border) !important">
                    <div class="text-muted small fw-bold mb-1.5">Rincian Stok Bahan yang Akan Dipotong:</div>
                    <template x-if="activeTestProject?.ingredients && activeTestProject.ingredients.length">
                        <ul class="mb-0 ps-3 small text-muted">
                            <template x-for="ing in activeTestProject.ingredients" :key="ing.id">
                                <li class="mb-1">
                                    <span x-text="ing.ingredient_name"></span>: 
                                    <strong class="text-dark" x-text="(ing.quantity * testForm.test_runs) + ' ' + (ing.unit_symbol || '')"></strong>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showTestModal=false">Batal</button>
                <button class="btn btn-warning text-white fw-bold shadow-sm" :disabled="testing" @click="executeStockDeduction()" style="background:#f59e0b; border:none; border-radius:8px">
                    <span x-show="testing">⏳ Memotong Stok...</span>
                    <span x-show="!testing">🧪 Potong Stok &amp; Mulai Test</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function productResearchApp() {
    return {
        loading: false, saving: false, testing: false,
        projects: [], availableIngredients: [], warehouses: [], categories: [],
        search: '', filterStatus: '',
        showModal: false, editId: null,
        form: { name: '', category: '', target_price: 0, status: 'draft', description: '', ingredients: [] },
        
        showTestModal: false, activeTestProject: null,
        testForm: { warehouse_id: 1, test_runs: 1 },

        async init() {
            const token = localStorage.getItem('erp_token');
            if (token) {
                axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
            }
            await Promise.all([this.load(), this.loadCats(), this.loadIngredients(), this.loadWarehouses()]);
        },

        async load() {
            this.loading = true;
            try {
                const r = await axios.get('/api/v1/research-products');
                this.projects = r.data.data ?? r.data ?? [];
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        async loadCats() {
            try {
                const r = await axios.get('/api/v1/product-categories');
                let list = r.data?.categories ?? r.data?.data ?? (Array.isArray(r.data) ? r.data : []);
                if (list && typeof list === 'object' && !Array.isArray(list)) {
                    list = Object.values(list);
                }
                this.categories = Array.isArray(list) ? list : [];
            } catch(e) {
                console.error('Gagal memuat kategori produk', e);
                this.categories = [];
            }
        },

        async loadIngredients() {
            try {
                const r = await axios.get('/api/v1/inventory/ingredients?per_page=200');
                this.availableIngredients = r.data.data ?? r.data ?? [];
            } catch(e) {}
        },

        async loadWarehouses() {
            try {
                const r = await axios.get('/api/v1/inventory/warehouses');
                this.warehouses = r.data ?? [];
                if (this.warehouses.length > 0) this.testForm.warehouse_id = this.warehouses[0].id;
            } catch(e) {}
        },

        filteredProjects() {
            return this.projects.filter(p => {
                const matchesSearch = !this.search || (p.name || '').toLowerCase().includes(this.search.toLowerCase()) || (p.description || '').toLowerCase().includes(this.search.toLowerCase());
                const matchesStatus = !this.filterStatus || p.status === this.filterStatus;
                return matchesSearch && matchesStatus;
            });
        },

        calcAvgCost() {
            if (!this.projects.length) return 0;
            const sum = this.projects.reduce((acc, p) => acc + (parseFloat(p.estimated_cost || p.target_cogs || 0)), 0);
            return Math.round(sum / this.projects.length);
        },

        getStatusBadgeClass(status) {
            switch(status) {
                case 'approved': return 'kpi-badge profit-badge';
                case 'testing': return 'kpi-badge asset-badge';
                case 'rejected': return 'kpi-badge expense-badge';
                default: return 'kpi-badge revenue-badge';
            }
        },

        getStatusLabel(status) {
            switch(status) {
                case 'approved': return '🟢 Approved';
                case 'testing': return '🔵 Testing';
                case 'rejected': return '🔴 Rejected';
                default: return '🟡 Draft';
            }
        },

        formatQty(val) {
            if (val === null || val === undefined || val === '') return '0';
            const num = parseFloat(val);
            if (isNaN(num)) return '0';
            return Number(num.toFixed(2)).toString();
        },

        calcMargin(p) {
            const price = parseFloat(p.target_price || 0);
            const cost = parseFloat(p.estimated_cost || p.target_cogs || 0);
            if (!price || price <= 0) return '0%';
            const profit = price - cost;
            const pct = Math.round((profit / price) * 100);
            return `Rp ${this.formatNumber(profit)} (${pct}%)`;
        },

        calcMarginPct(p) {
            const price = parseFloat(p.target_price || 0);
            const cost = parseFloat(p.estimated_cost || p.target_cogs || 0);
            if (!price || price <= 0) return '0%';
            const profit = price - cost;
            const pct = Math.round((profit / price) * 100);
            return `${pct}%`;
        },

        openForm(p = null) {
            if (p) {
                this.editId = p.id;
                this.form = {
                    name: p.name,
                    category: p.category || '',
                    target_price: p.target_price || 0,
                    status: p.status || 'draft',
                    description: p.description || '',
                    ingredients: (p.ingredients || []).map(i => ({
                        ingredient_id: i.ingredient_id,
                        quantity: i.quantity,
                        unit_symbol: i.unit_symbol || '',
                        unit_cost: i.unit_cost || 0
                    }))
                };
            } else {
                this.editId = null;
                this.form = {
                    name: '', category: this.categories[0]?.name || 'Coffee Series', target_price: 25000, status: 'draft', description: '',
                    ingredients: []
                };
                this.addRecipeRow();
            }
            this.showModal = true;
        },

        addRecipeRow() {
            this.form.ingredients.push({ ingredient_id: '', quantity: 1, unit_symbol: '', unit_cost: 0 });
        },

        removeRecipeRow(idx) {
            this.form.ingredients.splice(idx, 1);
        },

        onIngredientSelect(ingRow) {
            const found = this.availableIngredients.find(i => i.id == ingRow.ingredient_id);
            if (found) {
                ingRow.unit_symbol = found.unit?.symbol || found.unit || 'unit';
                ingRow.unit_cost = found.average_cost || found.purchase_price || 0;
            }
        },

        calcSubtotal(ingRow) {
            return (ingRow.quantity || 0) * (ingRow.unit_cost || 0);
        },

        calcTotalHpp() {
            return this.form.ingredients.reduce((sum, item) => sum + ((item.quantity || 0) * (item.unit_cost || 0)), 0);
        },

        async saveProject() {
            if (!this.form.name.trim()) return toast('Nama riset wajib diisi!', 'warning');
            this.saving = true;
            try {
                if (this.editId) {
                    await axios.put(`/api/v1/research-products/${this.editId}`, this.form);
                    toast('Project R&D berhasil diperbarui!');
                } else {
                    await axios.post('/api/v1/research-products', this.form);
                    toast('Project R&D baru berhasil diusulkan!');
                }
                this.showModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan project R&D', 'error');
            } finally { this.saving = false; }
        },

        openTestModal(p) {
            if (!p.ingredients || p.ingredients.length === 0) {
                return toast('Project riset ini belum memiliki daftar bahan baku! Tambahkan resep terlebih dahulu.', 'warning');
            }
            this.activeTestProject = p;
            this.testForm.test_runs = 1;
            this.showTestModal = true;
        },

        async executeStockDeduction() {
            if (!this.activeTestProject) return;
            this.testing = true;
            try {
                const r = await axios.post(`/api/v1/research-products/${this.activeTestProject.id}/deduct-stock`, this.testForm);
                toast(r.data.message || 'Stok berhasil dipotong untuk uji coba!');
                this.showTestModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memotong stok uji coba', 'error');
            } finally { this.testing = false; }
        },

        async convertToProduct(p) {
            confirmAction(async () => {
                try {
                    const r = await axios.post(`/api/v1/research-products/${p.id}/convert-to-product`);
                    toast(r.data.message || 'Berhasil mengonversi riset ke Menu Produk Live!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal merilis produk', 'error');
                }
            }, `Rilis "${p.name}" sebagai Produk Live POS?`);
        },

        delProject(p) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/research-products/${p.id}`);
                    toast('Project R&D berhasil dihapus!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus project', 'error');
                }
            }, p.name);
        },

        formatNumber
    }
}
</script>
@endpush
