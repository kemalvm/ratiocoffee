@php
// Generic module stub – generates a working API-connected page for any module
// $moduleApi   = API endpoint prefix e.g. '/api/v1/inventory'
// $alpineComp  = Alpine component function name e.g. 'inventoryPage'
@endphp
@extends('layouts.erp')
@section('page-content')
<div x-data="{{ $alpineComp ?? 'modulePage' }}()" x-init="init()">
    <!-- Loading -->
    <div x-show="loading" class="d-flex align-items-center gap-2 mb-3" style="color:var(--txt-muted)">
        <div class="spinner-border spinner-border-sm text-warning"></div>
        <span>Memuat data...</span>
    </div>
    @yield('module-content')
</div>
@endsection
