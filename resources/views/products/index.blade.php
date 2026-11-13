@extends('layouts.erp')
@section('title', 'Produk & Resep – Ratio Coffee ERP')

@section('page-content')
<div x-data="productsApp()" x-init="init()" x-cloak class="container-fluid p-0">

    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0 text-primary d-flex align-items-center gap-2" style="font-size: 1.4rem;">
                <span>☕</span> Produk &amp; Resep (Katalog &amp; HPP)
            </h3>
            <p class="text-muted small m-0 mt-1">Kelola menu, resep bahan baku, estimasi HPP, dan ketersediaan POS Kasir</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm fw-bold px-3 py-2 shadow-xs" style="border-radius:10px; font-size:12.5px" @click="openCatModal()">
                🏷️ Kelola Kategori
            </button>
            <button class="btn btn-warning text-white btn-sm fw-bold px-3 py-2 shadow-xs" style="border-radius:10px; font-size:12.5px; background:#f59e0b; border:none" @click="openBundlingForm()">
                🎁 + Paket Bundling
            </button>
            <button class="btn btn-primary btn-sm fw-bold px-3 py-2 shadow-xs" style="border-radius:10px; font-size:12.5px; background:linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark)); border:none" @click="openForm()">
                🍵 + Menu Produk Baru
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards Bar -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 shadow-xs border-0" style="border-radius:14px; background:var(--bg-surface)">
                <span class="text-muted small d-block mb-1 fw-semibold">Total Menu Produk</span>
                <h4 class="fw-extrabold m-0 text-dark" x-text="products.length">0</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 shadow-xs border-0" style="border-radius:14px; background:var(--bg-surface)">
                <span class="text-muted small d-block mb-1 fw-semibold">Menu POS Live</span>
                <h4 class="fw-extrabold m-0 text-success" x-text="products.filter(p => p.available_for_pos).length">0</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 shadow-xs border-0" style="border-radius:14px; background:var(--bg-surface)">
                <span class="text-muted small d-block mb-1 fw-semibold">Paket Bundling</span>
                <h4 class="fw-extrabold m-0 text-purple" style="color: #7c3aed;" x-text="products.filter(p => p.is_bundling).length">0</h4>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card p-3 shadow-xs border-0" style="border-radius:14px; background:var(--bg-surface)">
                <span class="text-muted small d-block mb-1 fw-semibold">Rata-Rata Harga Jual</span>
                <h4 class="fw-extrabold m-0 text-primary" x-text="'Rp ' + formatNumber(calcAvgPrice())">Rp 0</h4>
            </div>
        </div>
    </div>

    <!-- Toolbar: Category Pills & Live Search -->
    <div class="card p-3 shadow-sm mb-4" style="border-radius:14px; background:var(--bg-surface); border:1px solid var(--border)">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Category Pills Navigation -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="btn btn-sm fw-semibold px-3.5" 
                        :style="filterCategory === '' ? 'background:var(--clr-primary); color:#ffffff; border:none; box-shadow:0 2px 6px rgba(234,88,12,0.25)' : 'background:var(--bg-muted); color:var(--txt-secondary, #475569); border:1px solid var(--border)'" 
                        style="border-radius:10px; height:34px; font-size:12.5px; transition:all 0.2s ease" 
                        @click="filterCategory = ''; load()">
                    Semua Menu
                </button>
                <template x-for="c in categories" :key="c.id">
                    <button class="btn btn-sm fw-semibold px-3.5" 
                            :style="filterCategory == c.id ? 'background:var(--clr-primary); color:#ffffff; border:none; box-shadow:0 2px 6px rgba(234,88,12,0.25)' : 'background:var(--bg-muted); color:var(--txt-secondary, #475569); border:1px solid var(--border)'" 
                            style="border-radius:10px; height:34px; font-size:12.5px; transition:all 0.2s ease" 
                            @click="filterCategory = c.id; load()" 
                            x-text="c.name">
                    </button>
                </template>
            </div>

            <!-- Live Search Box -->
            <div class="position-relative flex-grow-1 flex-md-grow-0" style="min-width:200px">
                <input x-model="search" @input.debounce.300ms="load()" class="form-control form-control-sm ps-4" style="height:34px; border-radius:10px; background:var(--bg-muted); border:1px solid var(--border)" placeholder="Cari nama menu / SKU..." />
                <span class="position-absolute top-50 start-0 translate-middle-y ms-2.5 text-muted" style="font-size:12px">🔍</span>
            </div>
        </div>
    </div>

    <!-- Products Grid (Modern Premium SaaS Cards) -->
    <div class="row g-3">
        <template x-if="loading">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width: 2.5rem; height: 2.5rem;" role="status"></div>
                <div class="text-muted fw-medium">Memuat katalog produk &amp; resep...</div>
            </div>
        </template>
        
        <template x-if="!loading && products.length === 0">
            <div class="col-12">
                <div class="card p-5 text-center shadow-sm" style="border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
                    <div style="font-size:54px" class="mb-3">🍵</div>
                    <h5 class="fw-bold text-dark mb-2">Belum Ada Produk Menu Terdaftar</h5>
                    <p class="text-muted small m-0 max-w-md mx-auto">Silakan tambahkan produk baru atau kelola kategori produk untuk mulai memasarkan menu pada POS Kasir.</p>
                </div>
            </div>
        </template>

        <template x-for="p in products" :key="p.id">
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 p-4 shadow-sm" style="border-radius:14px; background:var(--bg-surface); border:1px solid rgba(0,0,0,0.06); box-shadow:0 2px 10px rgba(0,0,0,0.02); transition:transform 0.2s ease, box-shadow 0.2s ease" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.06)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.02)'">
                    <div class="d-flex flex-column h-100 justify-content-between">
                        <div>
                            <!-- Header: Category Tag (Custom Category Color Pill Badge) -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span :style="getCatBadgeStyle(p.category)" 
                                      x-text="p.category?.name || 'Menu'"></span>
                            </div>

                            <!-- Product Name & Description -->
                            <h5 class="fw-bold text-dark mb-1" style="font-size:16px; font-family:'Plus Jakarta Sans', sans-serif; letter-spacing:-0.2px" x-text="p.name"></h5>
                            <p class="text-muted small mb-2.5" style="font-size:12px; line-height:1.4; min-height:34px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; opacity:0.85" x-text="p.description || 'Tidak ada deskripsi'"></p>

                            <!-- Bundling Item Pills (If Product is a Bundling Package) -->
                            <template x-if="p.is_bundling && p.bundle_data && p.bundle_data.length > 0">
                                <div class="p-2 rounded-3 mb-3" style="background: rgba(124, 58, 237, 0.05); border: 1px solid rgba(124, 58, 237, 0.18);">
                                    <div class="d-flex align-items-center gap-1 mb-1 fw-bold" style="font-size: 10.5px; color: #7c3aed;">
                                        <span>📦</span> <span>Item Bundling (<span x-text="p.bundle_data.length"></span> Menu):</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <template x-for="bItem in p.bundle_data" :key="bItem.product_id || bItem.product_name">
                                            <span class="badge px-2 py-0.5 fw-semibold" style="background: rgba(124, 58, 237, 0.1); color: #6d28d9; border: 1px solid rgba(124, 58, 237, 0.2); font-size: 10px;">
                                                <span x-text="(bItem.qty || 1) + 'x ' + bItem.product_name"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div>
                            <!-- Price Information & High-Contrast Clear HPP Label -->
                            <div class="pt-3 border-top mb-3" style="border-color:var(--border) !important">
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <div>
                                        <span class="d-block mb-0.5 fw-bold" style="font-size:10px; color:#64748b; letter-spacing:0.5px; text-transform:uppercase">Harga Jual</span>
                                        <span class="fw-extrabold" style="font-size:17.5px; color:var(--clr-primary); font-family:'Plus Jakarta Sans', sans-serif" x-text="'Rp ' + formatNumber(p.selling_price)"></span>
                                    </div>
                                    <div class="text-end">
                                        <span class="d-block mb-0.5 fw-bold" style="font-size:10.5px; color:#475569; letter-spacing:0.5px; text-transform:uppercase">HPP Menu</span>
                                        <span class="fw-bold text-dark" style="font-size:13px;" x-text="'Rp ' + formatNumber(p.hpp || p.cogs || p.cost_price || 0)"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions: Soft Orange Gradient Edit Button & Soft Red Delete Button -->
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <button class="btn btn-sm flex-fill fw-extrabold d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" 
                                        style="height:35px; border-radius:9px; font-size:12px; background: linear-gradient(135deg, rgba(251, 146, 60, 0.12) 0%, rgba(234, 88, 12, 0.16) 100%); color: #c2410c; border: 1px solid rgba(234, 88, 12, 0.3); transition: all 0.2s ease" 
                                        onmouseover="this.style.background='linear-gradient(135deg, rgba(251, 146, 60, 0.22) 0%, rgba(234, 88, 12, 0.26) 100%)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 3px 10px rgba(234,88,12,0.15)'" 
                                        onmouseout="this.style.background='linear-gradient(135deg, rgba(251, 146, 60, 0.12) 0%, rgba(234, 88, 12, 0.16) 100%)'; this.style.transform='none'; this.style.boxShadow='none'" 
                                        @click="p.is_bundling ? openBundlingForm(p) : openForm(p)">
                                    <span>✏️</span> Edit Resep &amp; Menu
                                </button>
                                <button class="btn btn-sm px-0 d-inline-flex align-items-center justify-content-center shadow-xs" 
                                        style="width:34px; height:34px; border-radius:9px; background:rgba(239, 68, 68, 0.08); color:#dc2626; border:1px solid rgba(239, 68, 68, 0.25); transition:all 0.2s ease" 
                                        onmouseover="this.style.background='#ef4444'; this.style.color='#ffffff'; this.style.borderColor='#ef4444'; this.style.transform='translateY(-1px)'" 
                                        onmouseout="this.style.background='rgba(239, 68, 68, 0.08)'; this.style.color='#dc2626'; this.style.borderColor='rgba(239, 68, 68, 0.25)'; this.style.transform='none'" 
                                        @click="delProduct(p)" 
                                        title="Hapus Produk">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Modal Form Product & Recipe Builder -->
    <div x-show="showModal" class="modal-overlay" @click.self="showModal=false" x-cloak :style="showModal ? 'display:flex' : 'display:none'">
        <div class="modal-box" style="max-width:680px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark" x-text="editId ? '✏️ Edit Menu Produk & Resep' : '🍵 Tambah Menu Produk Baru'"></h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body py-3" style="max-height:72vh; overflow-y:auto">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Menu *</label>
                    <input x-model="form.name" class="form-input" placeholder="mis. Cappuccino Hot / Ice Aren Latte" />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Kategori *</label>
                        <select x-model="form.category_id" class="form-input">
                            <option value="">Pilih Kategori</option>
                            <template x-for="c in categories" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Harga Jual (Rp) *</label>
                        <input x-model.number="form.selling_price" type="number" class="form-input" placeholder="28000" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Deskripsi Menu</label>
                    <textarea x-model="form.description" class="form-input" rows="2" placeholder="Catatan/deskripsi singkat menu..."></textarea>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" x-model="form.available_for_pos" class="form-check-input" id="checkPos">
                    <label class="form-check-label fw-bold text-dark" for="checkPos">Tampilkan di POS Kasir</label>
                </div>

                <!-- Live Recipe Ingredient Builder & HPP Calculator -->
                <div class="p-3 rounded mb-3" style="background:var(--bg-muted); border: 1px solid var(--border); border-radius:12px">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <h6 class="fw-bold m-0 text-dark" style="font-size:13px">🧪 Resep Bahan Baku &amp; Hitung Estimasi HPP</h6>
                        <button type="button" class="btn btn-add-ingredient" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important; color: #ffffff !important; border: none !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 12px !important; padding: 6px 14px !important;" @click="addRecipeRow()">
                            <span>+</span> Tambah Bahan
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table mb-2">
                            <thead>
                                <tr>
                                    <th style="width:40%">Bahan Baku (Inventory)</th>
                                    <th style="width:25%">Takaran / Qty</th>
                                    <th style="width:20%">Biaya Unit</th>
                                    <th class="text-end" style="width:15%">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(ingRow, idx) in recipeItems" :key="idx">
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
                                                <input x-model.number="ingRow.qty" type="number" step="any" class="form-input" style="font-size:12px; padding:4px 8px" placeholder="0" />
                                                <span class="small text-muted" x-text="ingRow.unit_symbol"></span>
                                            </div>
                                        </td>
                                        <td class="small text-muted" x-text="'Rp ' + formatNumber(ingRow.unit_cost || 0)"></td>
                                        <td class="text-end fw-bold text-danger" x-text="'Rp ' + formatNumber((ingRow.qty || 0) * (ingRow.unit_cost || 0))"></td>
                                        <td class="text-end">
                                            <button class="btn btn-danger btn-sm" style="padding:2px 6px" @click="removeRecipeRow(idx)">✕</button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="recipeItems.length === 0">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted small py-3">Belum ada takaran bahan baku terhubung ke resep produk ini.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Dynamic HPP Total -->
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="fw-bold text-dark small">ESTIMASI TOTAL HPP PRODUK:</span>
                        <strong class="fs-5 text-danger" x-text="'Rp ' + formatNumber(calcTotalHpp())"></strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showModal=false">Batal</button>
                <button class="btn btn-primary fw-bold" style="border-radius:8px; background:linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark)); border:none" :disabled="saving" @click="saveProduct()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Produk &amp; Resep</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Kelola Kategori Produk -->
    <div x-show="showCatModal" class="modal-overlay" @click.self="showCatModal=false" x-cloak :style="showCatModal ? 'display:flex' : 'display:none'">
        <div class="modal-box" style="max-width:580px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark">🏷️ Kelola Kategori Produk</h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showCatModal=false">✕</button>
            </div>
            <div class="modal-body py-3">
                <!-- Form Tambah Kategori -->
                <div class="p-3 mb-3 rounded-3" style="background:var(--bg-muted); border:1px solid var(--border)">
                    <label class="form-label text-muted small fw-semibold mb-1.5">Tambah Kategori Baru</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" x-model="newCatColor" class="form-control form-control-color p-0 border-0 rounded-3" style="width:38px; height:38px; cursor:pointer" title="Pilih Warna Kategori" />
                        <input x-model="newCatName" class="form-input flex-fill" placeholder="Nama Kategori (mis. Coffee Series)" @keyup.enter="saveCat()" />
                        <button class="btn btn-primary btn-sm fw-bold px-3" style="border-radius:8px; height:38px" :disabled="savingCat" @click="saveCat()">+ Tambah</button>
                    </div>
                    <!-- Preset Color Swatches -->
                    <div class="d-flex align-items-center gap-1.5 mt-2">
                        <span class="text-muted small me-1" style="font-size:11px">Preset Warna:</span>
                        <template x-for="swatch in ['#6366f1', '#64f2d5', '#f2e664', '#75f264', '#9d64f2', '#e864f2', '#f2b763', '#ef4444']" :key="swatch">
                            <button type="button" class="rounded-circle border-0 p-0" :style="'width:18px; height:18px; background:' + swatch + '; cursor:pointer; opacity:' + (newCatColor === swatch ? '1' : '0.55') + '; transform:' + (newCatColor === swatch ? 'scale(1.25)' : 'none')" @click="newCatColor = swatch"></button>
                        </template>
                    </div>
                </div>

                <!-- Table Kategori -->
                <div class="table-responsive" style="max-height:300px">
                    <table class="data-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px">Warna</th>
                                <th>Nama Kategori</th>
                                <th>Jumlah Produk</th>
                                <th class="text-end" style="width:130px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="categories.length === 0">
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">Belum ada kategori terdaftar.</td>
                                </tr>
                            </template>
                            <template x-for="c in categories" :key="c.id">
                                <tr>
                                    <td>
                                        <template x-if="editingCatId !== c.id">
                                            <span class="d-inline-block rounded-circle" :style="'width:18px; height:18px; background:' + (c.color || '#ea580c') + '; border:2px solid rgba(255,255,255,0.4); box-shadow:0 1px 3px rgba(0,0,0,0.2)'"></span>
                                        </template>
                                        <template x-if="editingCatId === c.id">
                                            <input type="color" x-model="editingCatColor" class="form-control form-control-color p-0 border-0 rounded-2" style="width:26px; height:26px; cursor:pointer" title="Ubah Warna Kategori" />
                                        </template>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        <template x-if="editingCatId !== c.id">
                                            <span :style="getCatBadgeStyle(c)" x-text="c.name"></span>
                                        </template>
                                        <template x-if="editingCatId === c.id">
                                            <div class="d-flex flex-column gap-1">
                                                <input x-model="editingCatName" class="form-input form-input-sm" style="padding:4px 8px; font-size:13px;" @keyup.enter="updateCat(c)" />
                                                <!-- Color Swatches for inline edit -->
                                                <div class="d-flex align-items-center gap-1 mt-1">
                                                    <template x-for="swatch in ['#6366f1', '#64f2d5', '#f2e664', '#75f264', '#9d64f2', '#e864f2', '#f2b763', '#ef4444']" :key="'edit-' + swatch">
                                                        <button type="button" class="rounded-circle border-0 p-0" :style="'width:14px; height:14px; background:' + swatch + '; cursor:pointer; opacity:' + (editingCatColor === swatch ? '1' : '0.5')" @click="editingCatColor = swatch"></button>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </td>
                                    <td><span class="kpi-badge revenue-badge" x-text="(c.products_count || 0) + ' produk'"></span></td>
                                    <td class="text-end">
                                        <template x-if="editingCatId !== c.id">
                                            <div class="d-flex gap-1 justify-content-end">
                                                <button class="btn btn-secondary btn-sm px-2 py-1" style="border-radius:6px" @click="startEditCat(c)" title="Edit Kategori & Warna">✏️ Edit</button>
                                                <button class="btn btn-danger btn-sm px-2 py-1" style="border-radius:6px" @click="deleteCat(c)" title="Hapus Kategori">🗑</button>
                                            </div>
                                        </template>
                                        <template x-if="editingCatId === c.id">
                                            <div class="d-flex gap-1 justify-content-end">
                                                <button class="btn btn-success btn-sm px-2 py-1 fw-bold text-white" style="border-radius:6px" @click="updateCat(c)" title="Simpan Perubahan">✓ Simpan</button>
                                                <button class="btn btn-secondary btn-sm px-2 py-1" style="border-radius:6px" @click="cancelEditCat()" title="Batal Edit">✕</button>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showCatModal=false">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Form Bundling -->
    <div x-show="showBundlingModal" class="modal-overlay" @click.self="showBundlingModal=false" x-cloak :style="showBundlingModal ? 'display:flex' : 'display:none'">
        <div class="modal-box" style="max-width:560px; border-radius:16px; background:var(--bg-surface); border:1px solid var(--border)">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark">🎁 Tambah / Edit Paket Bundling</h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="showBundlingModal=false">✕</button>
            </div>
            <div class="modal-body py-3" style="max-height:72vh; overflow-y:auto">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Paket Bundling *</label>
                    <input x-model="bundlingForm.name" class="form-input" placeholder="mis. Paket Ramadhan Karim / Paket 1 Coffee &amp; Dessert" />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Kategori *</label>
                        <select x-model="bundlingForm.category_id" class="form-input">
                            <option value="">Pilih Kategori</option>
                            <template x-for="c in categories" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Harga Jual Paket (Rp) *</label>
                        <input x-model.number="bundlingForm.selling_price" type="number" class="form-input" placeholder="45000" />
                    </div>
                </div>

                <!-- Multi-Product Bundling Item Selection -->
                <div class="mb-3">
                    <label class="form-label fw-semibold text-dark mb-1">Pilih Produk yang Masuk Paket Bundling *</label>
                    <p class="text-muted small mb-2" style="font-size: 11.5px;">Pilih menu dan atur jumlah masing-masing isi paket.</p>
                    <input x-model="bundleSearch" class="form-input mb-2" placeholder="Cari menu untuk bundling..." />
                    <div class="p-3 bg-light border rounded-3" style="max-height: 190px; overflow-y: auto;">
                        <template x-for="itemProd in filteredBundleProducts" :key="itemProd.id">
                            <div class="form-check d-flex align-items-center justify-content-between py-1.5 border-bottom border-light">
                                <div>
                                    <input type="checkbox" :id="'bundle_prod_' + itemProd.id" 
                                           class="form-check-input me-2" 
                                           :checked="isProductSelectedInBundle(itemProd.id)" 
                                           @change="toggleBundleProduct(itemProd)">
                                    <label :for="'bundle_prod_' + itemProd.id" class="form-check-label fw-semibold text-dark small cursor-pointer" x-text="itemProd.name"></label>
                                </div>
                                <div class="text-end">
                                    <span class="small fw-bold text-primary d-block" x-text="'Jual Rp ' + formatNumber(itemProd.selling_price)"></span>
                                    <span class="text-muted" style="font-size:10.5px" x-text="'HPP Rp ' + formatNumber(itemProd.hpp || itemProd.cogs || 0)"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="filteredBundleProducts.length === 0" class="text-muted text-center small py-3">Menu tidak ditemukan.</div>
                    </div>
                </div>

                <div x-show="bundlingForm.bundle_data.length > 0" class="mb-3">
                    <label class="form-label fw-semibold text-dark">Isi Paket (<span x-text="bundleItemCount()"></span> item)</label>
                    <div class="border rounded-3 overflow-hidden">
                        <template x-for="item in bundlingForm.bundle_data" :key="item.product_id">
                            <div class="d-flex align-items-center gap-2 p-2 border-bottom">
                                <div class="flex-grow-1 small fw-semibold" x-text="item.product_name"></div>
                                <input type="number" min="1" step="1" x-model.number="item.qty" @input="normalizeBundleQty(item)" class="form-input text-center" style="width:66px" aria-label="Jumlah menu dalam paket" />
                                <div class="text-end" style="width:110px">
                                    <div class="small fw-bold" x-text="'Rp ' + formatNumber((item.hpp || 0) * (item.qty || 1))"></div>
                                    <div class="text-muted" style="font-size:10px">subtotal HPP</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeBundleProduct(item.product_id)">x</button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:#f5f3ff;border:1px solid #ddd6fe">
                            <div class="text-muted fw-semibold" style="font-size:10.5px;text-transform:uppercase">Total HPP Paket</div>
                            <div class="fw-extrabold" style="font-size:18px;color:#6d28d9" x-text="'Rp ' + formatNumber(calcBundleHpp())"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa">
                            <div class="text-muted fw-semibold" style="font-size:10.5px;text-transform:uppercase">Estimasi Margin</div>
                            <div class="fw-extrabold" style="font-size:18px;color:#c2410c" x-text="'Rp ' + formatNumber(Math.max(0, (bundlingForm.selling_price || 0) - calcBundleHpp()))"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Deskripsi / Catatan Bundling</label>
                    <textarea x-model="bundlingForm.description" class="form-input" rows="2" placeholder="Deskripsi singkat mengenai isi paket bundling..."></textarea>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" x-model="bundlingForm.available_for_pos" class="form-check-input" id="checkPosBundling">
                    <label class="form-check-label fw-bold text-dark" for="checkPosBundling">Tampilkan di POS Kasir</label>
                </div>
            </div>
            <div class="modal-footer border-top pt-2">
                <button class="btn btn-secondary" style="border-radius:8px" @click="showBundlingModal=false">Batal</button>
                <button class="btn btn-warning text-white fw-bold shadow-sm" :disabled="saving" @click="saveBundling()" style="background:#f59e0b; border:none; border-radius:8px">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan Paket Bundling</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function productsApp() {
    return {
        loading: false, saving: false,
        products: [], bundleProducts: [], categories: [], availableIngredients: [],
        search: '', filterCategory: '',
        showModal: false, editId: null,
        form: { name: '', category_id: '', selling_price: 0, description: '', available_for_pos: true },
        recipeItems: [],
        
        showCatModal: false, newCatName: '', newCatColor: '#6366f1', savingCat: false,
        editingCatId: null, editingCatName: '', editingCatColor: '#6366f1',
        
        showBundlingModal: false, bundlingEditId: null, bundleSearch: '',
        bundlingForm: { name: '', category_id: '', selling_price: 0, description: '', available_for_pos: true, is_bundling: true, bundle_data: [] },

        async init() {
            const token = localStorage.getItem('erp_token');
            if (token) {
                axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
            }
            await Promise.all([this.load(), this.loadBundleProducts(), this.loadCats(), this.loadIngredients()]);
        },

        async load() {
            this.loading = true;
            try {
                const params = { search: this.search, category_id: this.filterCategory };
                const r = await axios.get('/api/v1/products', { params });
                this.products = r.data.data ?? r.data ?? [];
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

        async loadBundleProducts() {
            try {
                const r = await axios.get('/api/v1/products', {
                    params: { per_page: 1000, status: 'active', is_bundling: 0 }
                });
                this.bundleProducts = r.data.data ?? r.data ?? [];
            } catch(e) {
                console.error('Gagal memuat menu untuk bundling', e);
                this.bundleProducts = [];
            }
        },

        get filteredBundleProducts() {
            const keyword = this.bundleSearch.trim().toLowerCase();
            return this.bundleProducts.filter(p => !keyword || p.name.toLowerCase().includes(keyword));
        },

        async loadIngredients() {
            try {
                const r = await axios.get('/api/v1/inventory/ingredients?per_page=200');
                this.availableIngredients = r.data.data ?? r.data ?? [];
            } catch(e) { console.error(e); }
        },

        calcAvgPrice() {
            if (!this.products.length) return 0;
            const sum = this.products.reduce((acc, p) => acc + parseFloat(p.selling_price || 0), 0);
            return Math.round(sum / this.products.length);
        },

        openForm(p = null) {
            if (p) {
                this.editId = p.id;
                this.form = {
                    name: p.name, category_id: p.category_id,
                    selling_price: p.selling_price, description: p.description || '',
                    available_for_pos: Boolean(p.available_for_pos)
                };
                if (p.recipe && p.recipe.ingredients) {
                    this.recipeItems = p.recipe.ingredients.map(rIng => {
                        const ing = rIng.ingredient;
                        return {
                            ingredient_id: rIng.ingredient_id,
                            qty: rIng.qty,
                            unit_symbol: ing?.unit?.symbol || ing?.unit || 'unit',
                            unit_cost: ing?.recipe_unit_cost || rIng.unit_cost || 0
                        };
                    });
                } else {
                    this.recipeItems = [];
                }
            } else {
                this.editId = null;
                this.form = { name: '', category_id: this.categories[0]?.id || '', selling_price: 0, description: '', available_for_pos: true };
                this.recipeItems = [];
            }
            this.showModal = true;
        },

        addRecipeRow() {
            this.recipeItems.push({ ingredient_id: '', qty: 1, unit_symbol: '', unit_cost: 0 });
        },

        removeRecipeRow(idx) {
            this.recipeItems.splice(idx, 1);
        },

        onIngredientSelect(ingRow) {
            const found = this.availableIngredients.find(i => i.id == ingRow.ingredient_id);
            if (found) {
                ingRow.unit_symbol = found.unit?.symbol || found.unit || 'unit';
                ingRow.unit_cost = found.recipe_unit_cost || found.average_cost || found.purchase_price || 0;
            }
        },

        calcTotalHpp() {
            return this.recipeItems.reduce((sum, item) => sum + ((item.qty || 0) * (item.unit_cost || 0)), 0);
        },

        async saveProduct() {
            if (!this.form.name.trim()) return toast('Nama menu wajib diisi!', 'warning');
            if (!this.form.category_id) return toast('Kategori menu wajib dipilih!', 'warning');
            
            this.saving = true;
            try {
                const payload = {
                    ...this.form,
                    recipe: {
                        name: `Resep ${this.form.name}`,
                        items: this.recipeItems.filter(i => i.ingredient_id && i.qty > 0)
                    }
                };

                if (this.editId) {
                    await axios.put(`/api/v1/products/${this.editId}`, payload);
                    toast('Produk & resep berhasil diperbarui!');
                } else {
                    await axios.post('/api/v1/products', payload);
                    toast('Produk menu baru berhasil ditambahkan!');
                }
                this.showModal = false;
                await this.load();
                await this.loadCats();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan produk', 'error');
            } finally { this.saving = false; }
        },

        openBundlingForm(p = null) {
            const defaultCat = this.categories.find(c => c.name.toLowerCase().includes('paket') || c.name.toLowerCase().includes('bundling')) || this.categories[0];
            this.bundlingEditId = p?.id || null;
            this.bundleSearch = '';
            this.bundlingForm = {
                name: p?.name || '',
                category_id: p?.category_id || (defaultCat ? defaultCat.id : ''),
                selling_price: parseFloat(p?.selling_price || 0),
                description: p?.description || '',
                available_for_pos: p ? Boolean(p.available_for_pos) : true,
                is_bundling: true,
                bundle_data: (p?.bundle_data || []).map(item => ({
                    ...item,
                    qty: Math.max(1, parseInt(item.qty || 1)),
                    hpp: parseFloat(item.hpp || 0),
                    price: parseFloat(item.price || 0)
                }))
            };
            this.showBundlingModal = true;
        },

        isProductSelectedInBundle(productId) {
            if (!this.bundlingForm.bundle_data) return false;
            return this.bundlingForm.bundle_data.some(b => b.product_id == productId);
        },

        toggleBundleProduct(prod) {
            if (!this.bundlingForm.bundle_data) this.bundlingForm.bundle_data = [];
            const idx = this.bundlingForm.bundle_data.findIndex(b => b.product_id == prod.id);
            if (idx > -1) {
                this.bundlingForm.bundle_data.splice(idx, 1);
            } else {
                this.bundlingForm.bundle_data.push({
                    product_id: prod.id,
                    product_name: prod.name,
                    qty: 1,
                    price: parseFloat(prod.selling_price || 0),
                    hpp: parseFloat(prod.hpp || prod.cogs || 0)
                });
            }
        },

        removeBundleProduct(productId) {
            this.bundlingForm.bundle_data = this.bundlingForm.bundle_data.filter(item => item.product_id != productId);
        },

        normalizeBundleQty(item) {
            item.qty = Math.max(1, parseInt(item.qty || 1));
        },

        calcBundleHpp() {
            return this.bundlingForm.bundle_data.reduce((sum, item) =>
                sum + (parseFloat(item.hpp || 0) * Math.max(1, parseInt(item.qty || 1))), 0);
        },

        bundleItemCount() {
            return this.bundlingForm.bundle_data.reduce((sum, item) =>
                sum + Math.max(1, parseInt(item.qty || 1)), 0);
        },

        async saveBundling() {
            if (!this.bundlingForm.name.trim()) return toast('Nama paket bundling wajib diisi!', 'warning');
            if (!this.bundlingForm.category_id) return toast('Kategori paket wajib dipilih!', 'warning');
            if (!this.bundlingForm.bundle_data.length) return toast('Pilih minimal satu menu untuk paket bundling!', 'warning');
            if (parseFloat(this.bundlingForm.selling_price || 0) <= 0) return toast('Harga jual paket wajib lebih dari 0!', 'warning');
            this.saving = true;
            try {
                const payload = {
                    ...this.bundlingForm,
                    is_bundling: true,
                    bundle_data: this.bundlingForm.bundle_data.map(item => ({
                        product_id: item.product_id,
                        qty: Math.max(1, parseInt(item.qty || 1))
                    }))
                };
                if (this.bundlingEditId) {
                    await axios.put(`/api/v1/products/${this.bundlingEditId}`, payload);
                    toast('Paket bundling berhasil diperbarui!');
                } else {
                    await axios.post('/api/v1/products', payload);
                    toast('Paket bundling berhasil ditambahkan!');
                }
                this.showBundlingModal = false;
                await Promise.all([this.load(), this.loadBundleProducts()]);
                await this.loadCats();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan paket bundling', 'error');
            } finally { this.saving = false; }
        },

        getCatBadgeStyle(c) {
            const color = (c && c.color) ? c.color : '#ea580c';
            return `background: ${color}1f; color: ${color}; border: 1px solid ${color}4d; font-size: 10.5px; font-weight: 600; line-height: 1.2; padding: 3px 9px; border-radius: 9999px; display: inline-flex; align-items: center; white-space: nowrap;`;
        },

        async openCatModal() {
            this.newCatName = '';
            this.newCatColor = '#6366f1';
            this.cancelEditCat();
            await this.loadCats();
            this.showCatModal = true;
        },

        async saveCat() {
            if (!this.newCatName.trim()) return toast('Nama kategori wajib diisi!', 'warning');
            this.savingCat = true;
            try {
                await axios.post('/api/v1/product-categories', {
                    name: this.newCatName,
                    color: this.newCatColor || '#6366f1'
                });
                toast('Kategori produk berhasil ditambahkan!');
                this.newCatName = '';
                this.newCatColor = '#6366f1';
                await this.loadCats();
            } catch(e) {
                const msg = e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Gagal menambahkan kategori';
                toast(msg, 'error');
            } finally { this.savingCat = false; }
        },

        startEditCat(c) {
            this.editingCatId = c.id;
            this.editingCatName = c.name;
            this.editingCatColor = c.color || '#6366f1';
        },

        cancelEditCat() {
            this.editingCatId = null;
            this.editingCatName = '';
            this.editingCatColor = '#6366f1';
        },

        async updateCat(c) {
            if (!this.editingCatName.trim()) return toast('Nama kategori tidak boleh kosong!', 'warning');
            try {
                await axios.put(`/api/v1/product-categories/${c.id}`, {
                    name: this.editingCatName,
                    color: this.editingCatColor || '#6366f1'
                });
                toast('Kategori produk berhasil diperbarui!');
                this.cancelEditCat();
                await this.loadCats();
                await this.load();
            } catch(e) {
                const msg = e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Gagal mengedit kategori';
                toast(msg, 'error');
            }
        },

        deleteCat(c) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/product-categories/${c.id}`);
                    toast('Kategori berhasil dihapus!');
                    await this.loadCats();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus kategori', 'error');
                }
            }, c.name);
        },

        delProduct(p) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/products/${p.id}`);
                    toast('Produk berhasil dihapus!');
                    await this.load();
                    await this.loadCats();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus produk', 'error');
                }
            }, p.name);
        },

        formatNumber(val) {
            if (val === null || val === undefined || val === '') return '0';
            const num = Number(val);
            if (isNaN(num)) return '0';
            return num.toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }
    }
}
</script>
@endpush
