// Ratio Coffee ERP – Global Bootstrap (no Vite, no Vue)
// Runs before Alpine.js initializes

// ── Axios defaults ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const token = localStorage.getItem('erp_token');
    if (token) {
        axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
    }
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta && csrfMeta.content) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.content;
    }
    axios.defaults.withCredentials = true;
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    // Auto-logout on 401 (only if token is invalid on explicit auth check)
    axios.interceptors.response.use(
        r => r,
        err => {
            if (err.response?.status === 401) {
                const url = err.config?.url || '';
                const isAuthCheck = url.includes('/auth/me') || url.includes('/auth/logout');
                if (isAuthCheck && !window.location.pathname.includes('/login')) {
                    localStorage.removeItem('erp_token');
                    localStorage.removeItem('erp_user');
                    window.location.href = '/login';
                }
            }
            return Promise.reject(err);
        }
    );
});

// ── Alpine global appRoot ──────────────────────────────────────────────
function appRoot() {
    return {
        theme: localStorage.getItem('erp_theme') || 'light',
        init() {
            document.documentElement.setAttribute('data-theme', this.theme);
        }
    };
}

// ── Helpers ────────────────────────────────────────────────────────────
function formatRp(val) {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
}

function formatNumber(val) {
    return Number(val || 0).toLocaleString('id-ID');
}

function formatDate(dt, opts) {
    if (!dt) return '-';
    const d = new Date(dt);
    if (opts === 'time') {
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
    }
    if (opts === 'full') {
        return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function getInitials(name) {
    if (!name) return 'K';
    const p = String(name).trim().split(' ');
    return p.length >= 2 ? (p[0][0] + p[1][0]).toUpperCase() : String(name).slice(0, 2).toUpperCase();
}

function getPaymentIcon(m) {
    const method = (m || '').toUpperCase();
    if (method === 'CASH') return '💵';
    if (method === 'QRIS') return '📱';
    if (method === 'TRANSFER' || method === 'BANK_TRANSFER') return '🏦';
    if (['DEBIT','CREDIT','EDC','CARD'].includes(method)) return '💳';
    return '💰';
}

function toast(msg, type = 'success') {
    Swal.fire({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 3000, timerProgressBar: true,
        icon: type, title: msg,
    });
}

function confirmDelete(callback, name = 'data ini') {
    Swal.fire({
        title: 'Hapus ' + name + '?',
        text: 'Tindakan ini tidak bisa dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonText: 'Batal',
        confirmButtonText: 'Ya, Hapus!'
    }).then(r => { if (r.isConfirmed) callback(); });
}
