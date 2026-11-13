@extends('layouts.app')
@section('title', 'Installation Wizard – Ratio Coffee ERP')

@section('content')
<div x-data="setupWizardApp()" x-cloak class="d-flex align-items-center justify-content-center min-vh-100 p-3" style="background:var(--bg-base)">
    <div class="card p-4 shadow-lg" style="max-width:540px;width:100%;border-radius:16px">
        <!-- Logo Header -->
        <div class="text-center mb-4">
            <div style="font-size:40px">☕</div>
            <h3 class="fw-bold m-0" style="color:var(--txt-primary)">Installation Wizard</h3>
            <p class="small text-muted m-0">Selamat datang di Ratio Coffee ERP. Konfigurasi profil toko dan akun CEO Anda.</p>
        </div>

        <!-- Step Indicator -->
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <span :class="['badge', step === 1 ? 'badge-primary' : 'badge-muted']" style="padding:8px 16px;font-size:12px">1. Profil Toko</span>
            <span class="text-muted">→</span>
            <span :class="['badge', step === 2 ? 'badge-primary' : 'badge-muted']" style="padding:8px 16px;font-size:12px">2. Akun CEO</span>
        </div>

        <form @submit.prevent="submitSetup()">
            <!-- Step 1: Shop Profile -->
            <div x-show="step === 1">
                <div class="mb-3">
                    <label class="form-label">Nama Coffee Shop *</label>
                    <input x-model="form.shop_name" class="form-input" placeholder="Ratio Coffee Specialty" required />
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat Utama</label>
                    <input x-model="form.address" class="form-input" placeholder="Jl. Kopi Sudirman No. 12" />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">No. Telepon Toko</label>
                        <input x-model="form.phone" class="form-input" placeholder="0812xxxxxxx" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Email Toko</label>
                        <input x-model="form.email" type="email" class="form-input" placeholder="info@ratiocoffee.id" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Zona Waktu (Timezone) *</label>
                    <select x-model="form.timezone" class="form-input">
                        <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                        <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                        <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary w-100 py-2 mt-2" @click="step = 2">
                    Lanjut ke Akun CEO →
                </button>
            </div>

            <!-- Step 2: CEO Account Setup -->
            <div x-show="step === 2">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap CEO *</label>
                    <input x-model="form.name" class="form-input" placeholder="Alexander Pratama" required />
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Username CEO *</label>
                        <input x-model="form.username" class="form-input" placeholder="ceo" required />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Email CEO *</label>
                        <input x-model="form.ceo_email" type="email" class="form-input" placeholder="ceo@ratiocoffee.id" required />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password CEO *</label>
                    <input x-model="form.password" type="password" class="form-input" placeholder="Minimal 8 karakter" required />
                </div>

                <div x-show="error" class="alert alert-danger p-2 mb-3 small" x-text="'⚠️ ' + error"></div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-secondary w-50" @click="step = 1">← Kembali</button>
                    <button type="submit" class="btn btn-primary w-50" :disabled="loading">
                        <span x-show="loading">⏳ Memproses...</span>
                        <span x-show="!loading">🚀 Selesaikan Instalasi</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setupWizardApp() {
    return {
        step: 1, loading: false, error: null,
        form: {
            shop_name: 'Ratio Coffee Specialty',
            address: 'Jl. Boulevard Utama No. 88, Jakarta Selatan',
            phone: '081122334455',
            email: 'admin@ratiocoffee.id',
            timezone: 'Asia/Jakarta',
            name: '', username: '', ceo_email: '', password: ''
        },

        async submitSetup() {
            this.loading = true;
            this.error = null;
            try {
                const r = await axios.post('/api/v1/setup/install', this.form);
                localStorage.setItem('erp_token', r.data.access_token);
                localStorage.setItem('erp_user', JSON.stringify(r.data.user));
                window.location.href = '/dashboard';
            } catch(e) {
                this.error = e.response?.data?.message || 'Gagal memproses instalasi.';
            } finally { this.loading = false; }
        }
    }
}
</script>
@endpush
