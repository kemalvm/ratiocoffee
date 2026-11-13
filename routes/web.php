<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes – Ratio Coffee ERP (Blade Migration, no Vite/Vue)
|--------------------------------------------------------------------------
*/

// ── Auth ──────────────────────────────────────────────────────────────
Route::get('/login', fn() => view('auth.login'))->name('login');
Route::get('/', fn() => redirect('/login'));

// ── Dashboard ─────────────────────────────────────────────────────────
Route::get('/dashboard', fn() => view('dashboard.index', [
    'pageTitle'    => 'Dashboard',
    'pageSubtitle' => 'Pusat kendali real-time operasional, finansial, inventory, dan transaksi Ratio Coffee ERP',
    'moduleLabel'  => 'Dashboard',
]));
Route::get('/my-dashboard', fn() => view('dashboard.employee', [
    'pageTitle'   => 'Dashboard Operasional Employee',
    'moduleLabel' => 'Dashboard',
]));

// ── POS ───────────────────────────────────────────────────────────────
Route::get('/pos/kasir', fn() => view('pos.kasir', [
    'pageTitle'   => 'POS Kasir (Live)',
    'moduleLabel' => 'POS',
]));
Route::get('/pos/queue', fn() => view('pos.queue', [
    'pageTitle'   => 'Antrean Pesanan (KDS)',
    'moduleLabel' => 'POS',
]));
Route::get('/pos/orders', fn() => view('pos.orders', [
    'pageTitle'   => 'Riwayat Transaksi POS',
    'moduleLabel' => 'POS',
]));

// ── Inventory ─────────────────────────────────────────────────────────
Route::get('/inventory', fn() => view('inventory.index', [
    'pageTitle'   => 'Manajemen Inventory & Gudang',
    'moduleLabel' => 'Inventory',
]));
Route::get('/inventory/ingredients', fn() => view('inventory.ingredients', [
    'pageTitle'   => 'Master Bahan Baku & Ingredients',
    'moduleLabel' => 'Inventory',
]));
Route::get('/inventory/stock-movements', fn() => view('inventory.stock-movements', [
    'pageTitle'   => 'Riwayat Mutasi Stok',
    'moduleLabel' => 'Inventory',
]));
Route::get('/suppliers', fn() => view('inventory.suppliers', [
    'pageTitle'   => 'Master Supplier',
    'moduleLabel' => 'Inventory',
]));

// ── Products ──────────────────────────────────────────────────────────
Route::get('/products', fn() => view('products.index', [
    'pageTitle'   => 'Produk & Resep Menu',
    'moduleLabel' => 'Produk',
]));
Route::get('/research-product', fn() => view('products.research', [
    'pageTitle'   => 'Research Product R&D',
    'moduleLabel' => 'Produk',
]));
Route::get('/operations/research-product', fn() => view('products.research', [
    'pageTitle'   => 'Research Product R&D',
    'moduleLabel' => 'Operations',
]));

// ── Finance ───────────────────────────────────────────────────────────
Route::get('/finance', fn() => view('finance.index', [
    'pageTitle'   => 'Keuangan & Cash Flow',
    'moduleLabel' => 'Finance',
]));
Route::get('/expenses', fn() => view('finance.expenses', [
    'pageTitle'   => 'Expense & Pembayaran',
    'moduleLabel' => 'Finance',
]));
Route::get('/reports', fn() => view('finance.reports', [
    'pageTitle'   => 'Laporan Bisnis',
    'moduleLabel' => 'Laporan',
]));
Route::get('/revenue-sharing', fn() => view('finance.revenue-sharing', [
    'pageTitle'   => 'Revenue Sharing',
    'moduleLabel' => 'Finance',
]));
Route::get('/admin-fee', fn() => view('finance.admin-fee', [
    'pageTitle'   => 'Pengaturan Biaya Admin',
    'moduleLabel' => 'Finance',
]));
Route::get('/tax-settings', fn() => view('finance.tax-settings', [
    'pageTitle'   => 'Pengaturan Pajak',
    'moduleLabel' => 'Finance',
]));

// ── HR & Settings ─────────────────────────────────────────────────────
Route::get('/users', fn() => view('hr.users', [
    'pageTitle'   => 'Manajemen User & Karyawan',
    'moduleLabel' => 'HR',
]));
Route::get('/security', fn() => view('hr.security', [
    'pageTitle'   => 'Keamanan & MFA',
    'moduleLabel' => 'HR',
]));
Route::get('/account-settings', fn() => view('hr.account-settings', [
    'pageTitle'   => 'Pengaturan Akun',
    'moduleLabel' => 'HR',
]));
Route::get('/hq-control-center', fn() => view('hr.hq-control-center', [
    'pageTitle'   => 'HQ Control Center',
    'moduleLabel' => 'HR',
]));
Route::get('/activity-logs', fn() => view('hr.activity-logs', [
    'pageTitle'   => 'Activity Log Sistem',
    'moduleLabel' => 'HR',
]));

// ── Setup Wizard ──────────────────────────────────────────────────────
Route::get('/setup', fn() => view('setup.wizard'));

// ── Fallback ──────────────────────────────────────────────────────────
Route::fallback(fn() => view('errors.404', ['pageTitle' => 'Halaman Tidak Ditemukan']));

