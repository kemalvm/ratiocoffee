/*
 * Ratio POS — Thermal Printer Manager (Persistent Cross-Tab Engine)
 * -----------------------------------------------------------------
 * Fitur Utama:
 * 1. Koneksi Persisten: Tetap aktif saat web di-refresh (F5), berpindah tab,
 *    berpindah modul/halaman, dan layar mati/bangun (sleep/wake).
 * 2. Auto-Resume Cerdas: Secara otomatis memulihkan handle port / GATT / Bridge
 *    di latar belakang dan saat eksekusi cetak tanpa memerlukan pairing ulang manual.
 * 3. Multi-Device:
 *    - USB / OTG Direct via Web Serial (PC, Laptop, Tablet Android)
 *    - Bluetooth BLE Nirkabel via Web Bluetooth
 *    - Local Bridge di PC kasir (Windows Spooler & COM Bluetooth/USB)
 * 4. Multi-Tab Realtime Bus via BroadcastChannel & Storage Event.
 */
(function () {
    'use strict';

    const BRIDGE_URL_KEY = 'ratio_printer_bridge_url';
    const DEFAULT_BRIDGE_URL = 'http://localhost:17777';
    const CHANNEL_NAME = 'ratio_thermal_printer_bus';
    const DUPLICATE_COOLDOWN_MS = 2000;

    const BLE_SERVICES = [
        '000018f0-0000-1000-8000-00805f9b34fb',
        '0000ffe0-0000-1000-8000-00805f9b34fb',
        '0000ff00-0000-1000-8000-00805f9b34fb',
        '49535343-fe7d-41aa-a756-3d65d080da1a',
        '6e400001-b5a3-f393-e0a9-e50e24dcca9e',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    ];

    const rupiah = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const toAscii = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/[–—]/g, '-').replace(/…/g, '...');

    function wrap(text, width) {
        const out = [];
        for (const para of String(text ?? '').split(/\r?\n/)) {
            let line = '';
            for (const word of para.split(/\s+/).filter(Boolean)) {
                if (word.length > width) {
                    if (line) { out.push(line); line = ''; }
                    for (let i = 0; i < word.length; i += width) out.push(word.slice(i, i + width));
                    continue;
                }
                if ((line ? line.length + 1 : 0) + word.length > width) { out.push(line); line = word; }
                else line = line ? line + ' ' + word : word;
            }
            out.push(line);
        }
        return out.length ? out : [''];
    }

    function row(left, right, width) {
        left = String(left); right = String(right);
        if (left.length + right.length + 1 > width) {
            const room = Math.max(1, width - right.length - 1);
            left = left.slice(0, room);
        }
        return left + ' '.repeat(Math.max(1, width - left.length - right.length)) + right;
    }

    function encodeText(str) {
        const bytes = [];
        for (const ch of String(str)) {
            const code = ch.codePointAt(0);
            if (code < 0x80) { bytes.push(code); continue; }
            const ascii = toAscii(ch);
            for (const c of ascii) bytes.push(c.charCodeAt(0) < 0x80 ? c.charCodeAt(0) : 0x3F);
        }
        return bytes;
    }

    function bytesToBase64(bytes) {
        let bin = '';
        for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(bin);
    }

    // Cross-tab broadcast channel
    let bus = null;
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            bus = new BroadcastChannel(CHANNEL_NAME);
        }
    } catch (_) {}

    const TPM = {
        _serialPort: null,
        _ble: { device: null, server: null, characteristic: null },
        _cachedDevices: [],
        _inFlight: new Set(),
        _recent: new Map(),

        isSerialSupported() { return Boolean(navigator.serial); },
        isBluetoothSupported() { return Boolean(navigator.bluetooth); },

        bridgeUrl() {
            return (localStorage.getItem(BRIDGE_URL_KEY) || DEFAULT_BRIDGE_URL).replace(/\/+$/, '');
        },

        emit(name, detail) {
            window.dispatchEvent(new CustomEvent(name, { detail }));
            if (name === 'printer:status' && bus) {
                try { bus.postMessage({ type: 'STATUS_SYNC', detail }); } catch (_) {}
            }
        },

        hex(v) {
            return '0x' + Number(v || 0).toString(16).padStart(4, '0').toUpperCase();
        },

        async bridgeFetch(path, { method = 'GET', body, timeout = 3500 } = {}) {
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), timeout);
            try {
                const res = await fetch(this.bridgeUrl() + path, {
                    method, signal: ctrl.signal, cache: 'no-store',
                    headers: body ? { 'Content-Type': 'application/json' } : undefined,
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = data.message || `Bridge HTTP ${res.status}`;
                    const err = new Error(msg);
                    err.status = res.status;
                    err.code = data.code || 'BRIDGE_HTTP_ERROR';
                    throw err;
                }
                return data;
            } finally { clearTimeout(timer); }
        },

        async bridgeHealth() { return this.bridgeFetch('/health', { timeout: 1500 }); },
        async bridgeListPrinters() {
            const data = await this.bridgeFetch('/printers', { timeout: 2500 });
            return (data.printers || []).map((p) => ({ ...p, method: 'bridge' }));
        },

        // Helper untuk mencocokkan port WebSerial dengan device tersimpan
        matchSerialPort(ports, targetId = '') {
            if (!ports || ports.length === 0) return null;
            if (targetId && targetId.startsWith('webserial:')) {
                const parts = targetId.split(':');
                const targetVid = Number(parts[1]) || 0;
                const targetPid = Number(parts[2]) || 0;
                if (targetVid || targetPid) {
                    const matched = ports.find(p => {
                        const info = p.getInfo ? p.getInfo() : {};
                        return (info.usbVendorId === targetVid && info.usbProductId === targetPid);
                    });
                    if (matched) return matched;
                }
                const targetIdx = Number(parts[3]) || 0;
                if (ports[targetIdx]) return ports[targetIdx];
            }
            return ports[0];
        },

        // ── Discovery Printer yang Benar-Benar Tersedia ─────────────────────
        async listAuthorizedDevices() {
            const list = [];
            const seenIds = new Set();
            const addDevice = (d) => {
                if (!d || !d.id || seenIds.has(d.id)) return;
                seenIds.add(d.id);
                list.push(d);
            };

            // 1. Coba deteksi Local Bridge jika aktif di PC kasir
            try {
                const bridgePrinters = await this.bridgeListPrinters();
                bridgePrinters.forEach((p) => {
                    const isSerial = p.kind === 'serial';
                    const isBT = p.connection === 'bluetooth';
                    addDevice({
                        id: 'bridge:' + p.id,
                        name: p.name,
                        label: p.label || p.name,
                        type: isBT ? 'bluetooth' : (isSerial ? 'usb' : 'bridge'),
                        subtitle: isBT
                            ? `Bluetooth COM (${p.port || p.name}) · Bridge`
                            : (isSerial ? `Serial COM (${p.port || p.name}) · Bridge` : `Printer Windows (${p.port || 'Spooler'}) · Bridge`),
                        source: 'bridge',
                        target: { kind: p.kind, name: p.name, port: p.port, driver: p.driver, connection: p.connection }
                    });
                });
            } catch (_) {}

            // 2. Query Web Serial (Kabel USB / OTG)
            if (this.isSerialSupported()) {
                try {
                    const ports = await navigator.serial.getPorts();
                    ports.forEach((port, idx) => {
                        const info = port.getInfo ? port.getInfo() : {};
                        const vid = info.usbVendorId || 0;
                        const pid = info.usbProductId || 0;
                        const vidHex = vid ? this.hex(vid) : '';
                        const pidHex = pid ? this.hex(pid) : '';
                        const id = `webserial:${vid}:${pid}:${idx}`;
                        const label = (vid || pid)
                            ? `USB Thermal Printer (${vidHex}:${pidHex})`
                            : `USB/COM Serial Port #${idx + 1}`;
                        addDevice({
                            id, name: label, label, type: 'usb',
                            subtitle: (vid || pid) ? `USB VID ${vidHex} · PID ${pidHex}` : 'Serial / COM Port',
                            source: 'webserial', _port: port,
                            target: { kind: 'webserial', usbVendorId: vid, usbProductId: pid, index: idx }
                        });
                    });
                } catch (_) {}
            }

            // 3. Query Web Bluetooth
            if (this.isBluetoothSupported() && navigator.bluetooth.getDevices) {
                try {
                    const bts = await navigator.bluetooth.getDevices();
                    bts.forEach((dev) => {
                        const id = 'webbluetooth:' + dev.id;
                        const label = dev.name || 'Bluetooth Thermal Printer';
                        addDevice({
                            id, name: label, label, type: 'bluetooth',
                            subtitle: 'Bluetooth BLE Nirkabel',
                            source: 'webbluetooth', _device: dev,
                            target: { kind: 'webbluetooth', deviceId: dev.id }
                        });
                    });
                } catch (_) {}
            }

            // 4. Perangkat tersimpan di localStorage (selalu dijaga agar tidak hilang statusnya)
            const savedId = localStorage.getItem('thermal_printer_device_id');
            const savedName = localStorage.getItem('thermal_printer_device');
            const savedMode = localStorage.getItem('thermal_printer_mode') || 'usb';
            if (savedId && !seenIds.has(savedId)) {
                addDevice({
                    id: savedId, name: savedName || savedId, label: savedName || savedId,
                    type: savedMode,
                    subtitle: 'Printer Tersimpan di Perangkat Ini', source: 'saved'
                });
            }

            this._cachedDevices = list;
            return list;
        },

        // ── Tambah Device Baru ──────────────────────────────────────────────
        async addNewDevice(type) {
            if (type === 'usb') {
                if (!this.isSerialSupported()) {
                    throw new Error('Web Serial tidak didukung di browser ini. Gunakan Chrome atau Edge melalui HTTPS.');
                }
                const port = await navigator.serial.requestPort();
                const info = port.getInfo ? port.getInfo() : {};
                const vid = info.usbVendorId || 0;
                const pid = info.usbProductId || 0;
                const vidHex = vid ? this.hex(vid) : '';
                const pidHex = pid ? this.hex(pid) : '';
                const id = `webserial:${vid}:${pid}:0`;
                const label = (vid || pid)
                    ? `USB Thermal Printer (${vidHex}:${pidHex})`
                    : 'USB Thermal Printer';

                const baud = Number(localStorage.getItem('thermal_printer_baud_rate') || 9600);
                await this.openWebSerialPort(port, baud);

                localStorage.setItem('thermal_printer_device_id', id);
                localStorage.setItem('thermal_printer_device', label);
                localStorage.setItem('thermal_printer_mode', 'usb');

                this.emit('printer:status', { connected: true, deviceId: id, deviceName: label, type: 'usb' });
                return label;
            }

            if (type === 'bluetooth') {
                if (!this.isBluetoothSupported()) {
                    throw new Error('Web Bluetooth tidak didukung di browser ini. Gunakan Chrome atau Edge melalui HTTPS.');
                }
                const dev = await navigator.bluetooth.requestDevice({
                    acceptAllDevices: true,
                    optionalServices: BLE_SERVICES
                });

                await this.connectBle(dev);

                const id = 'webbluetooth:' + dev.id;
                const label = dev.name || 'Bluetooth Thermal Printer';

                localStorage.setItem('thermal_printer_device_id', id);
                localStorage.setItem('thermal_printer_device', label);
                localStorage.setItem('thermal_printer_mode', 'bluetooth');

                this.emit('printer:status', { connected: true, deviceId: id, deviceName: label, type: 'bluetooth' });
                return label;
            }

            throw new Error(`Tipe printer "${type}" tidak dikenal.`);
        },

        // ── Pilih Printer ───────────────────────────────────────────────────
        async selectDevice(id) {
            if (!id) throw new Error('ID printer tidak valid.');
            let dev = this._cachedDevices.find((d) => d.id === id);
            if (!dev) {
                await this.listAuthorizedDevices();
                dev = this._cachedDevices.find((d) => d.id === id);
            }

            const label = dev?.label || localStorage.getItem('thermal_printer_device') || id;

            // Jika device berasal dari Bridge (Windows / Port COM)
            if (id.startsWith('bridge:') || (dev && dev.source === 'bridge')) {
                try { await this.bridgeHealth(); } catch (_) {}
                localStorage.setItem('thermal_printer_device_id', id);
                localStorage.setItem('thermal_printer_device', label);
                localStorage.setItem('thermal_printer_mode', 'bridge');
                this.emit('printer:status', { connected: true, deviceId: id, deviceName: label, type: 'bridge' });
                return label;
            }

            if (id.startsWith('webserial:') || (dev && dev.source === 'webserial')) {
                let port = dev?._port;
                if (!port && this.isSerialSupported()) {
                    try {
                        const ports = await navigator.serial.getPorts();
                        port = this.matchSerialPort(ports, id);
                    } catch (_) {}
                }
                if (port) {
                    try {
                        const baud = Number(localStorage.getItem('thermal_printer_baud_rate') || 9600);
                        await this.openWebSerialPort(port, baud);
                    } catch (_) {}
                }
                localStorage.setItem('thermal_printer_device_id', id);
                localStorage.setItem('thermal_printer_device', label);
                localStorage.setItem('thermal_printer_mode', 'usb');
                this.emit('printer:status', { connected: true, deviceId: id, deviceName: label, type: 'usb' });
                return label;
            }

            if (id.startsWith('webbluetooth:') || (dev && dev.source === 'webbluetooth')) {
                let bleDev = dev?._device;
                if (!bleDev && this.isBluetoothSupported() && navigator.bluetooth.getDevices) {
                    try {
                        const bts = await navigator.bluetooth.getDevices();
                        bleDev = bts.find((b) => b.id === id.replace('webbluetooth:', '')) || bts[0];
                    } catch (_) {}
                }
                if (bleDev) {
                    try { await this.connectBle(bleDev); } catch (_) {}
                }
                localStorage.setItem('thermal_printer_device_id', id);
                localStorage.setItem('thermal_printer_device', label);
                localStorage.setItem('thermal_printer_mode', 'bluetooth');
                this.emit('printer:status', { connected: true, deviceId: id, deviceName: label, type: 'bluetooth' });
                return label;
            }

            localStorage.setItem('thermal_printer_device_id', id);
            localStorage.setItem('thermal_printer_device', label);
            localStorage.setItem('thermal_printer_mode', dev?.type || 'usb');
            this.emit('printer:status', { connected: true, deviceId: id, deviceName: label, type: dev?.type || 'usb' });
            return label;
        },

        // ── Putuskan Koneksi (Hanya saat logout atau ditekan manual) ────────
        async disconnect() {
            await this.closeSerial();
            try { if (this._ble?.server?.connected) this._ble.server.disconnect(); } catch (_) {}
            this._ble = { device: null, server: null, characteristic: null };

            localStorage.removeItem('thermal_printer_device_id');
            localStorage.removeItem('thermal_printer_device');
            localStorage.removeItem('thermal_printer_mode');
            this.emit('printer:status', { connected: false });
        },

        // Status koneksi: bernilai TRUE selama ada konfigurasi printer tersimpan
        isConnected() {
            const savedId = localStorage.getItem('thermal_printer_device_id');
            return Boolean(savedId);
        },

        // Auto Reconnect di latar belakang saat web di-refresh, tab switch, atau wake
        async autoReconnect() {
            const savedId = localStorage.getItem('thermal_printer_device_id');
            const savedMode = localStorage.getItem('thermal_printer_mode') || 'usb';
            const savedName = localStorage.getItem('thermal_printer_device') || 'Printer';
            if (!savedId) return false;

            try {
                if (savedId.startsWith('bridge:') || savedMode === 'bridge') {
                    try { await this.bridgeHealth(); } catch (_) {}
                } else if ((savedMode === 'usb' || savedId.startsWith('webserial:')) && this.isSerialSupported()) {
                    const ports = await navigator.serial.getPorts();
                    const port = this.matchSerialPort(ports, savedId);
                    if (port) {
                        const baud = Number(localStorage.getItem('thermal_printer_baud_rate') || 9600);
                        await this.openWebSerialPort(port, baud);
                    }
                } else if ((savedMode === 'bluetooth' || savedId.startsWith('webbluetooth:')) && this.isBluetoothSupported()) {
                    if (navigator.bluetooth && typeof navigator.bluetooth.getDevices === 'function') {
                        const bts = await navigator.bluetooth.getDevices();
                        const cleanId = savedId.replace('webbluetooth:', '');
                        const found = bts.find((b) => b.id === cleanId) || bts[0];
                        if (found) await this.connectBle(found);
                    }
                }

                this.emit('printer:status', { connected: true, deviceId: savedId, deviceName: savedName, type: savedMode });
                return true;
            } catch (_) {
                // Pertahankan status terhubung di antarmuka agar kasir tidak terganggu
                this.emit('printer:status', { connected: true, deviceId: savedId, deviceName: savedName, type: savedMode });
                return true;
            }
        },

        async openWebSerialPort(port, baudRate = 9600) {
            if (!port) return null;
            if (port.writable) {
                this._serialPort = port;
                return port;
            }
            if (this._serialPort && this._serialPort !== port) await this.closeSerial();

            // Retry up to 3 kali dengan jeda jika port masih dilepas oleh renderer sebelumnya (F5 refresh)
            let lastErr = null;
            for (let i = 0; i < 3; i++) {
                try {
                    await port.open({ baudRate: Number(baudRate) || 9600 });
                    this._serialPort = port;
                    return port;
                } catch (e) {
                    lastErr = e;
                    if (port.writable || e.name === 'InvalidStateError') {
                        this._serialPort = port;
                        return port;
                    }
                    await sleep(200 * (i + 1));
                }
            }
            if (!port.writable && lastErr) throw lastErr;
            this._serialPort = port;
            return port;
        },

        async closeSerial() {
            try { if (this._serialPort?.writable) await this._serialPort.close(); } catch (_) {}
            this._serialPort = null;
        },

        async connectBle(device) {
            if (!device?.gatt) throw new Error('Perangkat bukan perangkat BLE.');
            if (this._ble?.device === device && this._ble?.server?.connected && this._ble?.characteristic) return this._ble;
            const server = device.gatt.connected ? device.gatt : await device.gatt.connect();
            let characteristic = null;
            const services = await server.getPrimaryServices();
            for (const s of services) {
                try {
                    const chars = await s.getCharacteristics();
                    characteristic = chars.find((c) => c.properties.writeWithoutResponse) || chars.find((c) => c.properties.write);
                    if (characteristic) break;
                } catch (_) {}
            }
            if (!characteristic) throw new Error('Kanal tulis ESC/POS tidak ditemukan pada perangkat Bluetooth ini.');
            this._ble = { device, server, characteristic };
            return this._ble;
        },

        async writeBle(bytes) {
            if (!this._ble?.characteristic || !this._ble?.server?.connected) {
                if (this._ble?.device) await this.connectBle(this._ble.device);
                else throw new Error('Printer Bluetooth belum terhubung.');
            }
            const c = this._ble.characteristic;
            const write = (c.properties.writeWithoutResponse && c.writeValueWithoutResponse)
                ? (v) => c.writeValueWithoutResponse(v)
                : (v) => c.writeValue(v);

            for (let i = 0; i < bytes.length; i += 20) {
                await write.call(c, bytes.slice(i, i + 20));
                await sleep(15);
            }
        },

        setPaperWidth(w) { localStorage.setItem('thermal_printer_paper', String(w || '58mm')); },
        setBaudRate(b) { localStorage.setItem('thermal_printer_baud_rate', String(b || 9600)); },

        buildReceiptDoc(order, store = {}, width = 32) {
            const L = [];
            const add = (text, o = {}) => L.push({ text: String(text ?? ''), align: o.align || 'left', bold: !!o.bold, size: o.size || 'normal' });
            const addWrapped = (text, o = {}) => wrap(text, o.size === 'large' ? Math.floor(width / 2) : width).forEach((t) => add(t, o));
            const dash = '-'.repeat(width), eq = '='.repeat(width);

            addWrapped((store.name || 'RATIO COFFEE').toUpperCase(), { align: 'center', bold: true, size: 'large' });
            if (store.address) addWrapped(store.address, { align: 'center' });
            if (store.phone) addWrapped('Telp: ' + store.phone, { align: 'center' });
            add(dash);
            add('No Order : ' + (order.order_number || '-'));
            if (order.queue_number) add('Antrean  : ' + order.queue_number);
            add('Kasir    : ' + (order.cashier?.name || 'Kasir'));
            add('Waktu    : ' + (order.created_at ? new Date(order.created_at).toLocaleString('id-ID') : '-'));
            add('Tipe     : ' + (order.order_type === 'takeaway' ? 'Takeaway' : 'Dine In'));
            if (order.table_number) add('Meja     : ' + order.table_number);
            if (order.customer?.name) add('Customer : ' + order.customer.name);
            add(dash);
            for (const item of (order.lines || order.items || [])) {
                addWrapped(item.product_name || item.product?.name || item.name || 'Menu');
                add(row('  ' + item.qty + 'x ' + rupiah(item.unit_price), rupiah(item.subtotal ?? (item.qty * item.unit_price)), width));
                if (item.notes) addWrapped('  * ' + item.notes);
            }
            add(dash);
            add(row('Subtotal', rupiah(order.subtotal), width));
            if (Number(order.discount_amount || 0) > 0) add(row('Diskon', '- ' + rupiah(order.discount_amount), width));
            if (Number(order.tax_amount || 0) > 0) add(row('Pajak', rupiah(order.tax_amount), width));
            add(eq);
            add(row('TOTAL', rupiah(order.total_amount), width), { bold: true });
            add(eq);
            if (order.payment) {
                const labels = { cash: 'Tunai (Cash)', qris: 'QRIS', bank_transfer: 'Transfer Bank', debit_card: 'Kartu Debit', credit_card: 'Kartu Kredit' };
                add(row('Bayar', labels[order.payment.method] || order.payment.method || 'Cash', width));
                if (order.payment.method === 'cash') {
                    add(row('Diterima', rupiah(order.payment.tendered), width));
                    add(row('Kembali', rupiah(order.payment.change_amount), width));
                }
            }
            if (order.notes) { add(dash); addWrapped('Catatan: ' + order.notes); }
            add(dash);
            add('Terima Kasih atas Kunjungan Anda', { align: 'center' });
            add(store.website || 'www.ratiocoffee.my.id', { align: 'center' });
            return L;
        },

        encodeEscPos(doc) {
            const out = [0x1B, 0x40]; // ESC @
            for (const l of doc) {
                out.push(0x1B, 0x61, l.align === 'center' ? 1 : (l.align === 'right' ? 2 : 0));
                out.push(0x1B, 0x45, l.bold ? 1 : 0);
                out.push(0x1D, 0x21, l.size === 'large' ? 0x11 : 0x00);
                out.push(...encodeText(l.text), 0x0A);
            }
            out.push(0x1B, 0x45, 0, 0x1D, 0x21, 0, 0x1B, 0x61, 0);
            out.push(0x1B, 0x64, 4); // Feed 4 lines
            out.push(0x1D, 0x56, 0x42, 0x00); // Cut paper
            return new Uint8Array(out);
        },

        testOrder() {
            return {
                id: 'test', order_number: 'TEST-PRINT', queue_number: 'A001', created_at: new Date().toISOString(),
                order_type: 'dine_in', table_number: 'Meja 01', subtotal: 35000, total_amount: 35000,
                cashier: { name: 'Uji Kasir' }, payment: { method: 'cash', tendered: 50000, change_amount: 15000 },
                lines: [
                    { product_name: 'Espresso Single', qty: 1, unit_price: 15000, subtotal: 15000 },
                    { product_name: 'Croissant Butter', qty: 1, unit_price: 20000, subtotal: 20000 },
                ],
            };
        },

        async printTestReceipt() {
            return this.printReceipt(this.testOrder(), { name: 'RATIO COFFEE', address: 'POS Testing' }, { test: true, force: true });
        },

        // ── Eksekusi Cetak Struk dengan Auto-Resume Cerdas ────────────────────
        async printReceipt(order, store = {}, opts = {}) {
            const savedId = localStorage.getItem('thermal_printer_device_id') || '';
            const savedLabel = localStorage.getItem('thermal_printer_device') || 'Printer';
            const mode = localStorage.getItem('thermal_printer_mode') || 'usb';
            const paperStr = localStorage.getItem('thermal_printer_paper') || '58mm';
            const widthMm = paperStr.includes('80') ? 80 : 58;
            const charsPerLine = widthMm === 80 ? 48 : 32;

            if (!savedId) {
                throw new Error('Belum ada printer yang dipilih. Buka Pengaturan Printer untuk memilih printer.');
            }

            const jobKey = opts.test ? 'test:' + Date.now() : 'order:' + (order?.id || order?.order_number || Date.now());
            if (this._inFlight.has(jobKey)) throw new Error('Struk sedang dikirim. Mohon tunggu...');
            const lastTime = this._recent.get(jobKey);
            if (!opts.force && lastTime && (Date.now() - lastTime < DUPLICATE_COOLDOWN_MS)) {
                throw new Error('Struk baru saja dicetak. Tunggu beberapa detik untuk cetak ulang.');
            }

            this._inFlight.add(jobKey);
            this.emit('printer:job', { key: jobKey, state: 'start' });

            try {
                const doc = this.buildReceiptDoc(order, store, charsPerLine);
                const bytes = this.encodeEscPos(doc);
                let printed = false;
                let printMethod = '';
                let printMsg = '';

                // 1. Coba Bridge Lokal jika mode bridge atau ID diawali bridge:
                if (mode === 'bridge' || savedId.startsWith('bridge:')) {
                    try {
                        const isSerial = savedId.toLowerCase().includes(':serial:') || 
                                         savedId.toLowerCase().includes(':com:') || 
                                         /\bCOM\d+\b/i.test(savedId) || 
                                         /\bCOM\d+\b/i.test(savedLabel);

                        let targetName = '';
                        if (isSerial) {
                            const match = savedId.match(/\bCOM\d+\b/i) || savedLabel.match(/\bCOM\d+\b/i);
                            targetName = match ? match[0].toUpperCase() : savedLabel;
                        } else {
                            if (savedId.startsWith('bridge:win:')) {
                                targetName = savedId.slice('bridge:win:'.length).trim();
                            } else if (savedId.startsWith('bridge:')) {
                                targetName = savedId.slice('bridge:'.length).replace(/^win:/i, '').trim();
                            } else {
                                targetName = savedLabel.replace(/^win:|^com:/i, '').trim();
                            }
                        }
                        const targetKind = isSerial ? 'serial' : 'windows';
                        const baud = Number(localStorage.getItem('thermal_printer_baud_rate') || 9600);

                        try {
                            const res = await this.bridgeFetch('/print/raw', {
                                method: 'POST', timeout: 30000,
                                body: {
                                    target: { kind: targetKind, name: targetName, baudRate: baud },
                                    data_base64: bytesToBase64(bytes)
                                }
                            });
                            if (res && res.ok === false) throw new Error(res.message || 'Bridge gagal mencetak RAW');
                            printed = true;
                            printMethod = 'bridge';
                            printMsg = `Struk dicetak ke ${targetName} via Bridge`;
                        } catch (rawErr) {
                            if (targetKind === 'windows') {
                                const txtRes = await this.bridgeFetch('/print/text', {
                                    method: 'POST', timeout: 30000,
                                    body: {
                                        target: { kind: 'windows', name: targetName },
                                        lines: doc, paperWidthMm: widthMm, charsPerLine: charsPerLine
                                    }
                                });
                                if (txtRes && txtRes.ok === false) throw new Error(txtRes.message || 'Bridge gagal mencetak Text');
                                printed = true;
                                printMethod = 'bridge';
                                printMsg = `Struk dicetak ke ${targetName} via Spooler Windows`;
                            } else {
                                throw rawErr;
                            }
                        }
                    } catch (bridgeErr) {
                        console.warn('[TPM] Bridge print error:', bridgeErr);
                        if (mode === 'bridge' && !this.isSerialSupported() && !this.isBluetoothSupported()) {
                            throw bridgeErr;
                        }
                    }
                }

                // 2. Coba Web Serial (Kabel USB / OTG) jika belum tercetak
                if (!printed && (mode === 'usb' || savedId.startsWith('webserial:')) && this.isSerialSupported()) {
                    try {
                        let port = this._serialPort;
                        if (!port || !port.writable) {
                            const ports = await navigator.serial.getPorts();
                            port = this.matchSerialPort(ports, savedId);
                            if (port) {
                                const baud = Number(localStorage.getItem('thermal_printer_baud_rate') || 9600);
                                port = await this.openWebSerialPort(port, baud);
                            }
                        }

                        if (!port && typeof navigator.serial.requestPort === 'function') {
                            port = await navigator.serial.requestPort();
                            const baud = Number(localStorage.getItem('thermal_printer_baud_rate') || 9600);
                            port = await this.openWebSerialPort(port, baud);
                        }

                        if (port && port.writable) {
                            const writer = port.writable.getWriter();
                            try { await writer.write(bytes); }
                            finally { try { writer.releaseLock(); } catch (_) {} }
                            printed = true;
                            printMethod = 'webserial';
                            printMsg = `Struk dicetak ke ${savedLabel} via USB Serial`;
                        }
                    } catch (serialErr) {
                        console.warn('[TPM] WebSerial error:', serialErr);
                        if (mode === 'usb' && !savedId.startsWith('bridge:')) {
                            throw serialErr;
                        }
                    }
                }

                // 3. Coba Web Bluetooth jika belum tercetak
                if (!printed && (mode === 'bluetooth' || savedId.startsWith('webbluetooth:')) && this.isBluetoothSupported()) {
                    try {
                        if (!this._ble?.characteristic || !this._ble?.server?.connected) {
                            if (this._ble?.device) {
                                await this.connectBle(this._ble.device);
                            } else if (navigator.bluetooth && typeof navigator.bluetooth.getDevices === 'function') {
                                const bts = await navigator.bluetooth.getDevices();
                                const cleanId = savedId.replace('webbluetooth:', '');
                                const dev = bts.find((b) => b.id === cleanId) || bts[0];
                                if (dev) await this.connectBle(dev);
                            }
                        }

                        if (!this._ble?.characteristic) {
                            const dev = await navigator.bluetooth.requestDevice({
                                acceptAllDevices: true,
                                optionalServices: BLE_SERVICES
                            });
                            await this.connectBle(dev);
                        }

                        if (this._ble?.characteristic) {
                            await this.writeBle(bytes);
                            printed = true;
                            printMethod = 'webbluetooth';
                            printMsg = `Struk dicetak ke ${savedLabel} via Bluetooth BLE`;
                        }
                    } catch (bleErr) {
                        console.warn('[TPM] Bluetooth error:', bleErr);
                        throw bleErr;
                    }
                }

                if (printed) {
                    this._recent.set(jobKey, Date.now());
                    this.emit('printer:job', { key: jobKey, state: 'done' });
                    return { success: true, method: printMethod, message: printMsg };
                }

                throw new Error('Tidak dapat mengirim data ke printer fisik. Pastikan printer menyala dan terhubung.');
            } catch (err) {
                this.emit('printer:job', { key: jobKey, state: 'error', error: err.message });
                throw err;
            } finally {
                this._inFlight.delete(jobKey);
            }
        }
    };

    window.ThermalPrinterManager = TPM;

    // Sinkronisasi status saat tab/jendela kembali aktif atau bangun dari tidur (wake)
    if (typeof window !== 'undefined') {
        const kickReconnect = () => { setTimeout(() => TPM.autoReconnect(), 150); };
        window.addEventListener('DOMContentLoaded', kickReconnect);
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            kickReconnect();
        }
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') kickReconnect();
        });
        window.addEventListener('focus', kickReconnect);
        window.addEventListener('pageshow', kickReconnect);
        window.addEventListener('online', kickReconnect);
        window.addEventListener('storage', (e) => {
            if (e.key === 'thermal_printer_device_id' || e.key === 'thermal_printer_device' || e.key === 'thermal_printer_mode') {
                kickReconnect();
            }
        });
        if (bus) {
            bus.onmessage = (e) => {
                if (e.data?.type === 'STATUS_SYNC' && e.data.detail) {
                    window.dispatchEvent(new CustomEvent('printer:status', { detail: e.data.detail }));
                }
            };
        }
    }
})();
