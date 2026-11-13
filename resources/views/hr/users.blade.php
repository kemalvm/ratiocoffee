@extends('layouts.erp')
@section('title', 'Manajemen User & Karyawan – Ratio Coffee ERP')

@section('page-content')
<div x-data="usersApp()" x-init="init()" x-cloak class="container-fluid p-0">
    <!-- Top Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-bold m-0 text-primary d-flex align-items-center gap-2" style="font-size: 1.4rem;">
                <span>👥</span> Manajemen User & Access Control
            </h3>
            <p class="text-muted small m-0 mt-1">Kelola data karyawan, hak akses role, lokasi outlet, dan otentikasi akun</p>
        </div>
        <div>
            <button class="btn btn-primary" @click="openForm()">
                <span>👤</span> + Tambah Karyawan Baru
            </button>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="data-table align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Nama Pengguna</th>
                        <th>Email</th>
                        <th>Role Akses</th>
                        <th>Outlet / Branch</th>
                        <th>Status MFA</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="6" class="text-center py-5 text-muted">⏳ Memuat data user...</td></tr>
                    </template>
                    <template x-if="!loading && users.length === 0">
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada user terdaftar.</td></tr>
                    </template>
                    <template x-for="u in users" :key="u.id">
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="topbar-avatar fw-bold" x-text="getInitials(u.name)"></div>
                                    <div>
                                        <div class="fw-bold" x-text="u.name"></div>
                                        <div class="text-muted small" x-text="u.position || 'Staf Operasional'"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted" x-text="u.email"></td>
                            <td><span class="badge badge-info px-3 py-1" x-text="u.role?.name || u.role_name || u.role || 'Staff'"></span></td>
                            <td class="text-muted" x-text="u.outlet?.name || 'Ratio Coffee HQ'"></td>
                            <td>
                                <span :class="['badge', u.mfa_enabled ? 'badge-success' : 'badge-muted']"
                                      x-text="u.mfa_enabled ? '🔒 MFA Aktif' : '🔓 Non-MFA'"></span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-secondary btn-sm me-1" @click="openForm(u)">✏️ Edit</button>
                                <button class="btn btn-danger btn-sm" @click="delUser(u)">🗑</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal User Form -->
    <div x-show="showModal" class="modal-overlay" @click.self="showModal=false" style="display:flex">
        <div class="modal-box" style="max-width:480px">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" x-text="editId ? '✏️ Edit Karyawan' : '👤 Tambah Karyawan Baru'"></h5>
                <button class="btn btn-secondary btn-sm" @click="showModal=false">✕</button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Nama Lengkap *</label>
                        <input x-model="form.name" class="form-input" placeholder="mis. Budi Santoso" required />
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Username (Opsional)</label>
                        <input x-model="form.username" class="form-input" placeholder="budi_santoso" />
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Email *</label>
                        <input x-model="form.email" type="email" class="form-input" placeholder="budi@ratiocoffee.com" required />
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Jabatan / Posisi</label>
                        <input x-model="form.position" class="form-input" placeholder="Barista / Supervisor / Partner" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role Akses (5 Role Sistem) *</label>
                    <select x-model="form.role_id" class="form-input" required>
                        <option value="">Pilih Role Akses</option>
                        <template x-for="r in roles" :key="r.id">
                            <option :value="r.id" x-text="r.name"></option>
                        </template>
                    </select>
                </div>
                <div class="mb-3" x-show="!editId">
                    <label class="form-label fw-semibold">Password Initial *</label>
                    <input x-model="form.password" type="password" class="form-input" placeholder="••••••••" />
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" @click="showModal=false">Batal</button>
                <button class="btn btn-primary" :disabled="saving" @click="saveUser()">
                    <span x-show="saving">⏳ Menyimpan...</span>
                    <span x-show="!saving">Simpan User</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function usersApp() {
    return {
        loading: false, saving: false,
        users: @json(\App\Models\User::with(['role', 'outlet'])->whereNull('deleted_at')->orderBy('name')->get()),
        roles: @json(\App\Models\Role::orderBy('name')->get()),
        showModal: false, editId: null,
        form: { name: '', username: '', email: '', position: '', role_id: '', password: '' },

        async init() {
            await Promise.all([this.load(), this.loadRoles()]);
        },

        async load() {
            try {
                const r = await axios.get('/api/v1/users');
                const list = Array.isArray(r.data.data) ? r.data.data : (Array.isArray(r.data) ? r.data : []);
                if (list.length > 0) {
                    this.users = list;
                }
            } catch(e) { console.error('Error loading users:', e); }
        },

        async loadRoles() {
            try {
                const r = await axios.get('/api/v1/roles');
                const list = Array.isArray(r.data.data) ? r.data.data : (Array.isArray(r.data) ? r.data : []);
                if (list.length > 0) {
                    this.roles = list;
                }
            } catch(e) { console.error('Error loading roles:', e); }
        },

        openForm(u = null) {
            if (u) {
                this.editId = u.id;
                this.form = { name: u.name, username: u.username || '', email: u.email, position: u.position || '', role_id: u.role_id || u.role?.id, password: '' };
            } else {
                this.editId = null;
                this.form = { name: '', username: '', email: '', position: '', role_id: '', password: '' };
            }
            this.showModal = true;
        },

        async saveUser() {
            this.saving = true;
            try {
                if (this.editId) {
                    await axios.put(`/api/v1/users/${this.editId}`, this.form);
                    toast('Data karyawan diperbarui!');
                } else {
                    await axios.post('/api/v1/users', this.form);
                    toast('Karyawan baru ditambahkan!');
                }
                this.showModal = false;
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan user', 'error');
            } finally { this.saving = false; }
        },

        delUser(u) {
            confirmDelete(async () => {
                try {
                    await axios.delete(`/api/v1/users/${u.id}`);
                    toast('User berhasil dihapus!');
                    await this.load();
                } catch(e) {
                    toast(e.response?.data?.message || 'Gagal menghapus user', 'error');
                }
            }, u.name);
        },

        getInitials(name) {
            if (!name) return 'U';
            return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        }
    }
}
</script>
@endpush
