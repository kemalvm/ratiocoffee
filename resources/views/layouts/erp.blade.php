@extends('layouts.app')
@section('title', $pageTitle ?? 'Ratio Coffee ERP')

@push('styles')
<link href="{{ asset('css/erp-sidebar.css') }}" rel="stylesheet" />
@endpush

@section('content')
<div class="erp-layout" x-data="erpLayout()" :class="{ 'sidebar-collapsed': collapsed, 'mobile-open': mobileOpen }" x-init="init()">

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div x-show="mobileOpen" x-cloak class="sidebar-backdrop" @click="mobileOpen = false"></div>

    <!-- ══ SIDEBAR ══════════════════════════════════════════════════════ -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <img x-show="isImageLogo(brand.logo) && !imgFailed" :src="formatLogoUrl(brand.logo)" x-on:error="imgFailed = true" alt="Logo" x-cloak />
                <span x-show="!isImageLogo(brand.logo) || imgFailed" x-cloak>☕</span>
            </div>
            <div class="sidebar-logo-text" x-show="isSidebarTextVisible()" x-cloak>
                <div x-text="brand.name || 'Ratio Coffee'"></div>
                <div class="sidebar-logo-sub" x-text="brand.tagline || 'ERP Control Center'"></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <!-- Dashboard -->
            <div class="sidebar-section-label" x-show="isSidebarTextVisible()">Dashboard</div>

            <a href="/dashboard" class="sidebar-item" :class="{active: isActive('/dashboard')}"
               x-show="canSee('dashboard')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('dashboard', '📊')">📊</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()"
                    x-text="roleDashLabel()"></span>
            </a>

            <!-- Operations -->
            <div class="sidebar-section-label" style="margin-top:14px"
                 x-show="isSidebarTextVisible() && canSee('operations')">Operations</div>

            <a href="/pos/kasir" class="sidebar-item" :class="{active: isActive('/pos/kasir')}"
               x-show="canSee('pos')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('pos', '💳')">💳</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">POS Kasir</span>
                <span class="sidebar-badge" style="background:var(--clr-primary)" x-show="isSidebarTextVisible()">Live</span>
            </a>
            <a href="/pos/queue" class="sidebar-item" :class="{active: isActive('/pos/queue')}"
               x-show="canSee('pos')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('queue', '⏱️')">⏱️</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Antrean Pesanan</span>
                <span class="sidebar-badge" style="background:#f59e0b" x-show="isSidebarTextVisible()">KDS</span>
            </a>
            <a href="/pos/orders" class="sidebar-item" :class="{active: isActive('/pos/orders')}"
               x-show="canSee('pos')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('orders', '📜')">📜</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Riwayat Transaksi</span>
            </a>
            <a href="/inventory" class="sidebar-item" :class="{active: isActive('/inventory')}"
               x-show="canSee('inventory')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('inventory', '📦')">📦</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Inventory</span>
            </a>
            <a href="/suppliers" class="sidebar-item" :class="{active: isActive('/suppliers')}"
               x-show="canSee('suppliers')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('suppliers', '🏢')">🏢</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Master Supplier</span>
            </a>
            <a href="/products" class="sidebar-item" :class="{active: isActive('/products')}"
               x-show="canSee('products')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('products', '🍵')">🍵</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Produk &amp; Resep</span>
            </a>
            <a href="/research-product" class="sidebar-item" :class="{active: isActive('/research-product')}"
               x-show="canSee('research')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('research', '🔬')">🔬</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Research Product</span>
                <span class="sidebar-badge" style="background:#8b5cf6" x-show="isSidebarTextVisible()">R&D</span>
            </a>

            <!-- Finance -->
            <div class="sidebar-section-label" style="margin-top:14px"
                 x-show="isSidebarTextVisible() && (canSee('finance') || canSee('reports'))">Finance</div>

            <a href="/finance" class="sidebar-item" :class="{active: isActive('/finance')}"
               x-show="canSee('finance')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('finance', '💰')">💰</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Keuangan &amp; Cash Flow</span>
            </a>
            <a href="/expenses" class="sidebar-item" :class="{active: isActive('/expenses')}"
               x-show="canSee('expenses')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('expenses', '💸')">💸</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Expense &amp; Pembayaran</span>
                <span class="sidebar-badge" style="background:#ef4444" x-show="isSidebarTextVisible()">Pengingat</span>
            </a>
            <a href="/reports" class="sidebar-item" :class="{active: isActive('/reports')}"
               x-show="canSee('reports')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('reports', '📈')">📈</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Laporan Bisnis</span>
            </a>
            <a href="/revenue-sharing" class="sidebar-item" :class="{active: isActive('/revenue-sharing')}"
               x-show="canSee('revenue-sharing')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('revenue-sharing', '💼')">💼</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Revenue Sharing</span>
                <span class="sidebar-badge" style="background:#10b981" x-show="isSidebarTextVisible()">OP</span>
            </a>
            <a href="/tax-settings" class="sidebar-item" :class="{active: isActive('/tax-settings')}"
               x-show="canSee('tax')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('tax', '🏛️')">🏛️</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Pengaturan Pajak</span>
                <span class="sidebar-badge" style="background:#ea580c" x-show="isSidebarTextVisible()">CEO</span>
            </a>
            <a href="/admin-fee" class="sidebar-item" :class="{active: isActive('/admin-fee')}"
               x-show="canSee('admin-fee')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('admin-fee', '⚙️')">⚙️</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Pengaturan Biaya Admin</span>
                <span class="sidebar-badge" style="background:#7c3aed" x-show="isSidebarTextVisible()">Rate</span>
            </a>

            <!-- HR & Security -->
            <div class="sidebar-section-label" style="margin-top:14px" x-show="isSidebarTextVisible()">HR &amp; Security</div>
            <a href="/users" class="sidebar-item" :class="{active: isActive('/users')}"
               x-show="canSee('hr')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('users', '👥')">👥</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Manajemen User</span>
            </a>
            <a href="/security" class="sidebar-item" :class="{active: isActive('/security')}" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('security', '🛡️')">🛡️</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Keamanan &amp; MFA</span>
                <span class="sidebar-badge" style="background:#10b981" x-show="isSidebarTextVisible()">MFA</span>
            </a>
            <a href="/hq-control-center" class="sidebar-item" :class="{active: isActive('/hq-control-center')}"
               x-show="canSee('hq')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('hq', '🏢')">🏢</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">HQ Control Center</span>
            </a>
            <a href="/activity-logs" class="sidebar-item" :class="{active: isActive('/activity-logs')}"
               x-show="canSee('logs')" @click="mobileOpen = false">
                <span class="sidebar-item-icon" x-text="getModuleIcon('logs', '📋')">📋</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Activity Log</span>
            </a>
        </nav>

        <div style="padding:12px;border-top:1px solid var(--border)">
            <div class="sidebar-item" @click="doLogout()">
                <span class="sidebar-item-icon" x-text="getModuleIcon('logout', '🚪')">🚪</span>
                <span class="sidebar-item-label" x-show="isSidebarTextVisible()">Keluar System</span>
            </div>
        </div>
    </aside>

    <!-- ══ TOPBAR ════════════════════════════════════════════════════════ -->
    <header class="topbar">
        <button @click="toggleSidebar()" class="topbar-toggle-btn" title="Toggle Sidebar">☰</button>

        <div class="topbar-breadcrumb">
            <span>🏠</span>
            <span class="topbar-breadcrumb-sep">/</span>
            <span>{{ $moduleLabel ?? 'Dashboard' }}</span>
            <span class="topbar-breadcrumb-sep">/</span>
            <span class="topbar-breadcrumb-current">{{ $pageTitle ?? '' }}</span>
        </div>

        <div class="topbar-actions" style="margin-left:auto">
            <!-- Outlet Badge -->
            <div class="outlet-badge" title="Active Coffee Outlet">
                <span style="font-size:12px">🏪</span>
                <span class="outlet-badge-text" style="font-weight:600;font-size:12px" x-text="brand.name || 'Ratio Coffee HQ'"></span>
            </div>

            <!-- Notification Bell -->
            <div class="position-relative">
                <button class="topbar-btn position-relative" @click="showNotifs = !showNotifs" title="Notifikasi Operasional Sistem">
                    🔔
                    <span class="notif-badge" x-show="unreadCount() > 0" x-text="unreadCount()"></span>
                </button>
                <div x-show="showNotifs" x-cloak @click.away="showNotifs = false" class="notif-dropdown shadow-lg" style="width:340px; padding:0; border-radius:14px; overflow:hidden; border:1px solid var(--border); background:var(--bg-surface)">
                    <div class="notif-header d-flex justify-content-between align-items-center p-3 border-bottom" style="background:var(--bg-muted)">
                        <div class="fw-bold text-dark small m-0 d-flex align-items-center gap-1.5">
                            <span>🔔</span>
                            <span>NOTIFIKASI OPERASIONAL</span>
                            <span class="badge rounded-pill bg-danger ms-1" style="font-size:10px" x-text="unreadCount()" x-show="unreadCount() > 0"></span>
                        </div>
                        <button type="button" class="btn btn-link p-0 text-decoration-none small text-muted" style="font-size:11px" @click="markAllRead()" x-show="unreadCount() > 0">
                            Tandai Dibaca
                        </button>
                    </div>

                    <div style="max-height:360px; overflow-y:auto">
                        <template x-for="item in unreadNotifs()" :key="item.id">
                            <div class="notif-item p-3 border-bottom cursor-pointer d-flex gap-2.5 align-items-start" style="transition:background 0.2s; border-color:var(--border)" onmouseover="this.style.background='var(--bg-muted)'" onmouseout="this.style.background='transparent'" @click="handleNotifClick(item)">
                                <div style="font-size:18px" x-text="item.icon || '🔔'"></div>
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge" :class="getNotifBadgeClass(item.type)" style="font-size:9.5px; padding:2px 6px; border-radius:6px" x-text="item.badge"></span>
                                        <span class="text-muted" style="font-size:10px" x-text="item.timestamp"></span>
                                    </div>
                                    <div class="fw-bold text-dark mb-0.5" style="font-size:12.5px" x-text="item.title"></div>
                                    <div class="text-muted small" style="font-size:11.5px; line-height:1.3" x-text="item.message"></div>
                                </div>
                            </div>
                        </template>

                        <template x-if="unreadCount() === 0">
                            <div class="p-4 text-center">
                                <div style="font-size:32px" class="mb-2">✅</div>
                                <div class="fw-bold text-dark small mb-1">Sistem Operasional Normal</div>
                                <div class="text-muted" style="font-size:11px">Tidak ada peringatan stok kritis atau tugas pending saat ini.</div>
                            </div>
                        </template>
                    </div>

                    <div class="p-2 border-top text-center" style="background:var(--bg-muted)">
                        <button type="button" class="btn btn-sm text-primary p-0 fw-semibold" style="font-size:11.5px; border:none; background:none" @click="fetchNotifications()">
                            🔄 Refresh Notifikasi
                        </button>
                    </div>
                </div>
            </div>

            <!-- Theme Toggle -->
            <button class="topbar-btn" @click="toggleTheme()" title="Toggle Dark/Light Mode">
                <span x-text="theme === 'dark' ? '☀️' : '🌙'"></span>
            </button>

            <!-- Profile Dropdown -->
            <div class="position-relative ps-2" style="border-left:1px solid var(--border)">
                <button class="profile-btn" @click="showProfile = !showProfile">
                    <div class="topbar-avatar" x-text="userInitial()"></div>
                    <div class="text-start topbar-profile-text">
                        <div style="font-size:13px;font-weight:700;line-height:1.2;color:var(--txt-primary)"
                             x-text="user?.name || 'CEO Owner'"></div>
                        <div style="font-size:11px;color:var(--txt-muted)"
                             x-text="(user?.role?.name || 'Chief Executive Officer') + ' ▾'"></div>
                    </div>
                </button>
                <div x-show="showProfile" x-cloak @click.away="showProfile = false" class="profile-dropdown">
                    <a href="/account-settings" class="profile-dropdown-item">⚙️ Setting Account</a>
                    <a href="/security" class="profile-dropdown-item">🛡️ Keamanan &amp; MFA</a>
                    <a href="/hq-control-center" class="profile-dropdown-item" x-show="canSee('hq')">🏢 HQ Control Center</a>
                    <div class="profile-dropdown-item" style="color:var(--clr-danger);cursor:pointer" @click="doLogout()">🚪 Keluar System</div>
                </div>
            </div>
        </div>
    </header>

    <!-- ══ MAIN CONTENT ══════════════════════════════════════════════════ -->
    <main class="main-content">
        @if(isset($pageTitle))
        <div style="margin-bottom:20px">
            <h1 style="font-size:22px;font-weight:800;margin:0 0 4px;letter-spacing:-0.5px">{{ $pageTitle }}</h1>
            @if(isset($pageSubtitle))
            <p style="font-size:13px;color:var(--txt-secondary);margin:0">{{ $pageSubtitle }}</p>
            @endif
        </div>
        @endif

        @yield('page-content')
    </main>

    @include('components.bi-chat-drawer')
    @include('components.confirm-modal')
    @include('components.printer-settings-modal')
</div>
@endsection

@push('scripts')
<script>
function erpLayout() {
    return {
        collapsed: false,
        mobileOpen: false,
        imgFailed: false,
        showNotifs: false,
        showProfile: false,
        user: null,
        brand: { name: 'Ratio Coffee', tagline: 'ERP Control Center', logo: '☕' },
        moduleIcons: {},
        theme: localStorage.getItem('erp_theme') || 'light',
        notifs: [],
        readNotifs: JSON.parse(localStorage.getItem('erp_read_notifs') || '[]'),

        getModuleIcon(key, defaultIcon) {
            if (this.moduleIcons && this.moduleIcons[key]) {
                return this.moduleIcons[key];
            }
            return defaultIcon;
        },

        isSidebarTextVisible() {
            if (window.innerWidth <= 991) {
                return true;
            }
            return !this.collapsed;
        },

        toggleSidebar() {
            if (window.innerWidth <= 991) {
                this.mobileOpen = !this.mobileOpen;
            } else {
                this.collapsed = !this.collapsed;
            }
        },

        init() {
            // Auth guard
            const token = localStorage.getItem('erp_token');
            if (!token) { window.location.href = '/login'; return; }
            axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;

            this.user = JSON.parse(localStorage.getItem('erp_user') || '{}');
            document.documentElement.setAttribute('data-theme', this.theme);

            this.loadBrand();
            this.fetchNotifications();
            setInterval(() => this.fetchNotifications(), 60000);

            // Listen to global brand update event from HQ Control Center
            window.addEventListener('brand-updated', (e) => {
                if (e.detail) {
                    const b = e.detail;
                    this.brand = {
                        name: b.brand_name || b.name || this.brand.name,
                        tagline: b.brand_tagline || b.tagline || this.brand.tagline,
                        logo: b.brand_logo || b.logo_url || b.logo || this.brand.logo,
                        operating_hours_open: b.operating_hours_open || this.brand.operating_hours_open,
                        operating_hours_close: b.operating_hours_close || this.brand.operating_hours_close,
                    };
                    if (b.module_icons) {
                        try {
                            this.moduleIcons = typeof b.module_icons === 'string' ? JSON.parse(b.module_icons) : b.module_icons;
                        } catch(err) {}
                    }
                    this.imgFailed = false;
                    if (this.isImageLogo(this.brand.logo)) {
                        document.getElementById('app-favicon')?.setAttribute('href', this.formatLogoUrl(this.brand.logo));
                    }
                }
            });
        },

        loadBrand() {
            axios.get('/api/v1/brand-settings').then(r => {
                const b = r.data?.data || r.data || {};
                this.brand = {
                    name: b.brand_name || b.name || 'Ratio Coffee',
                    tagline: b.brand_tagline || b.tagline || 'ERP Control Center',
                    logo: b.brand_logo || b.logo_url || b.logo || '☕',
                    operating_hours_open: b.operating_hours_open || b.operatingHoursOpen || '10:00',
                    operating_hours_close: b.operating_hours_close || b.operatingHoursClose || '22:00',
                };
                if (b.module_icons) {
                    try {
                        this.moduleIcons = typeof b.module_icons === 'string' ? JSON.parse(b.module_icons) : b.module_icons;
                    } catch(err) {}
                }
                this.imgFailed = false;
                if (this.isImageLogo(this.brand.logo)) {
                    document.getElementById('app-favicon')?.setAttribute('href', this.formatLogoUrl(this.brand.logo));
                }
            }).catch(() => {});
        },

        isImageLogo(v) {
            if (!v || typeof v !== 'string') return false;
            if (v.startsWith('data:image') || v.startsWith('http://') || v.startsWith('https://') || v.startsWith('/')) return true;
            return /\.(png|jpg|jpeg|gif|svg|webp)($|\?)/i.test(v) || v.includes('uploads/') || v.includes('storage/');
        },

        formatLogoUrl(v) {
            if (!v || typeof v !== 'string') return '';
            if (v.startsWith('data:image') || v.startsWith('http://') || v.startsWith('https://') || v.startsWith('/')) return v;
            return '/' + v;
        },

        userInitial() {
            const n = this.user?.name || 'C';
            return n.charAt(0).toUpperCase();
        },

        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', this.theme);
            localStorage.setItem('erp_theme', this.theme);
        },

        isActive(path) {
            return window.location.pathname.startsWith(path);
        },

        roleDashLabel() {
            const r = this.user?.role?.slug || 'ceo';
            const m = { ceo:'Dashboard CEO', employee:'Dashboard CEO', partner:'Executive Dashboard', operator:'Dashboard Operator', consultant:'Dashboard Consultant' };
            return m[r] || 'Dashboard CEO';
        },

        get role() { return this.user?.role?.slug || 'ceo'; },

        canSee(section) {
            const accessMap = {
                ceo:        ['dashboard','operations','pos','inventory','suppliers','products','research','finance','expenses','tax','admin-fee','hr','hq','logs','reports','security'],
                employee:   ['dashboard','operations','pos','inventory','suppliers','products','research','finance','expenses','hq','reports','security'],
                partner:    ['dashboard','security'],
                consultant: ['research','operations','security'],
                operator:   ['dashboard','operations','pos','inventory','suppliers','products','research','finance','expenses','revenue-sharing','admin-fee','hr','logs','reports','security'],
            };
            const allowed = accessMap[this.role] ?? accessMap['ceo'];
            return allowed.includes(section);
        },

        async fetchNotifications() {
            try {
                const r = await axios.get('/api/v1/notifications');
                this.notifs = r.data?.data || [];
            } catch(e) {
                console.error('Gagal memuat notifikasi operasional:', e);
            }
        },

        unreadNotifs() {
            return this.notifs.filter(n => !this.readNotifs.includes(n.id));
        },

        unreadCount() {
            return this.unreadNotifs().length;
        },

        markAllRead() {
            const allIds = this.notifs.map(n => n.id);
            this.readNotifs = Array.from(new Set([...this.readNotifs, ...allIds]));
            localStorage.setItem('erp_read_notifs', JSON.stringify(this.readNotifs));
        },

        handleNotifClick(item) {
            if (!this.readNotifs.includes(item.id)) {
                this.readNotifs.push(item.id);
                localStorage.setItem('erp_read_notifs', JSON.stringify(this.readNotifs));
            }
            this.showNotifs = false;
            if (item.url) {
                window.location.href = item.url;
            }
        },

        getNotifBadgeClass(type) {
            switch(type) {
                case 'danger': return 'bg-danger text-white';
                case 'warning': return 'bg-warning text-dark';
                case 'info': return 'bg-primary text-white';
                case 'success': return 'bg-success text-white';
                default: return 'bg-secondary text-white';
            }
        },

        async doLogout() {
            try { await axios.post('/api/v1/auth/logout'); } catch(e) {}
            localStorage.removeItem('erp_token');
            localStorage.removeItem('erp_user');
            if (window.ThermalPrinterManager) {
                try { await window.ThermalPrinterManager.disconnect(); } catch (_) {}
            }
            localStorage.removeItem('thermal_printer_device_id');
            localStorage.removeItem('thermal_printer_device');
            localStorage.removeItem('thermal_printer_mode');
            window.location.href = '/login';
        }
    }
}
</script>
@endpush