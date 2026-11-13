@extends('layouts.erp')
@section('title', 'Revenue Sharing Operator – Ratio Coffee ERP')

@section('page-content')
<div x-data="revSharingApp()" x-init="init()" x-cloak>
    <!-- Summary Banner -->
    <div class="card mb-4" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;padding:24px;border-radius:16px">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="small fw-bold text-muted mb-1" style="letter-spacing:1px">💼 TOTAL AKUMULASI REVENUE SHARING</div>
                <div class="display-6 fw-bold text-success" x-text="'Rp ' + formatNumber(data.total_sharing || 0)"></div>
                <div class="small text-muted mt-1">
                    Dari total omzet penjualan <strong class="text-white" x-text="'Rp ' + formatNumber(data.total_revenue || 0)"></strong> dengan skema bagi hasil <strong class="text-warning" x-text="(data.rate || 0) + '%'"></strong>
                </div>
            </div>
            <div class="d-flex gap-4 p-3 rounded" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)">
                <div class="text-center">
                    <div class="small text-muted fw-bold">BULAN INI</div>
                    <div class="fs-4 fw-bold text-info" x-text="'Rp ' + formatNumber(data.current_month?.sharing || 0)"></div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,0.1)"></div>
                <div class="text-center">
                    <div class="small text-muted fw-bold">MENUNGGU VERIFIKASI</div>
                    <div class="fs-4 fw-bold text-warning" x-text="pendingVerifications.length + ' Tx'"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Verifications -->
    <div x-show="pendingVerifications.length" class="card mb-4 p-0 overflow-hidden" style="border:2px solid #7c3aed">
        <div class="p-3 text-white d-flex justify-content-between align-items-center" style="background:linear-gradient(90deg,#7c3aed,#6d28d9)">
            <h5 class="fw-bold m-0">⚡ Verifikasi Pembayaran Revenue Sharing dari CEO (<span x-text="pendingVerifications.length"></span>)</h5>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tgl Dibayar</th>
                        <th>Nama Pembayaran</th>
                        <th>Rekening Tujuan</th>
                        <th>Nominal</th>
                        <th>Catatan / Ref</th>
                        <th>Status</th>
                        <th class="text-end">Aksi Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in pendingVerifications" :key="item.id">
                        <tr>
                            <td x-text="formatDate(item.paid_at || item.created_at, 'full')"></td>
                            <td class="fw-bold" x-text="item.expense_name"></td>
                            <td>
                                <div class="fw-bold" x-text="'🏦 ' + (item.bank_name || '-')"></div>
                                <small class="text-muted" x-text="item.bank_account + ' (a.n. ' + item.account_holder + ')'"></small>
                            </td>
                            <td class="fw-bold text-success fs-6" x-text="'Rp ' + formatNumber(item.amount)"></td>
                            <td class="small text-muted" x-text="item.proof || '-'"></td>
                            <td>
                                <span :class="['badge', item.operator_status === 'verified' ? 'badge-success' : 'badge-warning']"
                                      x-text="item.operator_status === 'verified' ? '✅ Terverifikasi' : '⏳ Menunggu'"></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-success btn-sm" @click="verify(item, 'verified')">✅ Verifikasi</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Accumulation -->
    <div class="card p-0 overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold m-0">📊 Akumulasi Revenue Sharing Bulanan</h5>
            <span class="badge badge-info" x-text="'Skema Rate: ' + (data.rate || 0) + '%'"></span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Periode Bulan</th>
                        <th>Total Omzet Penjualan</th>
                        <th>Persentase Sharing</th>
                        <th>Hasil Sharing Operator</th>
                        <th>Status Kumulatif</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="5" class="text-center py-4">⏳ Memuat akumulasi revenue sharing...</td></tr>
                    </template>
                    <template x-if="!loading && (!data.monthly || data.monthly.length === 0)">
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data omzet bulanan.</td></tr>
                    </template>
                    <template x-for="m in data.monthly || []" :key="m.label">
                        <tr>
                            <td class="fw-bold" x-text="m.label"></td>
                            <td x-text="'Rp ' + formatNumber(m.revenue)"></td>
                            <td><span class="badge badge-warning" x-text="(data.rate || 0) + '%'"></span></td>
                            <td class="fw-bold text-success fs-6" x-text="'Rp ' + formatNumber(m.sharing)"></td>
                            <td>
                                <span :class="['badge', m.sharing > 0 ? 'badge-success' : 'badge-muted']"
                                      x-text="m.sharing > 0 ? 'Terakumulasi' : 'Belum Ada Transaksi'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function revSharingApp() {
    return {
        loading: false, verifying: false,
        data: { rate: 0, total_revenue: 0, total_sharing: 0, current_month: {}, monthly: [] },
        pendingVerifications: [],

        async init() { await this.load(); },

        async load() {
            this.loading = true;
            try {
                const [r1, r2] = await Promise.all([
                    axios.get('/api/v1/admin-fee/revenue-sharing'),
                    axios.get('/api/v1/admin-fee/pending-verifications')
                ]);
                this.data = r1.data ?? {};
                this.pendingVerifications = r2.data.data ?? r2.data ?? [];
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        async verify(item, status) {
            this.verifying = true;
            try {
                await axios.put(`/api/v1/admin-fee/verify-payment/${item.id}`, { operator_status: status });
                toast('Pembayaran berhasil diverifikasi!');
                await this.load();
            } catch(e) {
                toast('Gagal memverifikasi', 'error');
            } finally { this.verifying = false; }
        },

        formatNumber, formatDate
    }
}
</script>
@endpush
