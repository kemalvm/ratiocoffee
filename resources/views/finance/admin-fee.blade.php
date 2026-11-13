@extends('layouts.erp')
@section('title', 'Pengaturan Biaya Admin & Revenue Sharing – Ratio Coffee ERP')

@section('page-content')
<div x-data="adminFeeApp()" x-init="init()" x-cloak class="container-fluid p-0">
    
    <!-- Top Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-bold m-0 text-primary d-flex align-items-center gap-2" style="font-size: 1.4rem;">
                <span>💰</span> Pengaturan Biaya Admin &amp; Revenue Sharing
            </h3>
            <p class="text-muted small m-0 mt-1">Sistem Terintegrasi CEO &amp; Operator Toko untuk Pengajuan Rate, Tanggal Efektif, &amp; Pembayaran Revenue Sharing</p>
        </div>

        <!-- Real Logged-in User Account Badge -->
        <div class="d-flex align-items-center gap-2">
            <span class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill fw-bold shadow-sm"
                  :style="isOperator ? 'background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); font-size: 12px;' : 'background: rgba(234, 88, 12, 0.1); color: #ea580c; border: 1px solid rgba(234, 88, 12, 0.25); font-size: 12px;'"
                  x-text="isOperator ? '🛠️ Akun: Operator Toko' : '👑 Akun: CEO / Management'">
            </span>
        </div>
    </div>

    <!-- Business Policy Alert Banner -->
    <div class="alert border-0 shadow-sm mb-4 d-flex align-items-center gap-3" style="background: rgba(59, 130, 246, 0.06); border-left: 4px solid #3b82f6 !important; border-radius: 12px; padding: 14px 18px;">
        <span class="fs-4">💡</span>
        <div class="small text-muted">
            <strong>Standar Kebijakan Ratio Coffee:</strong> Biaya Admin Rate (CEO &amp; Operator), Pajak PB1, dan Service Charge dipotong langsung dari alokasi <strong>HPP / Margin Operasional Toko</strong> dan <u>TIDAK dibebankan tambahan biaya kepada pelanggan kasir</u>.
        </div>
    </div>

    <!-- Grid Top: 2 Cards (Active Rate & Proposal Form / Operator Portal) -->
    <div class="row g-4 mb-4">
        <!-- Active Rate & Effective Date KPI Card -->
        <div class="col-12 col-lg-4">
            <div class="card h-100 p-4 border-0 shadow-sm d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, rgba(234, 88, 12, 0.04), rgba(234, 88, 12, 0.08)); border-left: 4px solid #ea580c !important; border-radius:14px">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:0.5px">Rate Aktif saat Ini</span>
                        <span class="d-inline-flex align-items-center gap-1 px-2.5 py-1 rounded-pill fw-bold text-uppercase" style="background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); font-size: 10.5px; letter-spacing: 0.5px;">
                            ● AKTIF
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <span class="display-4 fw-bold text-primary" x-text="(settings.rate || 0) + '%'">0%</span>
                        <span class="text-muted small">per transaksi</span>
                    </div>

                    <!-- Effective Date Badge -->
                    <div class="p-3 rounded-3 mt-3" style="background: var(--bg-muted); border: 1px dashed rgba(234, 88, 12, 0.3);">
                        <div class="small text-muted fw-medium">📅 Tanggal Mulai Efektif:</div>
                        <div class="fw-bold text-dark fs-6 mt-0.5" x-text="settings.effective_date ? formatDate(settings.effective_date, 'full') : 'Belum Ditentukan'"></div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-muted small">
                    ℹ️ Transaksi sebelum tanggal efektif bebas dari potongan biaya admin.
                </div>
            </div>
        </div>

        <!-- Proposal Form Card (CEO Form or Operator Review Portal) -->
        <div class="col-12 col-lg-8">
            <!-- CEO VIEW: Form Pengajuan Rate & Kalender Efektif -->
            <template x-if="isCeo">
                <div class="card p-4 border-0 shadow-sm h-100" style="border-radius:14px">
                    <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-dark">
                        <span>📤</span> Pengajuan Perubahan Rate Biaya Admin &amp; Kalender Efektif
                    </h5>
                    <p class="text-muted small mb-3">CEO mengajukan rate baru &amp; menentukan tanggal dimulainya perhitungan biaya admin untuk disetujui Operator.</p>

                    <form @submit.prevent="submitProposal()">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Angka Pengajuan Baru (%) <span class="text-danger">*</span></label>
                                <input x-model.number="proposeForm.proposed_rate" type="number" step="0.1" min="0" max="100" class="form-input" placeholder="mis. 5.0" required />
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">📅 Tanggal Mulai Efektif (Kalender) <span class="text-danger">*</span></label>
                                <input x-model="proposeForm.effective_date" type="date" class="form-input" required />
                                <span class="form-text text-muted" style="font-size:11px">Perhitungan revenue sharing hanya berlaku mulai tanggal ini.</span>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Catatan CEO untuk Operator</label>
                                <textarea x-model="proposeForm.notes" class="form-input" rows="2" placeholder="Jelaskan alasan usulan perubahan biaya admin..."></textarea>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-start">
                            <button type="submit" class="btn px-4 py-2.5 fw-bold text-white shadow-sm d-inline-flex align-items-center gap-2" :disabled="proposing" style="background: var(--clr-primary, #ea580c); border: none; border-radius: 10px; font-size: 13.5px; transition: all 0.2s ease;">
                                <span x-show="proposing">⏳ Mengirim...</span>
                                <span x-show="!proposing" class="d-inline-flex align-items-center gap-2">
                                    <span>📤</span> Kirim Pengajuan ke Operator
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>

            <!-- OPERATOR VIEW: Info Card & Direct Action Guide -->
            <template x-if="isOperator">
                <div class="card p-4 border-0 shadow-sm h-100" style="background: rgba(16, 185, 129, 0.04); border-left: 4px solid #10b981 !important; border-radius:14px">
                    <h5 class="fw-bold mb-1 d-flex align-items-center gap-2 text-dark">
                        <span>🛠️</span> Portal Review &amp; Approval Operator Toko
                    </h5>
                    <p class="text-muted small mb-3">Sebagai <strong>Operator Toko</strong>, Anda bertugas meninjau pengajuan rate biaya admin dan tanggal efektif yang diajukan oleh CEO.</p>
                    <div class="p-3 rounded bg-white border shadow-sm">
                        <div class="fw-bold text-dark small mb-1">📌 Panduan Operator:</div>
                        <div class="small text-muted mb-2">Lihat daftar usulan CEO pada tabel <strong>Riwayat Pengajuan</strong> di bawah:</div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-success px-2.5 py-1.5">✅ Setuju</span> <span class="small text-muted me-2">: Mengaktifkan rate &amp; tanggal efektif baru secara real-time.</span>
                            <span class="badge bg-info text-white px-2.5 py-1.5">✏️ Revisi</span> <span class="small text-muted me-2">: Meminta CEO mengajukan ulang.</span>
                            <span class="badge bg-danger px-2.5 py-1.5">❌ Tolak</span> <span class="small text-muted">: Membatalkan pengajuan CEO.</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Revenue Sharing Expense Accumulation Card & Status Flow -->
    <div class="card p-4 border-0 shadow-sm mb-4" style="background: var(--bg-surface, #ffffff); border-radius:14px">
        <!-- Card Header -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between pb-3 mb-4 border-bottom gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill mb-2" 
                     style="background: rgba(124, 58, 237, 0.1); border: 1px solid rgba(124, 58, 237, 0.25); color: #7c3aed; font-size: 11.5px; font-weight: 600; letter-spacing: 0.3px;">
                    <span>⚡</span> OTOMATIS TERKONEKSI KE MODUL EXPENSE
                </div>
                <h5 class="fw-bold m-0 d-flex align-items-center gap-2 text-dark">
                    <span>💵</span> Akumulasi Tagihan Revenue Sharing Operator
                </h5>
                <div class="text-muted small mt-1">
                    Hitungan real-time transaksi sejak <strong><span x-text="revSharing.effective_date ? formatDate(revSharing.effective_date, 'full') : 'Tanggal Efektif'"></span></strong>.
                </div>
            </div>

            <!-- Status Indicator Badge (Right) -->
            <div class="flex-shrink-0">
                <template x-if="expenseStatus === 'pending' || expenseStatus === 'unpaid'">
                    <span class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill fw-semibold" 
                          style="background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.25); font-size: 12px;">
                        <span>⚠️</span> Status: <strong>BELUM DIBAYAR CEO</strong>
                    </span>
                </template>
                <template x-if="expenseStatus === 'paid'">
                    <span class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill fw-semibold" 
                          style="background: rgba(14, 165, 233, 0.1); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.25); font-size: 12px;">
                        <span>💳</span> Status: <strong>PAID (Menunggu Konfirmasi Operator)</strong>
                    </span>
                </template>
                <template x-if="expenseStatus === 'confirmed'">
                    <span class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill fw-semibold" 
                          style="background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); font-size: 12px;">
                        <span>✅</span> Status: <strong>CONFIRMED (Terkonfirmasi Operator)</strong>
                    </span>
                </template>
            </div>
        </div>

        <!-- 3-Column Fit Grid: Metric 1 | Metric 2 | Role Action Container -->
        <div class="row g-3 align-items-stretch">
            <!-- METRIC 1: TOTAL OMZET TRANSAKSI -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="p-4 rounded-3 border h-100 d-flex flex-column justify-content-between shadow-xs" 
                     style="background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(124, 58, 237, 0.07)); border-color: rgba(124, 58, 237, 0.2) !important; border-radius: 14px; padding: 22px !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 11px; letter-spacing: 0.5px; color: #64748b !important;">Total Omzet Transaksi</span>
                        <span class="badge px-2.5 py-1 rounded-pill fw-semibold" style="background: rgba(124, 58, 237, 0.12); color: #7c3aed; font-size: 10.5px;" x-text="'Rate: ' + (revSharing.rate || 0) + '%'"></span>
                    </div>
                    <div class="my-2">
                        <div class="fw-extrabold text-dark" style="font-size: 1.5rem; font-family:'Plus Jakarta Sans', sans-serif;" x-text="'Rp ' + formatNumber(revSharing.total_revenue || 0)">Rp 0</div>
                    </div>
                    <div class="pt-2.5 border-top small text-muted" style="font-size: 12px; border-color: rgba(124, 58, 237, 0.15) !important;">
                        Mulai Efektif: <span class="fw-bold text-dark" x-text="revSharing.effective_date ? formatDate(revSharing.effective_date, 'short') : '-'"></span>
                    </div>
                </div>
            </div>

            <!-- METRIC 2: TOTAL AKUMULASI REVENUE SHARING -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="p-4 rounded-3 border h-100 d-flex flex-column justify-content-between shadow-xs" 
                     style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.03), rgba(16, 185, 129, 0.07)); border-color: rgba(16, 185, 129, 0.2) !important; border-radius: 14px; padding: 22px !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 11px; letter-spacing: 0.5px; color: #64748b !important;">Total Akumulasi Revenue Sharing</span>
                        <span class="badge px-2.5 py-1 rounded-pill fw-semibold" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 10.5px;">Expense Sync</span>
                    </div>
                    <div class="my-2">
                        <div class="fw-extrabold text-success" style="font-size: 1.5rem; font-family:'Plus Jakarta Sans', sans-serif;" x-text="'Rp ' + formatNumber(revSharing.total_sharing || 0)">Rp 0</div>
                    </div>
                    <div class="pt-2.5 border-top small text-muted" style="font-size: 12px; border-color: rgba(16, 185, 129, 0.15) !important;">
                        Otomatis masuk ke modul Expense
                    </div>
                </div>
            </div>

            <!-- ACTION CONTAINER: Strictly Separated CEO vs Operator Action -->
            <div class="col-12 col-md-12 col-lg-4 d-flex align-items-center justify-content-lg-end mt-3 mt-lg-0">
                <!-- CEO ACTIONS ONLY -->
                <template x-if="isCeo">
                    <div class="w-100 d-flex justify-content-lg-end">
                        <!-- Button when unpaid/pending -->
                        <template x-if="expenseStatus === 'pending' || expenseStatus === 'unpaid'">
                            <button class="btn px-4 py-2.5 fw-bold text-white shadow-sm d-inline-flex align-items-center justify-content-center gap-2" 
                                    @click="paySharing()" :disabled="paying" 
                                    style="background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 10px; font-size: 13.5px; transition: all 0.2s ease;">
                                <span x-show="paying">⏳ Memproses...</span>
                                <span x-show="!paying" class="d-inline-flex align-items-center gap-2">
                                    <span>💳</span> Kirim Pembayaran ke Operator
                                </span>
                            </button>
                        </template>

                        <!-- Text badge when paid (waiting for operator) -->
                        <template x-if="expenseStatus === 'paid'">
                            <div class="text-center text-info small fw-semibold px-3.5 py-2.5 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25 w-100" style="max-width: 320px;">
                                <span>💳</span> <strong>Pembayaran Dikirim CEO</strong><br/>
                                <span class="text-muted" style="font-size: 11.5px;">Menunggu Konfirmasi Operator Toko</span>
                            </div>
                        </template>

                        <!-- Text badge when confirmed -->
                        <template x-if="expenseStatus === 'confirmed'">
                            <div class="text-center text-success small fw-semibold px-3.5 py-2.5 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 w-100" style="max-width: 320px;">
                                <span>🎉</span> <strong>Revenue Sharing Lunas</strong><br/>
                                <span class="text-muted" style="font-size: 11.5px;">Terkonfirmasi oleh Operator Toko</span>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- OPERATOR ACTIONS ONLY -->
                <template x-if="isOperator">
                    <div class="w-100 d-flex justify-content-lg-end">
                        <!-- Text badge when CEO hasn't paid yet -->
                        <template x-if="expenseStatus === 'pending' || expenseStatus === 'unpaid'">
                            <div class="text-center text-muted small fw-semibold px-3.5 py-2.5 bg-light rounded-3 border w-100" style="max-width: 320px;">
                                <span>⏳</span> Menunggu CEO melakukan pembayaran tagihan.
                            </div>
                        </template>

                        <!-- Confirm button when CEO paid -->
                        <template x-if="expenseStatus === 'paid'">
                            <button class="btn px-4 py-2.5 fw-bold text-white shadow-sm d-inline-flex align-items-center justify-content-center gap-2" 
                                    @click="confirmSharing()" :disabled="confirming" 
                                    style="background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 10px; font-size: 13.5px; transition: all 0.2s ease;">
                                <span x-show="confirming">⏳ Memverifikasi...</span>
                                <span x-show="!confirming" class="d-inline-flex align-items-center gap-2">
                                    <span>✅</span> Confirm Pembayaran (Operator)
                                </span>
                            </button>
                        </template>

                        <!-- Text badge when confirmed -->
                        <template x-if="expenseStatus === 'confirmed'">
                            <div class="text-center text-success small fw-semibold px-3.5 py-2.5 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 w-100" style="max-width: 320px;">
                                <span>🎉</span> <strong>Pembayaran Terkonfirmasi</strong><br/>
                                <span class="text-muted" style="font-size: 11.5px;">Telah dikonfirmasi Lunas</span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Tab Navigation & History Section -->
    <div class="card border-0 shadow-sm overflow-hidden mb-4" style="border-radius:14px">
        <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm px-3 py-2 fw-bold" 
                        :class="activeTab==='proposals' ? 'btn-primary shadow-sm' : 'btn-outline-secondary'"
                        @click="activeTab='proposals'" style="border-radius:8px">
                    📄 Riwayat Pengajuan Rate
                    <span class="badge bg-warning text-dark ms-1" x-show="pendingCount() > 0" x-text="pendingCount()"></span>
                </button>
                <button type="button" class="btn btn-sm px-3 py-2 fw-bold" 
                        :class="activeTab==='keterangan' ? 'btn-primary shadow-sm' : 'btn-outline-secondary'"
                        @click="activeTab='keterangan'" style="border-radius:8px">
                    📝 Keterangan &amp; Feedback Operator
                    <span class="badge bg-info text-white ms-1" x-show="feedbackCount() > 0" x-text="feedbackCount()"></span>
                </button>
            </div>
            <div>
                <span class="badge bg-white text-muted border px-3 py-2" x-text="proposals.length + ' Total Record'"></span>
            </div>
        </div>

        <!-- TAB 1: Proposals History Table -->
        <div x-show="activeTab === 'proposals'">
            <div class="table-responsive">
                <table class="data-table align-middle">
                    <thead>
                        <tr style="background:var(--bg-muted, #f8fafc)">
                            <th class="ps-4">Tgl Pengajuan</th>
                            <th>📅 Tgl Efektif</th>
                            <th>Pengaju</th>
                            <th>Rate Lama</th>
                            <th>Rate Diajukan</th>
                            <th>Status</th>
                            <th>Catatan CEO</th>
                            <th>Feedback Operator</th>
                            <th class="text-end pe-4" x-text="isOperator ? 'Aksi Operator' : 'Status Approval'"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr><td colspan="9" class="text-center py-5 text-muted">⏳ Memuat daftar pengajuan...</td></tr>
                        </template>
                        <template x-if="!loading && proposals.length === 0">
                            <tr><td colspan="9" class="text-center py-5 text-muted">Belum ada riwayat pengajuan biaya admin.</td></tr>
                        </template>
                        <template x-for="p in proposals" :key="p.id">
                            <tr class="border-bottom">
                                <td class="ps-4 text-nowrap" x-text="formatDate(p.created_at, 'full')"></td>
                                <td class="fw-bold text-primary text-nowrap" x-text="p.effective_date ? formatDate(p.effective_date, 'full') : '-'"></td>
                                <td class="fw-bold text-dark" x-text="p.proposer_name || 'CEO'"></td>
                                <td class="text-muted" x-text="(p.current_rate || 0) + '%'"></td>
                                <td>
                                    <span class="fw-bold text-primary fs-6" x-text="(p.proposed_rate || 0) + '%'"></span>
                                </td>
                                <td>
                                    <span :class="['badge', statusClass(p.status)]" x-text="statusLabel(p.status)"></span>
                                </td>
                                <td class="small text-muted" style="max-width: 180px;" x-text="p.notes || '-'"></td>
                                <td class="small text-muted" style="max-width: 180px;" x-text="p.operator_notes || '-'"></td>
                                
                                <!-- Action Column: Role-based rendering -->
                                <td class="text-end pe-4">
                                    <!-- OPERATOR ACTION BUTTONS -->
                                    <template x-if="isOperator">
                                        <div>
                                            <template x-if="p.status === 'pending'">
                                                <div class="d-flex gap-1.5 justify-content-end">
                                                    <button class="btn btn-sm px-2.5 py-1 fw-semibold text-success d-inline-flex align-items-center gap-1" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 6px; font-size: 11.5px;" title="Setujui Pengajuan" @click="review(p, 'approved')">✅ Setuju</button>
                                                    <button class="btn btn-sm px-2.5 py-1 fw-semibold text-info d-inline-flex align-items-center gap-1" style="background: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.25); border-radius: 6px; font-size: 11.5px;" title="Minta Revisi" @click="review(p, 'revised')">✏️ Revisi</button>
                                                    <button class="btn btn-sm px-2.5 py-1 fw-semibold text-danger d-inline-flex align-items-center gap-1" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 6px; font-size: 11.5px;" title="Tolak Pengajuan" @click="review(p, 'rejected')">❌ Tolak</button>
                                                </div>
                                            </template>
                                            <template x-if="p.status !== 'pending'">
                                                <span class="badge bg-secondary text-white">Selesai</span>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- CEO STATUS DISPLAY ONLY -->
                                    <template x-if="isCeo">
                                        <div>
                                            <span x-show="p.status === 'pending'" class="badge badge-warning px-2.5 py-1">⏳ Menunggu Review Operator</span>
                                            <span x-show="p.status === 'approved'" class="badge badge-success px-2.5 py-1">✅ Disetujui Operator</span>
                                            <span x-show="p.status === 'revised'" class="badge badge-info text-white px-2.5 py-1">✏️ Perlu Revisi</span>
                                            <span x-show="p.status === 'rejected'" class="badge badge-danger px-2.5 py-1">❌ Ditolak Operator</span>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Tab Keterangan & Feedback Operator -->
        <div x-show="activeTab === 'keterangan'" class="p-4">
            <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                <span>📝</span> Catatan &amp; Feedback Hasil Peninjauan Operator
            </h6>
            <div class="row g-3">
                <template x-if="getFeedbackItems().length === 0">
                    <div class="col-12 text-center py-4 text-muted">Belum ada feedback / catatan dari Operator untuk CEO.</div>
                </template>
                <template x-for="item in getFeedbackItems()" :key="item.id">
                    <div class="col-12 col-md-6">
                        <div class="p-3.5 rounded-3 border shadow-sm h-100" style="background:var(--bg-surface, #ffffff); border-radius:10px">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span :class="['badge', statusClass(item.status)]" x-text="statusLabel(item.status)"></span>
                                <span class="small text-muted" x-text="formatDate(item.reviewed_at || item.updated_at, 'full')"></span>
                            </div>
                            <div class="fw-bold text-dark mb-1" x-text="'Pengajuan Rate: ' + item.proposed_rate + '% (Efektif: ' + (item.effective_date || '-') + ')'"></div>
                            <div class="p-2 rounded bg-light border mb-2" style="font-size:12px; color:var(--txt-secondary)">
                                <strong>Catatan CEO:</strong> <span x-text="item.notes || 'Tidak ada catatan.'"></span>
                            </div>
                            <div class="p-2 rounded border" style="background:rgba(59, 130, 246, 0.05); font-size:12.5px; border-color:rgba(59, 130, 246, 0.2) !important">
                                <strong class="text-primary">🛠️ Feedback Operator:</strong>
                                <div class="fw-semibold text-dark mt-1" x-text="item.operator_notes || 'Operator menyetujui tanpa catatan tambahan.'"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function adminFeeApp() {
    return {
        activeTab: 'proposals',
        loading: false, proposing: false, reviewing: false, paying: false, confirming: false,
        settings: { rate: 0, effective_date: '' }, 
        revSharing: { rate: 0, effective_date: '', total_revenue: 0, total_sharing: 0, expense: null },
        expenseStatus: 'pending',
        proposals: [],
        proposeForm: { 
            proposed_rate: '', 
            effective_date: new Date().toISOString().split('T')[0], 
            notes: '' 
        },
        userRole: (JSON.parse(localStorage.getItem('erp_user') || '{}')?.role?.slug || 'ceo'),

        get isOperator() { 
            const role = (this.userRole || '').toLowerCase();
            return role === 'operator' || role === 'store_operator' || role === 'operator_toko'; 
        },
        get isCeo() { return !this.isOperator; },

        pendingCount() {
            return this.proposals.filter(p => p.status === 'pending').length;
        },

        feedbackCount() {
            return this.proposals.filter(p => p.status !== 'pending').length;
        },

        getFeedbackItems() {
            return this.proposals.filter(p => p.status !== 'pending' || p.operator_notes);
        },

        async init() { await this.load(); },

        async load() {
            this.loading = true;
            try {
                const [r1, r2, r3] = await Promise.all([
                    axios.get('/api/v1/admin-fee/settings'),
                    axios.get('/api/v1/admin-fee/proposals'),
                    axios.get('/api/v1/admin-fee/revenue-sharing')
                ]);
                this.settings = r1.data ?? {};
                this.proposals = r2.data.data ?? r2.data ?? [];
                this.revSharing = r3.data ?? {};
                this.expenseStatus = this.revSharing.expense?.status || 'pending';
            } catch(e) { console.error(e); }
            finally { this.loading = false; }
        },

        statusLabel(s) { 
            return { pending:'Menunggu', approved:'Disetujui', rejected:'Ditolak', revised:'Perlu Revisi' }[s] || s; 
        },
        statusClass(s) { 
            return { pending:'badge-warning', approved:'badge-success', rejected:'badge-danger', revised:'badge-info' }[s] || ''; 
        },

        async submitProposal() {
            if (!this.proposeForm.proposed_rate) { toast('Masukkan angka rate', 'error'); return; }
            if (!this.proposeForm.effective_date) { toast('Pilih tanggal mulai efektif', 'error'); return; }
            
            this.proposing = true;
            try {
                await axios.post('/api/v1/admin-fee/proposals', this.proposeForm);
                toast('Pengajuan rate & tanggal efektif berhasil dikirim ke Operator!');
                this.proposeForm = { 
                    proposed_rate: '', 
                    effective_date: new Date().toISOString().split('T')[0], 
                    notes: '' 
                };
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal mengirim pengajuan', 'error');
            } finally { this.proposing = false; }
        },

        async review(p, status) {
            let notes = '';
            if (status === 'revised' || status === 'rejected') {
                notes = prompt('Masukkan catatan/alasan untuk CEO:') || '';
            }

            this.reviewing = true;
            try {
                await axios.put(`/api/v1/admin-fee/proposals/${p.id}/review`, { status, operator_notes: notes });
                toast('Status pengajuan diperbarui oleh Operator!');
                await this.load();
            } catch(e) {
                toast('Gagal memperbarui status', 'error');
            } finally { this.reviewing = false; }
        },

        async paySharing() {
            this.paying = true;
            try {
                const r = await axios.post('/api/v1/admin-fee/pay-sharing');
                toast(r.data?.message || 'Pembayaran berhasil dikirim! Status: Paid');
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal memproses pembayaran', 'error');
            } finally { this.paying = false; }
        },

        async confirmSharing() {
            if (!this.revSharing.expense?.id) return;
            this.confirming = true;
            try {
                const r = await axios.put(`/api/v1/admin-fee/confirm-sharing/${this.revSharing.expense.id}`);
                toast(r.data?.message || 'Pembayaran berhasil dikonfirmasi Operator!');
                await this.load();
            } catch(e) {
                toast(e.response?.data?.message || 'Gagal mengkonfirmasi pembayaran', 'error');
            } finally { this.confirming = false; }
        },

        formatNumber(n) { return new Intl.NumberFormat('id-ID').format(n || 0); },
        formatDate(str, mode = 'short') {
            if (!str) return '-';
            const d = new Date(str);
            if (isNaN(d)) return str;
            if (mode === 'full') {
                return d.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            }
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }
    }
}
</script>
@endpush
