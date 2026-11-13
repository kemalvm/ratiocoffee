@extends('layouts.erp')
@section('title', 'Riwayat Transaksi POS – Ratio Coffee ERP')

@section('page-content')
<div x-data="posOrdersApp()" x-init="init()" x-cloak>
    <div class="card p-3 mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <input x-model="search" @input.debounce.300ms="load()" class="form-input" placeholder="🔍 Cari nomor order / kasir..." />
            </div>
            <div class="col-md-3">
                <select x-model="filterStatus" @change="load()" class="form-input">
                    <option value="">Semua Status</option>
                    <option value="completed">Selesai</option>
                    <option value="preparing">Proses</option>
                    <option value="cancelled">Batal</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Waktu Transaksi</th>
                        <th>Tipe / Meja</th>
                        <th>Kasir</th>
                        <th>Metode Bayar</th>
                        <th>Total (Rp)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="7" class="text-center py-4">⏳ Memuat riwayat transaksi...</td></tr>
                    </template>
                    <template x-if="!loading && orders.length === 0">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat transaksi.</td></tr>
                    </template>
                    <template x-for="ord in orders" :key="ord.id">
                        <tr>
                            <td><span class="order-badge" x-text="'#' + ord.order_number"></span></td>
                            <td x-text="formatDate(ord.created_at, 'full')"></td>
                            <td><span class="badge badge-info" x-text="ord.order_type === 'takeaway' ? '🛍 Takeaway' : '🍽 Dine In (' + (ord.table_number||'-') + ')'"></span></td>
                            <td x-text="ord.cashier?.name || 'Kasir'"></td>
                            <td><span class="pay-badge" x-text="getPaymentIcon(ord.payment_method) + ' ' + (ord.payment_method||'CASH').toUpperCase()"></span></td>
                            <td class="fw-bold text-primary" x-text="'Rp ' + formatNumber(ord.total_amount)"></td>
                            <td>
                                <span :class="['badge', ord.status==='completed'?'badge-success':'badge-warning']"
                                      x-text="ord.status==='completed'?'Selesai':ord.status"></span>
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
function posOrdersApp() {
    return {
        loading: false, orders: [], search: '', filterStatus: '',

        async init() { await this.load(); },

        async load() {
            this.loading = true;
            try {
                const r = await axios.get('/api/v1/pos/orders', { params: { search: this.search, status: this.filterStatus } });
                this.orders = r.data.data ?? r.data ?? [];
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        formatDate, formatNumber, getPaymentIcon
    }
}
</script>
@endpush
