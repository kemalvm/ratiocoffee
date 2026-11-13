@extends('layouts.erp')
@section('title', 'HQ Control Center – Ratio Coffee ERP')

@section('page-content')
<div x-data="hqControlApp()" x-init="init()" x-cloak>

    <!-- Header Banner -->
    <div class="welcome-banner mb-4">
        <div class="welcome-info">
            <div class="welcome-badge">
                <span class="live-indicator"><span class="pulse-dot"></span> HQ CONTROL CENTER</span>
                <span class="dot-sep">•</span>
                <span x-text="(brand.brand_name || 'RATIO COFFEE').toUpperCase()"></span>
            </div>
            <h2 class="welcome-title">🏢 Executive HQ Control Center</h2>
            <p class="welcome-sub">Pusat pemantauan multi-outlet, kontrol identitas brand, dan audit operasional langsung secara real-time.</p>
        </div>
        <div class="welcome-summary">
            <div class="summary-box">
                <div class="summary-label">JAM BUKA</div>
                <div class="summary-val" x-text="brand.operating_hours_open || '10:00'"></div>
            </div>
            <div class="summary-box">
                <div class="summary-label">JAM TUTUP</div>
                <div class="summary-val" x-text="brand.operating_hours_close || '22:00'"></div>
            </div>
        </div>
    </div>

    <!-- Brand Identity Grid -->
    <div class="row g-4">

        <!-- Left Column: Mockup Preview & Logo Uploader -->
        <div class="col-12 col-lg-5 col-xl-4">
            <!-- Sidebar Mock Preview Card -->
            <div class="card p-4 text-center mb-4 shadow-sm" style="border-radius: 16px; background: var(--bg-surface); border: 1px solid var(--border);">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold m-0 text-dark" style="font-size: 14px;">👁️ Preview Live Sidebar ERP</h6>
                    <span class="badge px-2.5 py-1 rounded-pill" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-size: 10.5px; font-weight: 600;">Real-time Sync</span>
                </div>
                
                <!-- Mock Sidebar Container -->
                <div class="p-4 text-center rounded-3 position-relative overflow-hidden shadow-xs" 
                     style="background: #111827; border: 1px solid rgba(255,255,255,0.12); min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; border-radius: 14px !important;">
                    
                    <!-- Logo Avatar Box with High-Contrast Background -->
                    <div style="width: 76px; height: 76px; border-radius: 18px; background: rgba(255, 255, 255, 0.08); border: 1.5px solid rgba(255, 255, 255, 0.18); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 6px; box-shadow: 0 4px 14px rgba(0,0,0,0.3);">
                        <img x-show="(logoPreview || isImageLogo(brand.logo_url)) && !imgErr" 
                             :src="logoPreview || formatLogoUrl(brand.logo_url)" 
                             style="width: 100%; height: 100%; object-fit: contain;" 
                             x-on:error="imgErr = true" />
                        <span x-show="imgErr || (!logoPreview && !isImageLogo(brand.logo_url))" 
                              style="font-size: 32px;" 
                              x-text="(!isImageLogo(brand.logo_url) && brand.logo_url) ? brand.logo_url : '☕'"></span>
                    </div>

                    <div>
                        <div class="text-white fw-extrabold" style="font-size: 15px; letter-spacing: -0.2px;" x-text="brand.brand_name || 'Ratio Coffee'"></div>
                        <div style="color: rgba(255, 255, 255, 0.55); font-size: 11.5px; margin-top: 2px;" x-text="brand.tagline || 'ERP Control Center'"></div>
                    </div>
                </div>

                <!-- High-Contrast Logo Upload Box -->
                <div class="mt-4 text-start">
                    <label class="form-label fw-bold d-flex align-items-center justify-content-between" style="font-size: 13px;">
                        <span>🖼️ Upload Logo Brand</span>
                        <span class="text-muted fw-normal" style="font-size: 11px;">Maks. 10MB</span>
                    </label>

                    <!-- Dropzone Container -->
                    <div style="border: 2px dashed rgba(234, 88, 12, 0.3); border-radius: 14px; padding: 18px; text-align: center; cursor: pointer; background: var(--bg-base, #f8fafc); transition: all 0.2s ease;"
                         @dragover.prevent 
                         @drop.prevent="onFileDrop($event)"
                         :style="(logoPreview || brand.logo_url) ? 'border-color: var(--clr-primary); background: rgba(234, 88, 12, 0.02);' : ''">
                        
                        <input type="file" id="logoFileInput" style="display:none"
                               accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml,image/webp"
                               @change="onFileSelected($event)" />
                        
                        <label for="logoFileInput" class="m-0 w-100" style="cursor: pointer; display: block;">
                            <!-- Active Image Preview Container (Dark Contrast background so white/dark logos pop out) -->
                            <template x-if="logoPreview || isImageLogo(brand.logo_url)">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="p-2 rounded-3 mb-2 shadow-xs" style="background: #0f172a; border: 1px solid rgba(255,255,255,0.15); width: 100%; max-width: 180px; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                                        <img :src="logoPreview || formatLogoUrl(brand.logo_url)" 
                                             style="max-width: 100%; max-height: 80px; object-fit: contain;" />
                                    </div>
                                    <div class="badge px-2.5 py-1 rounded-pill fw-semibold text-truncate mb-1" 
                                         style="background: rgba(234, 88, 12, 0.12); color: #ea580c; font-size: 11px; max-width: 220px;" 
                                         x-text="selectedFileName || getFileName(brand.logo_url) || 'Logo Aktif'"></div>
                                    <div style="font-size: 11px; color: var(--clr-primary); font-weight: 600;">Klik untuk mengganti gambar</div>
                                </div>
                            </template>

                            <!-- Default Dropzone State -->
                            <template x-if="!logoPreview && !isImageLogo(brand.logo_url)">
                                <div class="py-2">
                                    <div style="font-size: 36px; margin-bottom: 6px;">☁️</div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--clr-primary);">Klik atau drag & drop berkas logo</div>
                                    <div style="font-size: 11px; color: var(--txt-muted); margin-top: 4px;">PNG, JPG, SVG, WEBP (Latar transparan disarankan)</div>
                                </div>
                            </template>
                        </label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2 mt-3" x-show="logoPreview">
                        <button type="button" class="btn btn-sm btn-outline-danger flex-fill fw-bold" 
                                style="font-size: 12px; border-radius: 10px; padding: 8px 14px;" 
                                @click="clearFile()">
                            ✖ Batal Upload
                        </button>
                        <button type="button" class="btn-submit-glowing flex-fill" 
                                :disabled="saving"
                                @click="save()">
                            <span x-show="!saving">💾 Simpan Logo</span>
                            <span x-show="saving">⏳ Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

<style>
.btn-submit-glowing {
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    color: #ffffff !important;
    border: 1px solid transparent;
    font-weight: 700;
    font-size: 12px;
    padding: 8px 16px;
    border-radius: 10px;
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.45);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
}
.btn-submit-glowing:hover {
    background: rgba(251, 146, 60, 0.16) !important;
    color: #c2410c !important;
    border-color: rgba(234, 88, 12, 0.35) !important;
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.12) !important;
    transform: translateY(-1px);
}
.btn-submit-glowing:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

        <!-- Right Column: Settings Form -->
        <div class="col-12 col-lg-7 col-xl-8">
            <div class="card p-4 shadow-sm" style="border-radius: 16px; background: var(--bg-surface); border: 1px solid var(--border);">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2 text-dark">
                    <span>🏢</span> Pengaturan Brand & Identitas HQ
                </h5>

                <form @submit.prevent="save()">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Brand / Outlet <span class="text-danger">*</span></label>
                            <input x-model="brand.brand_name" class="form-control form-input" placeholder="Ratio Coffee" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tagline Perusahaan</label>
                            <input x-model="brand.tagline" class="form-control form-input" placeholder="ERP Control Center" />
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">⏰ Jam Buka Operasional</label>
                            <input x-model="brand.operating_hours_open" type="time" class="form-control form-input" />
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">⏰ Jam Tutup Operasional</label>
                            <input x-model="brand.operating_hours_close" type="time" class="form-control form-input" />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">📍 Alamat Lengkap Outlet</label>
                        <textarea x-model="brand.address" class="form-control form-input" rows="3" placeholder="Jl. Boulevard Utama No. 1, Jakarta..."></textarea>
                    </div>

                    <!-- Status Alert Banner -->
                    <div x-show="saveStatus" class="mb-4" x-cloak>
                        <div :class="saveStatus === 'success' ? 'alert alert-success' : 'alert alert-danger'"
                             class="d-flex align-items-center gap-2 m-0 border-0 fw-semibold"
                             style="border-radius: 10px; font-size: 13px;"
                             x-text="saveMsg"></div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2" :disabled="saving" style="border-radius: 10px;">
                            <span x-show="saving">⏳ Menyimpan...</span>
                            <span x-show="!saving">💾 Simpan Perubahan Identitas HQ</span>
                        </button>
                        <button type="button" class="btn btn-secondary px-3 py-2.5 fw-semibold" @click="load()" style="border-radius: 10px;">↺ Reset Form</button>
                    </div>
                </form>
            </div>

            <!-- Current Brand Info Summary Card -->
            <div class="card p-4 mt-4 shadow-sm" style="border-radius: 16px; background: var(--bg-surface); border: 1px solid var(--border);">
                <h5 class="fw-bold mb-3 text-dark">📋 Ringkasan Identitas Terdaftar</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background: var(--bg-base, #f8fafc); border: 1px solid var(--border);">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 10.5px; letter-spacing: 0.5px;">Nama Brand HQ</div>
                            <div class="fw-bold text-dark" style="font-size: 14.5px;" x-text="brand.brand_name || '-'"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background: var(--bg-base, #f8fafc); border: 1px solid var(--border);">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 10.5px; letter-spacing: 0.5px;">Tagline Slogan</div>
                            <div class="fw-bold text-dark" style="font-size: 14.5px;" x-text="brand.tagline || '-'"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background: var(--bg-base, #f8fafc); border: 1px solid var(--border);">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 10.5px; letter-spacing: 0.5px;">Jam Operasional</div>
                            <div class="fw-bold text-dark" style="font-size: 14.5px;" x-text="(brand.operating_hours_open || '10:00') + ' – ' + (brand.operating_hours_close || '22:00') + ' WIB'"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background: var(--bg-base, #f8fafc); border: 1px solid var(--border);">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 10.5px; letter-spacing: 0.5px;">Lokasi Logo URL</div>
                            <div class="fw-bold text-primary text-truncate" style="font-size: 12.5px;" x-text="brand.logo_url || 'Belum ada logo'"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Icon Modul ERP Card -->
            <div class="card p-4 mt-4 shadow-sm" style="border-radius: 16px; background: var(--bg-surface); border: 1px solid var(--border);">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                            <span>🎨</span> Customization Icon Modul ERP
                        </h5>
                        <p class="text-muted small m-0 mt-1" style="font-size: 12px;">Sesuaikan ikon emoji / simbol pada masing-masing modul di sidebar secara real-time.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold" style="border-radius: 8px; font-size: 12px;" @click="resetModuleIcons()">
                            ↺ Reset Icon Default
                        </button>
                        <button type="button" class="btn-submit-glowing" :disabled="savingIcons" @click="saveModuleIcons()">
                            <span x-show="!savingIcons">💾 Simpan Custom Icon</span>
                            <span x-show="savingIcons">⏳ Menyimpan...</span>
                        </button>
                    </div>
                </div>

                <!-- Quick Emoji Palette Picker -->
                <div class="p-3 mb-4 rounded-3" style="background: var(--bg-base); border: 1px solid var(--border);">
                    <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">💡 Palet Emoji Pilihan Cepat (Pilih input modul lalu klik ikon di bawah):</div>
                    <div class="d-flex flex-wrap gap-1.5 align-items-center">
                        <template x-for="emoji in emojiPalette" :key="emoji">
                            <button type="button" class="btn btn-sm border-0 p-1.5 rounded-2 shadow-xs" 
                                    style="font-size: 18px; background: var(--bg-surface); transition: transform 0.15s ease;"
                                    @click="selectEmoji(emoji)"
                                    :title="'Gunakan ' + emoji">
                                <span x-text="emoji"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Module Items Grid -->
                <div class="row g-3">
                    <template x-for="mod in moduleList" :key="mod.key">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="p-3 rounded-3 d-flex align-items-center justify-content-between gap-2" 
                                 :style="activeKey === mod.key ? 'border-color: var(--clr-primary) !important; background: rgba(234, 88, 12, 0.04);' : ''"
                                 style="background: var(--bg-base); border: 1.5px solid var(--border); transition: all 0.2s ease;">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <div class="d-flex align-items-center justify-content-center rounded-2 shadow-xs" 
                                         style="width: 38px; height: 38px; background: var(--bg-surface); border: 1px solid var(--border); font-size: 20px; flex-shrink: 0;">
                                        <span x-text="moduleIcons[mod.key] || mod.defaultIcon"></span>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 13px;" x-text="mod.label"></div>
                                        <div class="text-muted text-truncate" style="font-size: 10.5px;" x-text="mod.category"></div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-1" style="width: 75px;">
                                    <input type="text" class="form-control form-control-sm text-center fw-bold form-input" 
                                           style="font-size: 16px; padding: 4px 6px; border-radius: 8px;"
                                           x-model="moduleIcons[mod.key]" 
                                           @focus="activeKey = mod.key"
                                           :placeholder="mod.defaultIcon" />
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function hqControlApp() {
    return {
        saving: false,
        savingIcons: false,
        imgErr: false,
        activeKey: 'dashboard',
        emojiPalette: ['📊', '💳', '⏱️', '📦', '🏢', '🍵', '🔬', '💰', '💸', '📈', '💼', '🏛️', '⚙️', '👥', '🛡️', '📋', '🚪', '☕', '🍹', '🛍️', '🛒', '📟', '⚡', '🔮', '🎯', '🏪', '🔥', '💎', '🚀', '🌟'],
        defaultModuleIcons: {
            dashboard: '📊',
            pos: '💳',
            queue: '⏱️',
            inventory: '📦',
            suppliers: '🏢',
            products: '🍵',
            research: '🔬',
            finance: '💰',
            expenses: '💸',
            reports: '📈',
            'revenue-sharing': '💼',
            tax: '🏛️',
            'admin-fee': '⚙️',
            users: '👥',
            security: '🛡️',
            hq: '🏢',
            logs: '📋',
            logout: '🚪'
        },
        moduleIcons: {
            dashboard: '📊',
            pos: '💳',
            queue: '⏱️',
            inventory: '📦',
            suppliers: '🏢',
            products: '🍵',
            research: '🔬',
            finance: '💰',
            expenses: '💸',
            reports: '📈',
            'revenue-sharing': '💼',
            tax: '🏛️',
            'admin-fee': '⚙️',
            users: '👥',
            security: '🛡️',
            hq: '🏢',
            logs: '📋',
            logout: '🚪'
        },
        moduleList: [
            { key: 'dashboard', label: 'Dashboard CEO', category: 'Main Navigation', defaultIcon: '📊' },
            { key: 'pos', label: 'POS Kasir', category: 'Operations', defaultIcon: '💳' },
            { key: 'queue', label: 'Antrean Pesanan (KDS)', category: 'Operations', defaultIcon: '⏱️' },
            { key: 'inventory', label: 'Inventory & Stok', category: 'Operations', defaultIcon: '📦' },
            { key: 'suppliers', label: 'Master Supplier', category: 'Operations', defaultIcon: '🏢' },
            { key: 'products', label: 'Produk & Resep', category: 'Operations', defaultIcon: '🍵' },
            { key: 'research', label: 'Research Product (R&D)', category: 'Operations', defaultIcon: '🔬' },
            { key: 'finance', label: 'Keuangan & Cash Flow', category: 'Finance', defaultIcon: '💰' },
            { key: 'expenses', label: 'Expense & Pembayaran', category: 'Finance', defaultIcon: '💸' },
            { key: 'reports', label: 'Laporan Bisnis', category: 'Finance', defaultIcon: '📈' },
            { key: 'revenue-sharing', label: 'Revenue Sharing', category: 'Finance', defaultIcon: '💼' },
            { key: 'tax', label: 'Pengaturan Pajak', category: 'Finance', defaultIcon: '🏛️' },
            { key: 'admin-fee', label: 'Pengaturan Biaya Admin', category: 'Finance', defaultIcon: '⚙️' },
            { key: 'users', label: 'Manajemen User', category: 'HR & Security', defaultIcon: '👥' },
            { key: 'security', label: 'Keamanan & MFA', category: 'HR & Security', defaultIcon: '🛡️' },
            { key: 'hq', label: 'HQ Control Center', category: 'HR & Security', defaultIcon: '🏢' },
            { key: 'logs', label: 'Activity Log', category: 'HR & Security', defaultIcon: '📋' },
            { key: 'logout', label: 'Keluar System', category: 'System', defaultIcon: '🚪' }
        ],
        brand: {
            brand_name: 'Ratio Coffee',
            tagline: 'ERP Control Center',
            operating_hours_open: '10:00',
            operating_hours_close: '22:00',
            address: '',
            logo_url: ''
        },
        selectedFile: null,
        selectedFileName: '',
        logoPreview: null,
        saveStatus: null,
        saveMsg: '',

        async init() { 
            const token = localStorage.getItem('erp_token');
            if (token) {
                axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
            }
            await this.load(); 
        },

        async load() {
            try {
                const r = await axios.get('/api/v1/brand-settings');
                const d = r.data?.data || r.data || {};
                this.brand = {
                    brand_name: d.brand_name || d.name || 'Ratio Coffee',
                    tagline: d.brand_tagline || d.tagline || 'ERP Control Center',
                    operating_hours_open: d.operating_hours_open || '10:00',
                    operating_hours_close: d.operating_hours_close || '22:00',
                    address: d.address || '',
                    logo_url: d.brand_logo || d.logo_url || d.logo || ''
                };
                if (d.module_icons) {
                    try {
                        const parsed = typeof d.module_icons === 'string' ? JSON.parse(d.module_icons) : d.module_icons;
                        this.moduleIcons = { ...this.defaultModuleIcons, ...parsed };
                    } catch(err) {}
                }
                this.imgErr = false;
            } catch(e) { 
                console.error('Load brand error:', e); 
            }
        },

        selectEmoji(emoji) {
            if (this.activeKey && this.moduleIcons[this.activeKey] !== undefined) {
                this.moduleIcons[this.activeKey] = emoji;
            }
        },

        resetModuleIcons() {
            this.moduleIcons = JSON.parse(JSON.stringify(this.defaultModuleIcons));
            Swal.fire({ icon: 'info', title: 'Reset Icon', text: 'Ikon modul dikembalikan ke versi default.', timer: 1500, showConfirmButton: false });
        },

        async saveModuleIcons() {
            this.savingIcons = true;
            try {
                const token = localStorage.getItem('erp_token');
                const reqHeaders = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                };
                if (token) {
                    reqHeaders['Authorization'] = 'Bearer ' + token;
                }
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (csrfToken) {
                    reqHeaders['X-CSRF-TOKEN'] = csrfToken;
                }

                const res = await axios.post('/api/v1/brand-settings', {
                    module_icons: JSON.stringify(this.moduleIcons)
                }, { headers: reqHeaders });
                const s = res.data?.settings || res.data || {};

                // Dispatch global event for instant sidebar update across layout
                window.dispatchEvent(new CustomEvent('brand-updated', { detail: s }));

                Swal.fire({ icon: 'success', title: 'Disimpan!', text: 'Custom icon modul berhasil diperbarui.', timer: 2000, showConfirmButton: false });
            } catch(e) {
                console.error('Save icons error:', e);
                let msg = 'Gagal memperbarui icon modul.';
                if (e.response?.data) {
                    if (e.response.data.errors) {
                        msg = Object.values(e.response.data.errors).flat().join(' ');
                    } else if (e.response.data.message) {
                        msg = e.response.data.message;
                    }
                } else if (e.message) {
                    msg = e.message;
                }
                Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', text: msg });
            } finally {
                this.savingIcons = false;
            }
        },

        isImageLogo(v) {
            if (!v) return false;
            if (v.startsWith('data:image') || v.startsWith('http://') || v.startsWith('https://') || v.startsWith('/')) return true;
            return /\.(png|jpg|jpeg|gif|svg|webp)($|\?)/i.test(v) || v.includes('uploads/') || v.includes('storage/');
        },

        formatLogoUrl(v) {
            if (!v) return '';
            if (v.startsWith('data:image') || v.startsWith('http://') || v.startsWith('https://') || v.startsWith('/')) return v;
            return '/' + v;
        },

        getFileName(url) {
            if (!url) return '';
            return url.split('/').pop().split('?')[0];
        },

        onFileSelected(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({ icon:'error', title:'File Terlalu Besar', text:'Ukuran maksimal 10MB.' });
                return;
            }
            this.selectedFile = file;
            this.selectedFileName = file.name;
            this.imgErr = false;
            const reader = new FileReader();
            reader.onload = (ev) => { this.logoPreview = ev.target.result; };
            reader.readAsDataURL(file);
        },

        onFileDrop(e) {
            const file = e.dataTransfer.files?.[0];
            if (file) {
                const fakeEvent = { target: { files: [file] } };
                this.onFileSelected(fakeEvent);
            }
        },

        clearFile() {
            this.selectedFile = null;
            this.selectedFileName = '';
            this.logoPreview = null;
            this.imgErr = false;
            const inp = document.getElementById('logoFileInput');
            if (inp) inp.value = '';
        },

        async save() {
            if (!this.brand.brand_name?.trim()) {
                Swal.fire({ icon:'warning', title:'Form Tidak Lengkap', text:'Nama brand tidak boleh kosong!' });
                return;
            }

            this.saving = true;
            this.saveStatus = null;

            try {
                const formData = new FormData();
                formData.append('brand_name', this.brand.brand_name);
                formData.append('brand_tagline', this.brand.tagline || '');
                formData.append('operating_hours_open', this.brand.operating_hours_open || '');
                formData.append('operating_hours_close', this.brand.operating_hours_close || '');
                formData.append('module_icons', JSON.stringify(this.moduleIcons));
                if (this.brand.address) {
                    formData.append('address', this.brand.address);
                }

                if (this.selectedFile) {
                    formData.append('brand_logo_file', this.selectedFile);
                } else {
                    formData.append('brand_logo', this.brand.logo_url || '');
                }

                const token = localStorage.getItem('erp_token');
                const reqHeaders = {};
                if (token) {
                    reqHeaders['Authorization'] = 'Bearer ' + token;
                }

                const res = await axios.post('/api/v1/brand-settings', formData, { headers: reqHeaders });
                const s = res.data?.settings || res.data || {};
                const newLogo = s.brand_logo || s.logo_url || s.logo || this.brand.logo_url;

                this.brand.brand_name            = s.brand_name || s.name || this.brand.brand_name;
                this.brand.tagline               = s.brand_tagline || s.tagline || this.brand.tagline;
                this.brand.operating_hours_open  = s.operating_hours_open || this.brand.operating_hours_open;
                this.brand.operating_hours_close = s.operating_hours_close || this.brand.operating_hours_close;
                this.brand.address               = s.address ?? this.brand.address;
                this.brand.logo_url              = newLogo;

                this.clearFile();
                this.imgErr = false;

                // Dispatch global event for ERP layout reactivity
                window.dispatchEvent(new CustomEvent('brand-updated', { detail: s }));

                // Dynamically refresh sidebar brand name, tagline, and logo icon
                const sidebarImg = document.querySelector('.sidebar-logo-icon img');
                const sidebarSpan = document.querySelector('.sidebar-logo-icon span');
                const sidebarTitle = document.querySelector('.sidebar-logo-text div:first-child');
                const sidebarSub = document.querySelector('.sidebar-logo-text .sidebar-logo-sub');

                if (sidebarTitle) sidebarTitle.textContent = this.brand.brand_name;
                if (sidebarSub) sidebarSub.textContent = this.brand.tagline;

                if (newLogo && (newLogo.startsWith('/') || newLogo.startsWith('http') || newLogo.startsWith('data:') || newLogo.includes('storage/') || newLogo.includes('uploads/'))) {
                    if (sidebarImg) {
                        sidebarImg.src = this.formatLogoUrl(newLogo) + '?t=' + Date.now();
                        sidebarImg.style.display = 'block';
                    }
                    if (sidebarSpan) sidebarSpan.style.display = 'none';
                }

                this.saveStatus = 'success';
                this.saveMsg = '✅ Identitas brand & logo HQ berhasil diperbarui!';
                Swal.fire({ icon:'success', title:'Disimpan!', text:'Identitas brand HQ berhasil diperbarui.', timer:2500, showConfirmButton:false });
                setTimeout(() => { this.saveStatus = null; }, 4000);

            } catch(e) {
                this.saveStatus = 'error';
                let msg = 'Gagal menyimpan brand setting.';
                if (e.response?.data) {
                    if (e.response.data.errors) {
                        msg = Object.values(e.response.data.errors).flat().join(' ');
                    } else if (e.response.data.message) {
                        msg = e.response.data.message;
                    }
                } else if (e.message) {
                    msg = e.message;
                }
                this.saveMsg = '❌ ' + msg;
                Swal.fire({ icon:'error', title:'Gagal Menyimpan', text:msg });
                setTimeout(() => { this.saveStatus = null; }, 5000);
            } finally {
                this.saving = false;
            }
        }
    }
}
</script>
@endpush
