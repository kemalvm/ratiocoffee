@extends('layouts.erp')
@section('title', 'Riwayat Mutasi & Perubahan Stok – Ratio Coffee ERP')

@section('page-content')
<div x-data="stockMovementsApp()" x-init="init()" x-cloak class="container-fluid p-0">
    
    <!-- Top Header with Back Button -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-bold m-0 text-primary d-flex align-items-center gap-2" style="font-size: 1.4rem;">
                <span>📋</span> Riwayat Mutasi & Perubahan Stok
            </h3>
            <p class="text-muted small m-0 mt-1">Daftar pencatatan mutasi stok bahan baku, kulakan, penyesuaian, dan waste secara real-time</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/inventory" class="btn btn-sm fw-bold d-inline-flex align-items-center gap-2 px-3 py-2 shadow-xs" style="border-radius: 10px; font-size: 12.5px; background: rgba(234, 88, 12, 0.08); color: var(--clr-primary, #ea580c); border: 1.5px solid var(--clr-primary, #ea580c); transition: all 0.2s ease;" onmouseover="this.style.background='var(--clr-primary, #ea580c)'; this.style.color='#ffffff';" onmouseout="this.style.background='rgba(234, 88, 12, 0.08)'; this.style.color='var(--clr-primary, #ea580c)';">
                <span>⬅️</span> Kembali ke Inventory
            </a>
        </div>
    </div>

    <!-- Filter & Search Card -->
    <div class="card p-3 mb-4 border-0 shadow-sm">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <input x-model="search" @input.debounce.300ms="load()" class="form-input" placeholder="🔍 Cari nama bahan baku, kode, atau keterangan mutasi..." />
            </div>
            <div class="col-12 col-md-3">
                <select x-model="filterType" @change="load()" class="form-input">
                    <option value="">Semua Tipe Mutasi</option>
                    <option value="stock_in">📦 Stock In / Kulakan</option>
                    <option value="stock_out">📤 Stock Out / Kasir POS</option>
                    <option value="adjustment">⚖️ Adjustment / Penyesuaian</option>
                    <option value="waste">🗑️ Waste / Rusak</option>
                    <option value="transfer">🔄 Transfer Gudang</option>
                </select>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                <span class="badge badge-muted px-3 py-2 fs-6" x-text="movements.length + ' Catatan Mutasi'"></span>
            </div>
        </div>
    </div>

    <!-- Stock History Table -->
    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="data-table align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Waktu</th>
                        <th>Item Bahan Baku</th>
                        <th>Tipe Mutasi</th>
                        <th>Gudang</th>
                        <th>Jumlah (Qty)</th>
                        <th>Stok Sebelum</th>
                        <th>Stok Sesudah</th>
                        <th>Catatan / Keterangan</th>
                        <th class="text-end pe-4">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="9" class="text-center py-5 text-muted">⏳ Memuat riwayat mutasi stok...</td></tr>
                    </template>
                    <template x-if="!loading && movements.length === 0">
                        <tr><td colspan="9" class="text-center py-5 text-muted">Belum ada riwayat mutasi stok terdaftar.</td></tr>
                    </template>
                    <template x-for="m in movements" :key="m.id">
                        <tr>
                            <td class="ps-4 text-nowrap" x-text="formatDate(m.created_at || m.movement_at, 'full')"></td>
                            <td>
                                <div class="fw-bold text-dark" x-text="m.ingredient?.name || m.ingredient_name || 'Bahan Baku'"></div>
                                <div class="small text-muted" x-text="m.ingredient?.code ? 'Kode: ' + m.ingredient.code : ''"></div>
                            </td>
                            <td>
                                <span :class="['badge', typeBadgeClass(m.type)]" x-text="typeLabel(m.type)"></span>
                            </td>
                            <td class="small text-muted" x-text="m.warehouse?.name || 'Gudang Utama'"></td>
                            <td class="fw-bold fs-6" :class="isPositiveType(m.type, m.qty) ? 'text-success' : 'text-danger'">
                                <span x-text="(isPositiveType(m.type, m.qty) ? '+' : '') + formatQty(m.qty)"></span>
                                <span class="small text-muted fw-normal" x-text="m.ingredient?.unit?.symbol || ''"></span>
                            </td>
                            <td class="text-muted" x-text="formatQty(m.qty_before || m.before_stock)"></td>
                            <td class="fw-bold" x-text="formatQty(m.qty_after || m.after_stock)"></td>
                            <td class="small text-muted" style="max-width: 220px;" x-text="m.notes || m.description || 'Mutasi sistem'"></td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-1 justify-content-end">
                                    <button class="btn btn-secondary btn-sm px-2 py-1" title="Edit Riwayat Stok" @click="openEditModal(m)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm px-2 py-1" title="Hapus Riwayat Stok" @click="deleteMovement(m)">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Stock Movement Modal -->
    <div x-show="showEditModal" class="modal-overlay" @click.self="showEditModal = false" style="display:flex;">
        <div class="modal-box" style="max-width: 500px;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">✏️ Edit Riwayat Stok</h5>
                <button class="btn btn-secondary btn-sm" @click="showEditModal = false">✕</button>
            </div>
            <form @submit.prevent="saveEditMovement()">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Item Bahan Baku</label>
                        <input type="text" class="form-input" :value="editForm.ingredient_name" disabled style="opacity: 0.7; cursor: not-allowed;" />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Jumlah Qty *</label>
                            <input x-model.number="editForm.qty" type="number" step="0.001" class="form-input" required />
                            <span class="form-text text-muted" style="font-size:11px">Gunakan nilai negatif jika mengurangi stok.</span>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Unit Cost / HPP (Rp) *</label>
                            <input x-model.number="editForm.unit_cost" type="number" step="1" min="0" class="form-input" required />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan / Keterangan Mutasi</label>
                        <textarea x-model="editForm.notes" class="form-input" rows="3" placeholder="Masukkan alasan pengubahan riwayat stok..."></textarea>
                    </div>
                    <div class="alert alert-warning p-2 rounded small m-0 mb-2">
                        ⚠️ <strong>Perhatian:</strong> Perubahan jumlah Qty akan secara otomatis menyesuaikan nilai stok terkini (Database) item bahan baku tersebut.
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-end gap-2 pt-0 border-0">
                    <button type="button" class="btn btn-secondary" @click="showEditModal = false">Batal</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving">⏳ Menyimpan...</span>
                        <span x-show="!saving">💾 Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function stockMovementsApp() {
    return {
        loading: false, saving: false,
        movements: [], search: '', filterType: '',
        showEditModal: false,
        editForm: { id: null, ingredient_name: '', qty: 0, unit_cost: 0, notes: '' },

        async init() { await this.load(); },

        async load() {
            this.loading = true;
            try {
                const params = {};
                if (this.search) params.search = this.search;
                if (this.filterType) params.type = this.filterType;

                const r = await axios.get('/api/v1/inventory/stock-movements', { params });
                this.movements = r.data.data ?? r.data ?? [];
            } catch(e) { 
                console.error(e); 
            } finally { 
                this.loading = false; 
            }
        },

        typeLabel(t) {
            return {
                stock_in: '📦 Stock In',
                stock_out: '📤 Stock Out (POS)',
                adjustment: '⚖️ Adjustment',
                waste: '🗑️ Waste',
                transfer: '🔄 Transfer',
                opname: '📊 Opname'
            }[t] || t;
        },

        typeBadgeClass(t) {
            return {
                stock_in: 'badge-success',
                stock_out: 'badge-danger',
                adjustment: 'badge-info',
                waste: 'badge-warning',
                transfer: 'badge-muted',
                opname: 'badge-primary'
            }[t] || 'badge-muted';
        },

        isPositiveType(t, qty) {
            if (t === 'stock_in') return true;
            if (t === 'stock_out' || t === 'waste') return false;
            return parseFloat(qty || 0) >= 0;
        },

        openEditModal(m) {
            this.editForm = {
                id: m.id,
                ingredient_name: m.ingredient?.name || m.ingredient_name || 'Bahan Baku',
                qty: parseFloat(m.qty || 0),
                unit_cost: parseFloat(m.unit_cost || 0),
                notes: m.notes || m.description || ''
            };
            this.showEditModal = true;
        },

        async saveEditMovement() {
            if (!this.editForm.id) return;
            this.saving = true;
            try {
                const res = await axios.put(`/api/v1/inventory/stock-movements/${this.editForm.id}`, {
                    qty: this.editForm.qty,
                    unit_cost: this.editForm.unit_cost,
                    notes: this.editForm.notes
                });
                toast(res.data?.message || 'Riwayat stok & database berhasil diperbarui!');
                this.showEditModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memperbarui riwayat stok', 'error');
            } finally {
                this.saving = false;
            }
        },

        deleteMovement(m) {
            const ingName = m.ingredient?.name || m.ingredient_name || 'Bahan Baku';
            confirmDelete(async () => {
                try {
                    const res = await axios.delete(`/api/v1/inventory/stock-movements/${m.id}`);
                    toast(res.data?.message || 'Riwayat pergerakan stok berhasil dihapus permanen!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus riwayat stok', 'error');
                }
            }, `riwayat mutasi "${ingName}"`);
        },

        formatQty(val) {
            if (val === null || val === undefined || val === '') return '0';
            let num = Number(val);
            if (isNaN(num)) return '0';
            if (Math.abs(num - Math.round(num)) < 0.0001) {
                return Math.round(num).toLocaleString('id-ID');
            }
            return num.toLocaleString('id-ID', { maximumFractionDigits: 3 });
        },

        formatDate(str, mode = 'short') {
            if (!str) return '-';
            const d = new Date(str);
            if (isNaN(d)) return str;
            if (mode === 'full') {
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            }
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }
    }
}
</script>
@endpush
