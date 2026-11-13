@extends('layouts.erp')
@section('title', 'Activity Logs Sistem – Ratio Coffee ERP')

@section('page-content')
<div x-data="activityLogsApp()" x-init="init()" x-cloak>
    <div class="card p-3 mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <input x-model="search" @input.debounce.300ms="load()" class="form-input" placeholder="🔍 Cari aktivitas / nama user / deskripsi..." />
            </div>
            <div class="col-md-6 text-end">
                <span class="text-muted small" x-text="'Total ' + logs.length + ' log aktivitas tercatat'"></span>
            </div>
        </div>
    </div>

    <div class="card p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu Log</th>
                        <th>User / Aktor</th>
                        <th>Modul</th>
                        <th>Aktivitas</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="5" class="text-center py-4">⏳ Memuat log aktivitas...</td></tr>
                    </template>
                    <template x-if="!loading && logs.length === 0">
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data log aktivitas.</td></tr>
                    </template>
                    <template x-for="l in logs" :key="l.id">
                        <tr>
                            <td x-text="formatDate(l.created_at, 'full')"></td>
                            <td>
                                <div class="fw-bold" x-text="l.user?.name || l.causer?.name || l.user_name || 'Sistem'"></div>
                                <small class="text-muted" x-text="l.role?.name ? ('Role: ' + l.role.name) : (l.user?.username || l.user?.email || '')"></small>
                            </td>
                            <td><span class="badge badge-info" x-text="l.module || l.log_name || 'Sistem'"></span></td>
                            <td x-text="l.description || l.action"></td>
                            <td><code x-text="l.ip_address || l.properties?.ip || '127.0.0.1'"></code></td>
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
function activityLogsApp() {
    return {
        loading: false, logs: [], search: '',

        async init() {
            const token = localStorage.getItem('erp_token');
            if (token) {
                axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
            }
            await this.load();
        },

        async load() {
            this.loading = true;
            try {
                const r = await axios.get('/api/v1/activity-logs', { params: { search: this.search } });
                const res = r.data;
                this.logs = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
            } catch(e) { console.error('Gagal memuat log aktivitas:', e); }
            finally { this.loading = false; }
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                return d.toLocaleString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch(e) {
                return dateStr;
            }
        }
    }
}
</script>
@endpush
