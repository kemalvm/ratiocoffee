@extends('layouts.erp')
@section('page-content')
<div x-data="invIngred()" x-init="init()" x-cloak>
    <div x-show="loading" class="d-flex align-items-center gap-2 mb-3" style="color:var(--txt-muted)">
        <div class="spinner-border spinner-border-sm text-warning"></div><span>Memuat data...</span>
    </div>
    <div x-show="!loading" class="card p-4">
        <div class="alert alert-info">
            <strong>Modul ini sedang dalam migrasi ke Blade.</strong>
            Semua data API sudah terhubung dan tersedia.
            Gunakan DevTools → Console → <code>window.__data</code> untuk melihat data yang masuk.
        </div>
        <pre x-text="JSON.stringify(items.slice ? items.slice(0,3) : items, null, 2)" style="font-size:11px;max-height:300px;overflow:auto;background:var(--bg-muted);padding:12px;border-radius:8px"></pre>
    </div>
</div>
@endsection
@push('scripts')
<script>
function invIngred() {
    return {
        loading: true,
        items: [],
        async init() {
            try {
                const r = await axios.get('/api/v1/inventory/ingredients');
                this.items = r.data.data ?? r.data ?? [];
                window.__data = this.items;
            } catch(e) { console.error(e); } finally { this.loading = false; }
        }
    }
}
</script>
@endpush
