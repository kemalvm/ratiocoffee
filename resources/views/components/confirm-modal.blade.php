<!-- Global Confirmation Modal Component -->
<div x-data="confirmModalComp()" x-init="init()" x-cloak>
    <div x-show="show" class="confirm-overlay" @click.self="cancel()" style="display:flex">
        <div class="confirm-dialog" role="dialog">
            <div :class="['confirm-icon-wrapper', type]">
                <span class="confirm-icon" x-text="type === 'danger' ? '🗑️' : (type === 'warning' ? '⚠️' : 'ℹ️')"></span>
            </div>
            <div class="confirm-content">
                <h3 class="confirm-title" x-text="title"></h3>
                <p class="confirm-message">
                    <span x-text="message"></span>
                    <strong class="item-highlight" x-show="itemName" x-text="'&quot;' + itemName + '&quot;'"></strong>?
                </p>
                <div x-show="dangerNotice" class="danger-notice">
                    <span class="notice-icon">⚠️</span>
                    <span x-text="dangerNotice"></span>
                </div>
            </div>
            <div class="confirm-actions">
                <button class="btn btn-secondary confirm-cancel-btn" :disabled="loading" @click="cancel()">Batal</button>
                <button :class="['btn', type === 'danger' ? 'btn-danger-confirm' : 'btn-primary']" :disabled="loading" @click="confirm()">
                    <span x-show="loading" class="spinner-sm"></span>
                    <span x-text="loading ? 'Memproses...' : confirmText"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.confirm-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
  z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;
}
.confirm-dialog {
  background: var(--bg-surface, #ffffff); border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px; width: 100%; max-width: 440px; padding: 28px;
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.25); text-align: center;
}
.confirm-icon-wrapper { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 18px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
.confirm-icon-wrapper.danger { background: #fef2f2; border: 2px solid #fee2e2; box-shadow: 0 0 20px rgba(239, 68, 68, 0.2); }
.confirm-icon-wrapper.warning { background: #fffbebfb; border: 2px solid #fef3c7; box-shadow: 0 0 20px rgba(245, 158, 11, 0.2); }

.confirm-title { font-size: 19px; font-weight: 800; color: var(--txt-primary, #0f172a); margin: 0 0 8px; }
.confirm-message { font-size: 14px; color: var(--txt-secondary, #475569); margin: 0 0 16px; line-height: 1.5; }
.item-highlight { color: var(--clr-danger, #ef4444); background: rgba(239, 68, 68, 0.08); padding: 2px 8px; border-radius: 6px; font-weight: 700; }
.danger-notice { display: flex; align-items: center; justify-content: center; gap: 8px; background: #fef2f2; border: 1px dashed #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; margin-bottom: 24px; }
.confirm-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.confirm-cancel-btn { border-radius: 10px; padding: 11px; font-weight: 700; }
.btn-danger-confirm { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: #ffffff; border: none; border-radius: 10px; padding: 11px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px; }
</style>

<script>
function confirmModalComp() {
    return {
        show: false, title: 'Konfirmasi Hapus', message: 'Apakah Anda yakin ingin menghapus data ini?',
        itemName: '', type: 'danger', confirmText: 'Ya, Hapus Sekarang', dangerNotice: 'Tindakan ini tidak dapat dibatalkan.',
        loading: false, callback: null,

        init() {
            window.showConfirmDialog = (opts) => {
                this.title = opts.title || 'Konfirmasi Hapus';
                this.message = opts.message || 'Apakah Anda yakin?';
                this.itemName = opts.itemName || '';
                this.type = opts.type || 'danger';
                this.confirmText = opts.confirmText || 'Ya, Hapus Sekarang';
                this.dangerNotice = opts.dangerNotice || 'Tindakan ini tidak dapat dibatalkan.';
                this.callback = opts.onConfirm || null;
                this.show = true;
            };
        },

        async confirm() {
            if (this.callback) {
                this.loading = true;
                try {
                    await this.callback();
                } catch(e) {}
                finally {
                    this.loading = false;
                    this.show = false;
                }
            } else {
                this.show = false;
            }
        },

        cancel() {
            if (!this.loading) this.show = false;
        }
    }
}
</script>
