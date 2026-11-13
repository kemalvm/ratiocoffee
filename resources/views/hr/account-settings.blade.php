@extends('layouts.erp')
@section('title', 'Pengaturan Akun – Ratio Coffee ERP')

@section('page-content')
<div x-data="accountSettingsApp()" x-init="init()" x-cloak>
    <div class="card p-4" style="max-width:600px">
        <h5 class="fw-bold mb-3">👤 Profil Akun Saya</h5>
        <form @submit.prevent="saveProfile()">
            <div class="mb-3">
                <label class="form-label">Nama Lengkap *</label>
                <input x-model="user.name" class="form-input" required />
            </div>
            <div class="mb-3">
                <label class="form-label">Alamat Email *</label>
                <input x-model="user.email" type="email" class="form-input" required />
            </div>
            <div class="mb-4">
                <label class="form-label">Role Akses</label>
                <input :value="user.role?.name || 'User Administrator'" class="form-input" disabled style="background:var(--bg-muted)" />
            </div>
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span x-show="saving">⏳ Menyimpan...</span>
                <span x-show="!saving">💾 Simpan Profil</span>
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function accountSettingsApp() {
    return {
        saving: false,
        user: JSON.parse(localStorage.getItem('erp_user') || '{}'),

        async init() {
            try {
                const r = await axios.get('/api/v1/auth/me');
                if (r.data) {
                    this.user = r.data.user || r.data;
                    localStorage.setItem('erp_user', JSON.stringify(this.user));
                }
            } catch(e) {}
        },

        async saveProfile() {
            this.saving = true;
            try {
                const r = await axios.put('/api/v1/auth/profile', this.user);
                toast('Profil berhasil diperbarui!');
                localStorage.setItem('erp_user', JSON.stringify(this.user));
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan profil', 'error');
            } finally { this.saving = false; }
        }
    }
}
</script>
@endpush
