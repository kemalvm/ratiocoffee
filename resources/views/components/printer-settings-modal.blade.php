<!-- Global Printer Settings & Connection Modal -->
<div x-data="printerSettingsComp()" x-init="init()" x-cloak>
    <div x-show="show" class="modal-overlay" @click.self="show=false" style="display:flex; z-index:10010">
        <div class="modal-box" style="max-width:560px; border-radius:16px">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" style="font-size:16px">
                    🖨️ Pengaturan Printer Thermal
                </h5>
                <button class="btn btn-secondary btn-sm" style="border-radius:8px" @click="show=false">✕</button>
            </div>

            <div class="modal-body py-3" style="max-height:78vh; overflow-y:auto">

                <!-- Status Koneksi Aktif -->
                <div class="mb-3 p-3 rounded-3 border" :class="connectedDevice ? 'bg-success-subtle border-success' : 'bg-light border-secondary'">
                    <div class="d-flex align-items-center gap-2">
                        <span x-text="connectedDevice ? '🟢' : '⚪'" style="font-size:18px"></span>
                        <div>
                            <div class="fw-bold small text-dark" x-text="connectedDevice ? connectedDevice.label : 'Belum ada printer yang dipilih'"></div>
                            <div class="text-muted" style="font-size:11px" x-text="connectedDevice ? (connectedDevice.type === 'usb' ? 'Terhubung via USB / Serial COM' : (connectedDevice.type === 'bluetooth' ? 'Terhubung via Bluetooth Nirkabel' : 'Terhubung via Sistem / Bridge')) : 'Pilih printer di bawah untuk memulai'"></div>
                        </div>
                        <template x-if="connectedDevice">
                            <button class="btn btn-outline-danger btn-sm ms-auto fw-bold" style="border-radius:8px; font-size:11px" @click="disconnectPrinter()">Putuskan</button>
                        </template>
                    </div>
                </div>

                <!-- Info Kompatibilitas -->
                <div class="alert alert-info py-2 small mb-3" style="border-radius:10px">
                    <strong>ℹ️ Metode Koneksi:</strong><br>
                    • <strong>USB / COM Port:</strong> Printer thermal via kabel USB ke PC kasir / OTG tablet. Klik tombol <strong>"+ USB / COM Port"</strong> di bawah.<br>
                    • <strong>Bluetooth BLE:</strong> Printer thermal nirkabel. Pastikan Bluetooth aktif, lalu klik <strong>"+ Bluetooth BLE"</strong>.<br>
                    • <strong>Koneksi Persisten:</strong> Koneksi printer otomatis tetap aktif saat berpindah tab, berpindah menu, atau perangkat mati/tidur. Hanya terputus saat kasir logout atau menekan tombol 'Putuskan'.
                </div>

                <!-- Daftar Printer yang Tersedia -->
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold small text-dark">Printer yang Tersedia:</span>
                        <button class="btn btn-outline-secondary btn-sm" style="border-radius:8px; font-size:11px" @click="refreshDevices()" :disabled="refreshing">
                            <span x-show="refreshing" class="spinner-border spinner-border-sm me-1"></span>
                            <span>🔄 Refresh</span>
                        </button>
                    </div>

                    <template x-if="devices.length === 0 && !refreshing">
                        <div class="text-muted small p-3 text-center bg-light rounded-3">
                            Belum ada printer yang terdeteksi.<br>
                            Klik tombol <strong>"+ USB / COM Port"</strong> atau <strong>"+ Bluetooth BLE"</strong> di bawah untuk memilih printer Anda.
                        </div>
                    </template>

                    <template x-for="dev in devices" :key="dev.id">
                        <label class="d-flex align-items-center gap-3 p-2 mb-2 border rounded-3 cursor-pointer"
                               :class="selectedId === dev.id ? 'bg-primary-subtle border-primary' : 'bg-white'"
                               @click="selectPrinter(dev)">
                            <span x-text="dev.type === 'usb' ? '🔌' : (dev.type === 'bluetooth' ? '📶' : '🖨️')" style="font-size:20px"></span>
                            <div class="flex-grow-1">
                                <div class="fw-bold small text-dark" x-text="dev.label"></div>
                                <div class="text-muted" style="font-size:10px" x-text="dev.subtitle || (dev.type === 'usb' ? 'USB / COM Port' : (dev.type === 'bluetooth' ? 'Bluetooth BLE' : 'Printer'))"></div>
                            </div>
                            <template x-if="connectedDevice?.id === dev.id">
                                <span class="badge bg-success text-white" style="font-size:10px">Aktif</span>
                            </template>
                            <template x-if="selectedId === dev.id && connectedDevice?.id !== dev.id">
                                <span class="badge bg-primary text-white" style="font-size:10px">Dipilih</span>
                            </template>
                        </label>
                    </template>
                </div>

                <!-- Tambah Printer Baru -->
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <button class="btn btn-outline-primary btn-sm fw-bold px-3" style="border-radius:8px"
                            :disabled="!serialSupported || connecting"
                            @click="addPrinter('usb')">
                        <span x-show="connecting && addingType === 'usb'" class="spinner-border spinner-border-sm me-1"></span>
                        🔌 + USB / COM Port
                    </button>
                    <button class="btn btn-outline-info btn-sm fw-bold px-3" style="border-radius:8px"
                            :disabled="!bluetoothSupported || connecting"
                            @click="addPrinter('bluetooth')">
                        <span x-show="connecting && addingType === 'bluetooth'" class="spinner-border spinner-border-sm me-1"></span>
                        📶 + Bluetooth BLE
                    </button>
                </div>

                <template x-if="!serialSupported">
                    <div class="text-danger small mb-2">⚠️ Web Serial tidak aktif. Pastikan menggunakan browser Chrome atau Edge melalui HTTPS.</div>
                </template>
                <template x-if="!bluetoothSupported">
                    <div class="text-warning small mb-2">⚠️ Web Bluetooth tidak aktif di browser/sistem operasi ini.</div>
                </template>

                <!-- Ukuran Kertas & Baud Rate -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="mb-2">
                        <span class="fw-bold small text-dark d-block mb-2">Ukuran Kertas Thermal:</span>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn fw-bold" :class="paperWidth==='58mm'?'btn-primary':'btn-outline-secondary'" @click="setPaper('58mm')">58mm (Standar)</button>
                            <button type="button" class="btn fw-bold" :class="paperWidth==='80mm'?'btn-primary':'btn-outline-secondary'" @click="setPaper('80mm')">80mm (Lebar)</button>
                        </div>
                    </div>
                    <div x-show="selectedType === 'usb'" class="mt-2">
                        <span class="fw-bold small text-dark d-block mb-1">Baud Rate Serial:</span>
                        <select class="form-select form-select-sm" x-model.number="baudRate" @change="setBaudRate()">
                            <option :value="9600">9600 (Default sebagian besar printer thermal)</option>
                            <option :value="19200">19200</option>
                            <option :value="38400">38400</option>
                            <option :value="115200">115200</option>
                        </select>
                    </div>
                </div>

                <!-- Sambungkan & Test Print -->
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-success fw-bold px-4 py-2 flex-grow-1" style="border-radius:10px"
                            :disabled="!selectedId || connecting"
                            @click="connectSelected()">
                        <span x-show="connecting && !addingType" class="spinner-border spinner-border-sm me-1"></span>
                        ⚡ Sambungkan Printer Terpilih
                    </button>
                    <button class="btn btn-outline-primary fw-bold px-3 py-2" style="border-radius:10px"
                            :disabled="testing || (!connectedDevice && !selectedId)"
                            @click="testPrint()">
                        <span x-show="testing" class="spinner-border spinner-border-sm me-1"></span>
                        🧪 Uji Cetak
                    </button>
                </div>

                <!-- Error / Status Message -->
                <template x-if="statusMsg">
                    <div class="mt-3 alert py-2 small" :class="statusOk ? 'alert-success' : 'alert-danger'" x-text="statusMsg"></div>
                </template>
            </div>

            <div class="modal-footer border-top pt-2 gap-2">
                <div class="me-auto small text-muted">
                    Pilihan tersimpan otomatis per perangkat kasir ini (tetap aktif antar tab).
                </div>
                <button class="btn btn-secondary btn-sm px-4 fw-bold" style="border-radius:8px" @click="show=false">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Thermal Printer Engine Loader Check ──────────────────────────────────────
(function() {
    if (!window.ThermalPrinterManager) {
        console.warn('[PrinterModal] ThermalPrinterManager belum dimuat, memuat /js/thermal-printer.js...');
        const s = document.createElement('script');
        s.src = '/js/thermal-printer.js?v=' + Date.now();
        document.head.appendChild(s);
    }
})();

// ── Alpine Component untuk Modal Pengaturan Printer ─────────────────────────
function printerSettingsComp() {
    return {
        show: false,
        devices: [],
        selectedId: localStorage.getItem('thermal_printer_device_id') || '',
        selectedType: localStorage.getItem('thermal_printer_mode') || 'usb',
        connectedDevice: (function() {
            const id = localStorage.getItem('thermal_printer_device_id');
            const name = localStorage.getItem('thermal_printer_device');
            const mode = localStorage.getItem('thermal_printer_mode') || 'usb';
            return id ? { id, label: name || id, type: mode } : null;
        })(),
        paperWidth: localStorage.getItem('thermal_printer_paper') || '58mm',
        baudRate: Number(localStorage.getItem('thermal_printer_baud_rate') || 9600),
        connecting: false,
        addingType: '',
        refreshing: false,
        testing: false,
        statusMsg: '',
        statusOk: false,
        serialSupported: Boolean(navigator.serial),
        bluetoothSupported: Boolean(navigator.bluetooth),

        init() {
            window.showPrinterSettingsModal = () => {
                this.show = true;
                this.clearStatus();
                this.refreshDevices();
            };

            // Sinkronisasi status dari event
            window.addEventListener('printer:status', (e) => {
                if (e.detail && e.detail.connected && e.detail.deviceId) {
                    this.connectedDevice = {
                        id: e.detail.deviceId,
                        label: e.detail.deviceName || e.detail.deviceId,
                        type: e.detail.type || this.selectedType || 'usb'
                    };
                    this.selectedId = e.detail.deviceId;
                } else if (e.detail && !e.detail.connected) {
                    this.connectedDevice = null;
                }
            });

            // Sinkronisasi status dari tab lain
            window.addEventListener('storage', (e) => {
                if (e.key === 'thermal_printer_device_id' || e.key === 'thermal_printer_device' || e.key === 'thermal_printer_mode') {
                    const id = localStorage.getItem('thermal_printer_device_id');
                    const name = localStorage.getItem('thermal_printer_device');
                    const mode = localStorage.getItem('thermal_printer_mode') || 'usb';
                    if (id) {
                        this.selectedId = id;
                        this.selectedType = mode;
                        this.connectedDevice = { id, label: name || id, type: mode };
                    } else {
                        this.connectedDevice = null;
                        this.selectedId = '';
                    }
                }
            });

            // Baca printer tersimpan langsung dari localStorage
            const savedId = localStorage.getItem('thermal_printer_device_id');
            const savedName = localStorage.getItem('thermal_printer_device');
            const savedMode = localStorage.getItem('thermal_printer_mode') || 'usb';
            if (savedId) {
                this.selectedId = savedId;
                this.selectedType = savedMode;
                this.connectedDevice = { id: savedId, label: savedName || savedId, type: savedMode };
            }

            // Pulihkan koneksi fisik di latar belakang
            if (this.selectedId && window.ThermalPrinterManager) {
                window.ThermalPrinterManager.autoReconnect().catch(() => {});
            }
        },

        async refreshDevices() {
            if (!window.ThermalPrinterManager) return;
            this.refreshing = true;
            try {
                this.devices = await window.ThermalPrinterManager.listAuthorizedDevices();

                const cur = this.devices.find(d => d.id === this.selectedId);
                if (cur) {
                    this.selectedType = cur.type;
                }

                // Jaga connectedDevice agar tetap aktif & tidak hilang saat refresh
                if (this.selectedId) {
                    const name = localStorage.getItem('thermal_printer_device') || cur?.label || this.selectedId;
                    this.connectedDevice = { id: this.selectedId, label: name, type: this.selectedType };
                }
            } catch (e) {
                console.error('[PrinterSettings] Refresh error:', e);
                this.showStatus('Gagal memuat daftar printer: ' + e.message, false);
            } finally {
                this.refreshing = false;
            }
        },

        async addPrinter(type) {
            if (!window.ThermalPrinterManager) {
                this.showStatus('❌ Thermal Printer Manager belum dimuat.', false);
                return;
            }
            this.connecting = true;
            this.addingType = type;
            this.clearStatus();
            try {
                const label = await window.ThermalPrinterManager.addNewDevice(type);
                this.showStatus(`✅ ${label} berhasil ditambahkan dan terhubung!`, true);
                await this.refreshDevices();

                const id = localStorage.getItem('thermal_printer_device_id');
                if (id) {
                    this.selectedId = id;
                    const dev = this.devices.find(d => d.id === id);
                    if (dev) this.selectedType = dev.type;
                    this.connectedDevice = { id, label, type: this.selectedType };
                }
            } catch (e) {
                console.warn('[PrinterSettings] Add printer:', e);
                if (e.name === 'NotFoundError' || e.message?.includes('No port selected') || e.message?.includes('cancelled')) {
                    this.showStatus('Pemilihan perangkat dibatalkan.', false);
                } else {
                    this.showStatus('❌ ' + e.message, false);
                }
            } finally {
                this.connecting = false;
                this.addingType = '';
            }
        },

        selectPrinter(dev) {
            this.selectedId = dev.id;
            this.selectedType = dev.type;
            this.clearStatus();
        },

        async connectSelected() {
            if (!this.selectedId || !window.ThermalPrinterManager) return;
            this.connecting = true;
            this.clearStatus();
            try {
                const label = await window.ThermalPrinterManager.selectDevice(this.selectedId);
                this.connectedDevice = { id: this.selectedId, label, type: this.selectedType };
                this.showStatus(`✅ ${label} berhasil terhubung!`, true);
            } catch (e) {
                console.error('[PrinterSettings] Connect error:', e);
                this.showStatus('❌ Gagal menghubungkan: ' + e.message, false);
            } finally {
                this.connecting = false;
            }
        },

        async disconnectPrinter() {
            if (!window.ThermalPrinterManager) return;
            await window.ThermalPrinterManager.disconnect();
            this.connectedDevice = null;
            this.selectedId = '';
            this.clearStatus();
            this.showStatus('Printer diputuskan.', true);
        },

        setPaper(w) {
            this.paperWidth = w;
            localStorage.setItem('thermal_printer_paper', w);
            if (window.ThermalPrinterManager) window.ThermalPrinterManager.setPaperWidth(w);
        },

        setBaudRate() {
            localStorage.setItem('thermal_printer_baud_rate', String(this.baudRate));
            if (window.ThermalPrinterManager) window.ThermalPrinterManager.setBaudRate(this.baudRate);
        },

        async testPrint() {
            if (!window.ThermalPrinterManager) return;
            this.testing = true;
            this.clearStatus();
            try {
                if (this.selectedId && !this.connectedDevice) {
                    try { await window.ThermalPrinterManager.selectDevice(this.selectedId); } catch (_) {}
                    const name = localStorage.getItem('thermal_printer_device') || this.selectedId;
                    this.connectedDevice = { id: this.selectedId, label: name, type: this.selectedType };
                }

                const res = await window.ThermalPrinterManager.printTestReceipt();
                this.showStatus(`✅ ${res?.message || 'Test print berhasil dikirim!'} Silakan periksa hasil cetakan kertas printer.`, true);
            } catch (e) {
                console.error('[PrinterSettings] Test print error:', e);
                this.showStatus('❌ ' + e.message, false);
            } finally {
                this.testing = false;
            }
        },

        showStatus(msg, ok) {
            this.statusMsg = msg;
            this.statusOk = ok;
            if (ok) setTimeout(() => { if (this.statusOk) this.clearStatus(); }, 5000);
        },

        clearStatus() {
            this.statusMsg = '';
            this.statusOk = false;
        }
    };
}
</script>
