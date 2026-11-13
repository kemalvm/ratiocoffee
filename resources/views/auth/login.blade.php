@extends('layouts.app')
@section('title', 'Ratio Coffee ERP – Login')

@section('content')
<div class="login-wrapper" x-data="loginPage()">

    <div class="login-card" x-show="!mfaRequired" x-cloak>
        <!-- Brand Header -->
        <div class="text-center mb-4">
            <div class="login-logo mx-auto mb-3">☕</div>
            <h1 class="brand-title">Ratio Coffee ERP</h1>
            <p class="brand-subtitle">Executive Control Center &amp; POS System</p>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" novalidate>
            <div class="form-group mb-3">
                <label class="form-label fw-semibold" for="username">Username / Email Akun *</label>
                <div class="position-relative">
                    <span class="input-icon-left">👤</span>
                    <input id="username" x-model="form.username" type="text"
                        class="form-control form-input ps-icon"
                        placeholder="Masukkan Username atau Email"
                        autocomplete="username" :disabled="loading" required />
                </div>
            </div>

            <div class="form-group mb-3">
                <label class="form-label fw-semibold" for="password">Password *</label>
                <div class="position-relative">
                    <span class="input-icon-left">🔒</span>
                    <input id="password" x-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="form-control form-input ps-icon pe-icon"
                        placeholder="••••••••"
                        autocomplete="current-password" :disabled="loading" required />
                    <button type="button" class="input-icon-right btn-icon-toggle"
                        @click="showPassword = !showPassword">
                        <span x-text="showPassword ? '🙈' : '👁️'"></span>
                    </button>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-4" style="font-size:12.5px">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;color:var(--txt-secondary)">
                    <input type="checkbox" x-model="form.remember" style="accent-color:var(--clr-primary)" />
                    Ingat Saya di Perangkat Ini
                </label>
                <a href="#" class="forgot-link">Lupa Password?</a>
            </div>

            <!-- Error Alert -->
            <div x-show="errorMsg" x-cloak class="error-alert mb-3">
                <span>⚠️</span>
                <span x-text="errorMsg"></span>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg" :disabled="loading">
                <span x-show="loading">⏳ Memproses Verifikasi...</span>
                <span x-show="!loading">🚀 Masuk ke ERP Control Center</span>
            </button>
        </form>

        <div class="login-footer mt-4">
            <p>© <span x-text="new Date().getFullYear()"></span> Ratio Coffee ERP · Executive Control Center v2.0</p>
        </div>
    </div>

    <!-- MFA Screen -->
    <div class="login-card" x-show="mfaRequired" x-cloak>
        <div class="text-center mb-4">
            <div class="login-logo mx-auto mb-3" style="background:linear-gradient(135deg,#6366f1,#4f46e5)">🔐</div>
            <h1 class="brand-title">Verifikasi Keamanan (MFA)</h1>
            <p class="brand-subtitle">Pilih metode verifikasi untuk melanjutkan login</p>
        </div>

        <!-- Method Pills -->
        <div class="d-flex gap-2 justify-content-center flex-wrap mb-3">
            <template x-for="m in availableMethods" :key="m">
                <button type="button" @click="selectedMethod = m; mfaCode = ''"
                    :class="['mfa-pill-btn', selectedMethod === m ? 'active' : '']">
                    <span x-text="m === 'pin' ? '🔢 PIN 8 Angka' : (m === 'otp' ? '✉️ OTP Email' : '🖐️ Sidik Jari')"></span>
                </button>
            </template>
        </div>

        <div x-show="mfaError || errorMsg" class="error-alert mb-3">
            <span>⚠️</span><span x-text="mfaError || errorMsg"></span>
        </div>

        <!-- PIN -->
        <div x-show="selectedMethod === 'pin'" class="mt-3">
            <label class="form-label fw-semibold">Masukkan PIN Keamanan 8 Angka *</label>
            <input x-model="mfaCode" type="password" maxlength="8"
                class="form-control form-input text-center"
                style="font-size:22px;letter-spacing:6px;font-weight:900"
                placeholder="••••••••" @keyup.enter="submitMfa('pin')" autofocus />
            <div class="text-center mt-1" style="font-size:11px;color:var(--txt-muted)">
                Digit terisi: <strong x-text="mfaCode.length + '/8'"></strong>
            </div>
            <button type="button" class="btn btn-primary w-100 btn-lg mt-3"
                @click="submitMfa('pin')" :disabled="mfaCode.length !== 8 || loading">
                <span x-show="loading">⏳ Memverifikasi...</span>
                <span x-show="!loading">✅ Verifikasi PIN</span>
            </button>
        </div>

        <!-- OTP -->
        <div x-show="selectedMethod === 'otp'" class="mt-3">
            <div class="text-center mb-3" style="font-size:12.5px;color:var(--txt-secondary)">
                Kode OTP dikirim ke <strong x-text="maskedEmail"></strong>
            </div>
            <input x-model="mfaCode" type="text" maxlength="6"
                class="form-control form-input text-center"
                style="font-size:24px;letter-spacing:8px;font-weight:900"
                placeholder="123456" @keyup.enter="submitMfa('otp')" />
            <div class="d-flex justify-content-between align-items-center mt-2">
                <button type="button" class="forgot-link btn-plain" @click="resendOtp" :disabled="resendingOtp">
                    <span x-show="resendingOtp">⏳ Mengirim...</span>
                    <span x-show="!resendingOtp">🔄 Kirim Ulang OTP</span>
                </button>
                <span x-show="otpMessage" style="font-size:11px;color:var(--clr-success);font-weight:700" x-text="otpMessage"></span>
            </div>
            <button type="button" class="btn btn-primary w-100 btn-lg mt-3"
                @click="submitMfa('otp')" :disabled="mfaCode.length !== 6 || loading">
                <span x-show="loading">⏳ Memverifikasi...</span>
                <span x-show="!loading">✅ Verifikasi OTP</span>
            </button>
        </div>

        <!-- Fingerprint -->
        <div x-show="selectedMethod === 'fingerprint'" class="mt-3 text-center">
            <div class="p-4 rounded-3 mb-3" style="background:var(--bg-muted);border:1px dashed var(--border)">
                <div style="font-size:48px">🖐️</div>
                <div class="fw-bold mt-2">Sensor Biometrik Sidik Jari</div>
                <div style="font-size:12px;color:var(--txt-muted);margin-top:4px">Klik tombol di bawah untuk memindai sidik jari</div>
            </div>
            <button type="button" class="btn btn-success w-100 btn-lg" @click="verifyBiometric" :disabled="loading">
                <span x-show="loading">⏳ Memindai...</span>
                <span x-show="!loading">🖐️ Pindai Sidik Jari</span>
            </button>
        </div>

        <div class="text-center mt-4">
            <button type="button" class="btn-plain" style="color:var(--txt-muted);font-size:12.5px"
                @click="mfaRequired = false; mfaCode = ''; mfaError = ''">
                ← Batal &amp; Kembali ke Login
            </button>
        </div>

        <div class="login-footer mt-4">
            <p>© <span x-text="new Date().getFullYear()"></span> Ratio Coffee ERP · Executive Control Center v2.0</p>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function loginPage() {
    return {
        form: { username: '', password: '', remember: true },
        showPassword: false,
        loading: false,
        errorMsg: '',

        // MFA
        mfaRequired: false,
        mfaToken: '',
        availableMethods: [],
        selectedMethod: 'pin',
        maskedEmail: '',
        mfaCode: '',
        mfaError: '',
        resendingOtp: false,
        otpMessage: '',

        async handleLogin() {
            this.errorMsg = '';
            this.loading = true;
            try {
                const res = await axios.post('/api/v1/auth/login', this.form);
                const data = res.data;
                if (data.mfa_required) {
                    this.mfaRequired = true;
                    this.mfaToken = data.mfa_token;
                    this.availableMethods = data.available_methods || ['pin'];
                    this.maskedEmail = data.masked_email || '';
                    this.selectedMethod = this.availableMethods[0] || 'pin';
                    this.mfaCode = '';
                    return;
                }
                this.storeAuthAndRedirect(data);
            } catch (err) {
                this.errorMsg = err.response?.data?.message || 'Login gagal. Periksa username & password Anda.';
            } finally {
                this.loading = false;
            }
        },

        async submitMfa(method) {
            if (!this.mfaCode) return;
            this.mfaError = '';
            this.loading = true;
            try {
                const res = await axios.post('/api/v1/auth/mfa/verify', {
                    mfa_token: this.mfaToken,
                    method: method || this.selectedMethod,
                    code: this.mfaCode,
                });
                this.storeAuthAndRedirect(res.data);
            } catch (err) {
                this.mfaError = err.response?.data?.message || 'Verifikasi MFA gagal.';
            } finally {
                this.loading = false;
            }
        },

        async resendOtp() {
            this.resendingOtp = true;
            this.otpMessage = '';
            try {
                const res = await axios.post('/api/v1/auth/mfa/otp', { mfa_token: this.mfaToken });
                this.otpMessage = res.data.message || 'OTP terkirim!';
            } catch (err) {
                this.mfaError = err.response?.data?.message || 'Gagal mengirim ulang OTP.';
            } finally {
                this.resendingOtp = false;
            }
        },

        async verifyBiometric() {
            this.mfaError = '';
            this.loading = true;
            try {
                let cred = { id: 'bio-cred-token', type: 'webauthn' };
                if (window.PublicKeyCredential && navigator.credentials?.get) {
                    try {
                        const c = await navigator.credentials.get({
                            publicKey: { challenge: new Uint8Array([1,2,3,4,5,6,7,8]), timeout: 60000, userVerification: 'preferred' }
                        });
                        if (c) {
                            cred = {
                                id: c.id,
                                type: c.type || 'webauthn',
                                signature: c.response?.signature ? btoa(String.fromCharCode(...new Uint8Array(c.response.signature))) : 'sig_' + c.id,
                                clientDataJSON: c.response?.clientDataJSON ? btoa(String.fromCharCode(...new Uint8Array(c.response.clientDataJSON))) : null,
                            };
                        }
                    } catch(e) {}
                }
                const res = await axios.post('/api/v1/auth/mfa/verify', {
                    mfa_token: this.mfaToken, method: 'fingerprint', credential: cred, code: this.mfaCode
                });
                this.storeAuthAndRedirect(res.data);
            } catch (err) {
                this.mfaError = err.response?.data?.message || 'Gagal memverifikasi sidik jari.';
            } finally {
                this.loading = false;
            }
        },

        storeAuthAndRedirect(data) {
            const token = data.access_token || data.token;
            if (token) {
                localStorage.setItem('erp_token', token);
                localStorage.setItem('erp_user', JSON.stringify(data.user || {}));
                axios.defaults.headers.common['Authorization'] = 'Bearer ' + token;
            }
            const slug = data.user?.role?.slug;
            let dest = '/dashboard';
            if (slug === 'employee') dest = '/my-dashboard';
            if (slug === 'consultant') dest = '/research-product';
            window.location.href = dest;
        }
    }
}
</script>
@endpush
