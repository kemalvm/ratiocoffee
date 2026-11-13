@extends('layouts.erp')
@section('title', 'Pengaturan Pajak & Service Charge – Ratio Coffee ERP')

@section('page-content')
<div x-data="taxSettingsApp()" x-init="init()" x-cloak class="container-fluid p-0">
    
    <!-- Top Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-bold m-0 text-primary d-flex align-items-center gap-2" style="font-size: 1.4rem;">
                <span>🏛️</span> Pengaturan Pajak & Service Charge
            </h3>
            <p class="text-muted small m-0 mt-1">Konfigurasi persentase pajak restoran (PB1), service charge, dan pembebanan HPP toko</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-success px-3 py-2" style="font-size:12px;">
                Status: <strong>Dibebankan ke HPP / Margin Toko (Bebas Biaya Pelanggan)</strong>
            </span>
        </div>
    </div>

    <!-- Business Policy Alert Banner -->
    <div class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-center gap-3" style="background: rgba(59, 130, 246, 0.08); border-left: 4px solid #3b82f6 !important;">
        <span class="fs-4">💡</span>
        <div class="small text-muted">
            <strong>Standar Kebijakan Ratio Coffee:</strong> Seluruh biaya Pajak Resto (PB1), Service Charge, dan Biaya Admin dipotong langsung dari <strong>HPP / Margin Operasional Toko</strong>. Pelanggan kasir membayar sesuai harga pas di menu tanpa biaya tambahan tersembunyi.
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
        <!-- Settings Form Column -->
        <div class="col-12 col-lg-7">
            <form @submit.prevent="saveTax()">
                <!-- Section 1: Pajak Restoran (PB1) -->
                <div class="card p-4 border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-5">🧾</span>
                            <div>
                                <h6 class="fw-bold m-0">Pajak Restoran / PB1</h6>
                                <span class="text-muted small">Potongan pajak wajib pemerintah (Alokasi HPP toko)</span>
                            </div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input type="checkbox" class="form-check-input" id="taxCheck" x-model="tax.is_enabled" style="cursor:pointer; width: 45px; height: 24px;">
                        </div>
                    </div>

                    <div x-show="tax.is_enabled" x-transition>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Nama Pajak <span class="text-danger">*</span></label>
                                <input x-model="tax.tax_name" class="form-input" placeholder="Pajak Resto (PB1)" required />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Persentase Pajak (%) <span class="text-danger">*</span></label>
                                <input x-model.number="tax.tax_rate" type="number" step="0.1" min="0" max="100" class="form-input" placeholder="10" required />
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold">Metode Pembebanan Pajak</label>
                            <select x-model="tax.tax_type" class="form-input" disabled style="background-color: var(--bg-muted, #f1f5f9); cursor: not-allowed;">
                                <option value="charged_to_hpp">⭐ Dibebankan Ke HPP / Margin Toko (Bebas Pajak Pelanggan - Standar Ratio Coffee)</option>
                            </select>
                        </div>

                        <!-- Info Alert -->
                        <div class="mt-3 p-3 rounded" style="background: rgba(16, 185, 129, 0.08); border-left: 3px solid #10b981;">
                            <div class="small text-muted">
                                💡 <strong>HPP / Margin Charge (Bebas Pajak Pelanggan):</strong> Pelanggan membayar harga pas menu di kasir (Struk Bebas Pajak). Nilai pajak <span class="fw-bold text-success" x-text="(tax.tax_rate||0)+'%'"></span> secara otomatis dipotong dari alokasi HPP / margin operasional toko.
                            </div>
                        </div>
                    </div>

                    <div x-show="!tax.is_enabled" class="text-muted small py-2">
                        ⚠️ Status Pajak Resto saat ini <strong>NONAKTIF</strong>. Transaksi di kasir POS tidak akan mengenakan pajak PB1.
                    </div>
                </div>

                <!-- Section 2: Service Charge -->
                <div class="card p-4 border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-5">🍽️</span>
                            <div>
                                <h6 class="fw-bold m-0">Service Charge</h6>
                                <span class="text-muted small">Biaya pelayanan operasional & service staf (Dialokasikan ke HPP)</span>
                            </div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input type="checkbox" class="form-check-input" id="scCheck" x-model="tax.is_service_charge_enabled" style="cursor:pointer; width: 45px; height: 24px;">
                        </div>
                    </div>

                    <div x-show="tax.is_service_charge_enabled" x-transition>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Nama Biaya</label>
                                <input x-model="tax.service_charge_name" class="form-input" placeholder="Service Charge" />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Rate (%)</label>
                                <input x-model.number="tax.service_charge_rate" type="number" step="0.1" min="0" max="100" class="form-input" placeholder="2" />
                            </div>
                        </div>
                    </div>

                    <div x-show="!tax.is_service_charge_enabled" class="text-muted small py-2">
                        ℹ️ Service charge nonaktif. Pelanggan tidak dikenakan biaya pelayanan tambahan.
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-start">
                    <button type="submit" class="btn btn-primary px-4 py-2" :disabled="saving">
                        <span x-show="saving">⏳ Menyimpan...</span>
                        <span x-show="!saving">💾 Simpan Pengaturan Pajak</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Live POS Struk Simulation Preview Column -->
        <div class="col-12 col-lg-5">
            <div class="card p-4 border-0 shadow-sm h-100" style="background: var(--bg-surface);">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                    <h6 class="fw-bold m-0 d-flex align-items-center gap-2">
                        <span>🧾</span> Simulasi Kalkulasi Struk POS
                    </h6>
                    <span class="badge badge-success">Standar Ratio Coffee</span>
                </div>

                <!-- Receipt Box -->
                <div class="p-3 rounded border mb-4" style="background: var(--bg-muted, #f8fafc); font-family: 'Courier New', Courier, monospace;">
                    <div class="text-center fw-bold fs-6 mb-1">RATIO COFFEE POS</div>
                    <div class="text-center text-muted small mb-3 border-bottom pb-2">SIMULASI TRANSAKSI KASIR</div>

                    <div class="d-flex justify-content-between small mb-1">
                        <span>2x Kopi Susu Aren</span>
                        <span>Rp 50.000</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-3 text-muted">
                        <span>1x Chocolate Toast</span>
                        <span>Rp 25.000</span>
                    </div>

                    <div class="border-top pt-2 d-flex justify-content-between small fw-bold">
                        <span>Subtotal Belanja:</span>
                        <span>Rp 75.000</span>
                    </div>

                    <!-- Service Charge Line -->
                    <div class="d-flex justify-content-between small text-muted mt-1" x-show="tax.is_service_charge_enabled">
                        <span x-text="(tax.service_charge_name || 'Service Charge') + ' (' + (tax.service_charge_rate||0) + '%):'"></span>
                        <span class="text-success fw-semibold">Termasuk HPP Toko (Rp 0)</span>
                    </div>

                    <!-- Tax Line -->
                    <div class="d-flex justify-content-between small text-muted mt-1" x-show="tax.is_enabled">
                        <span x-text="(tax.tax_name || 'Pajak Resto') + ' (' + (tax.tax_rate||0) + '%):'"></span>
                        <span class="text-success fw-semibold">Termasuk HPP / Margin Toko (Rp 0)</span>
                    </div>
                    <div class="d-flex justify-content-between small text-success mt-1" x-show="!tax.is_enabled">
                        <span>Status Pajak:</span>
                        <span>Bebas Pajak</span>
                    </div>

                    <!-- Total Line -->
                    <div class="border-top border-dark border-dashed pt-2 mt-2 d-flex justify-content-between fw-bold fs-6 text-primary">
                        <span>TOTAL AKHIR PELANGGAN:</span>
                        <span x-text="'Rp ' + simTotal().toLocaleString('id-ID')"></span>
                    </div>
                </div>

                <div>
                    <div class="fw-semibold small text-muted mb-2">METODE AKTIF RATIO COFFEE:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge badge-success" x-text="tax.is_enabled ? 'PB1: ' + (tax.tax_rate||0) + '% (Dibebankan HPP)' : 'PB1: Disabled'"></span>
                        <span class="badge badge-warning" x-text="tax.is_service_charge_enabled ? 'SC: ' + (tax.service_charge_rate||0) + '% (Dibebankan HPP)' : 'SC: Disabled'"></span>
                        <span class="badge badge-muted">TYPE: CHARGED_TO_HPP</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function taxSettingsApp() {
    return {
        saving: false,
        tax: {
            tax_name: 'Pajak Resto (PB1)', tax_rate: 10, is_enabled: true, tax_type: 'charged_to_hpp',
            service_charge_name: 'Service Charge', service_charge_rate: 0, is_service_charge_enabled: false
        },

        async init() {
            try {
                const r = await axios.get('/api/v1/tax-settings');
                const d = r.data?.data || r.data;
                if (d) this.tax = {
                    ...this.tax, ...d,
                    tax_type: 'charged_to_hpp', // Force charged_to_hpp
                    is_enabled: Boolean(d.is_enabled),
                    is_service_charge_enabled: Boolean(d.is_service_charge_enabled),
                    tax_rate: parseFloat(d.tax_rate)||0,
                    service_charge_rate: parseFloat(d.service_charge_rate)||0
                };
            } catch(e) {}
        },

        simTotal() {
            // Standard Ratio Coffee rule: Tax & Service Charge are 100% absorbed into HPP, customer pays subtotal Rp 75.000
            return 75000;
        },

        async saveTax() {
            this.saving = true;
            try {
                this.tax.tax_type = 'charged_to_hpp';
                await axios.post('/api/v1/tax-settings', this.tax);
                toast('Pengaturan pajak berhasil disimpan! (Metode: Dibebankan ke HPP/Margin Toko)');
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal menyimpan pajak', 'error');
            } finally { this.saving = false; }
        }
    }
}
</script>
@endpush
