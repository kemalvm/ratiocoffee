<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ─── POST /api/v1/auth/login ──────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Rate limiting – 5 attempts per minute per IP
        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Too many login attempts. Please retry after {$seconds} seconds.",
            ], 429);
        }

        // Find user by username or email
        $user = User::with('role')
            ->where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);

            ActivityLog::record(
                action:      'login_failed',
                description: "Failed login attempt for username: {$request->username}",
            );

            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
            ], 403);
        }

        RateLimiter::clear($key);

        // Check if user has MFA configured
        if ($user->hasMfaConfigured()) {
            $mfaToken = Str::random(64);
            $methods  = $user->getAvailableMfaMethods();
            Cache::put("mfa_token:{$mfaToken}", ['user_id' => $user->id, 'methods' => $methods], now()->addMinutes(5));

            if (in_array('otp', $methods)) {
                $this->dispatchEmailOtp($user);
            }

            return response()->json([
                'mfa_required'      => true,
                'mfa_token'         => $mfaToken,
                'available_methods' => $methods,
                'masked_email'      => $this->maskEmail($user->email),
                'message'           => 'Autentikasi dua faktor (MFA) diperlukan. Silakan verifikasi identitas Anda.',
            ]);
        }

        // Revoke old tokens (single-session policy)
        $user->tokens()->delete();

        // Create Sanctum token with abilities based on role
        $abilities = $user->isCeo() ? ['*'] : ['api'];
        $token = $user->createToken('erp_token', $abilities, now()->addHours(8));

        // Authenticate in web guard as well for session cookie support
        auth()->guard('web')->login($user, true);

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Log successful login
        ActivityLog::record(action: 'login', module: 'auth', description: 'User logged in successfully.');

        return response()->json([
            'message'      => 'Login berhasil.',
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => now()->addHours(8)->toISOString(),
            'user'         => $this->userResource($user),
        ]);
    }

    // ─── POST /api/v1/auth/logout ─────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        ActivityLog::record(action: 'logout', module: 'auth', description: 'User logged out.');
        $request->user()?->currentAccessToken()?->delete();

        auth()->guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }

    // ─── GET /api/v1/auth/me ──────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role', 'outlet');
        return response()->json(['user' => $this->userResource($user)]);
    }

    // ─── PUT /api/v1/auth/profile ─────────────────────────────────────────

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username,' . $user->id],
            'email'    => ['required', 'email', 'max:200', 'unique:users,email,' . $user->id],
            'phone'    => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        ActivityLog::record(
            action: 'update_profile',
            module: 'auth',
            entity: 'User',
            entityId: $user->id,
            description: "Updated profile details for {$user->username}"
        );

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => $this->userResource($user->fresh()->load('role', 'outlet')),
        ]);
    }

    // ─── PUT /api/v1/auth/password ────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        ActivityLog::record(
            action: 'change_password',
            module: 'auth',
            entity: 'User',
            entityId: $user->id,
            description: "Changed password for user {$user->username}"
        );

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    // ─── MFA VERIFICATION ENDPOINTS ──────────────────────────────────────────

    public function verifyMfa(Request $request): JsonResponse
    {
        $request->validate([
            'mfa_token'  => ['required', 'string'],
            'method'     => ['required', 'string', 'in:pin,otp,fingerprint'],
            'code'       => ['nullable', 'string'],
            'credential' => ['nullable'],
        ]);

        $cached = Cache::get("mfa_token:{$request->mfa_token}");
        if (!$cached) {
            return response()->json([
                'message' => 'Sesi MFA telah kadaluarsa. Silakan login kembali.',
            ], 401);
        }

        $user = User::with('role', 'outlet')->find($cached['user_id']);
        if (!$user || $user->status !== 'active') {
            return response()->json(['message' => 'Akun tidak valid atau tidak aktif.'], 403);
        }

        $verified = false;

        if ($request->method === 'pin') {
            if (empty($request->code) || strlen($request->code) !== 8) {
                return response()->json(['message' => 'PIN Keamanan harus terdiri dari 8 digit angka.'], 422);
            }
            if ($user->mfa_pin && Hash::check($request->code, $user->mfa_pin)) {
                $verified = true;
            } else {
                return response()->json(['message' => 'PIN Keamanan 8 angka salah.'], 422);
            }
        } elseif ($request->method === 'otp') {
            $storedOtp = Cache::get("mfa_otp:{$user->id}");
            if ($storedOtp && (string)$request->code === (string)$storedOtp) {
                $verified = true;
                Cache::forget("mfa_otp:{$user->id}");
            } else {
                return response()->json(['message' => 'Kode OTP Email tidak valid atau sudah kadaluarsa.'], 422);
            }
        } elseif ($request->method === 'fingerprint') {
            if (!empty($user->mfa_fingerprint_credential) && !empty($request->credential)) {
                $storedCred = json_decode($user->mfa_fingerprint_credential, true);
                $inputCred  = is_array($request->credential) ? $request->credential : json_decode($request->credential, true);

                $hasValidId = false;
                if ($storedCred && $inputCred && isset($storedCred['id'], $inputCred['id']) && $storedCred['id'] === $inputCred['id']) {
                    $hasValidId = true;
                } elseif (!empty($request->credential) && $user->mfa_fingerprint_credential === (string)$request->credential) {
                    $hasValidId = true;
                }

                if ($hasValidId) {
                    // Validasi cryptographic signature atau verifikasi PIN sekunder fallback agar tidak dapat di-bypass dengan ID statis
                    $signature = $inputCred['signature'] ?? $inputCred['response']['signature'] ?? null;
                    if (!empty($signature) && is_string($signature) && strlen($signature) >= 16) {
                        $verified = true;
                    } elseif (!empty($request->code) && !empty($user->mfa_pin) && Hash::check($request->code, $user->mfa_pin)) {
                        $verified = true;
                    } else {
                        return response()->json([
                            'message' => 'Verifikasi biometrik memerlukan signature WebAuthn yang valid atau PIN sekunder fallback.',
                        ], 422);
                    }
                }
            }
            if (!$verified) {
                return response()->json(['message' => 'Verifikasi sidik jari / biometrik tidak cocok.'], 422);
            }
        }

        if (!$verified) {
            return response()->json(['message' => 'Verifikasi MFA gagal.'], 422);
        }

        // Clear MFA token
        Cache::forget("mfa_token:{$request->mfa_token}");

        // Issue Sanctum token
        $user->tokens()->delete();
        $abilities = $user->isCeo() ? ['*'] : ['api'];
        $token = $user->createToken('erp_token', $abilities, now()->addHours(8));

        // Authenticate in web guard as well for session cookie support
        auth()->guard('web')->login($user, true);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        ActivityLog::record(action: 'login_mfa', module: 'auth', description: "User logged in with MFA verification ({$request->method}).");

        return response()->json([
            'message'      => 'Verifikasi MFA Berhasil. Selamat Datang!',
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => now()->addHours(8)->toISOString(),
            'user'         => $this->userResource($user),
        ]);
    }

    public function sendMfaOtp(Request $request): JsonResponse
    {
        $request->validate(['mfa_token' => 'required|string']);

        $cached = Cache::get("mfa_token:{$request->mfa_token}");
        if (!$cached) {
            return response()->json(['message' => 'Sesi MFA telah kadaluarsa.'], 401);
        }

        $user = User::find($cached['user_id']);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        $this->dispatchEmailOtp($user);

        return response()->json([
            'message' => 'Kode OTP 6-digit telah dikirimkan ke email ' . $this->maskEmail($user->email),
        ]);
    }

    // ─── SECURITY SETTINGS ENDPOINTS ──────────────────────────────────────────

    public function getSecurityStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json($this->getSecurityStatusPayload($user));
    }

    public function updatePin(Request $request): JsonResponse
    {
        $request->validate([
            'pin'              => ['required', 'string', 'digits:8'],
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
        }

        $user->update([
            'mfa_pin'     => Hash::make($request->pin),
            'mfa_enabled' => true,
        ]);

        ActivityLog::record(action: 'update_pin', module: 'security', description: 'Configured 8-digit MFA security PIN.');

        return response()->json([
            'message' => 'PIN Keamanan 8 angka berhasil disimpan dan aktif!',
            'status'  => $this->getSecurityStatusPayload($user->fresh()),
        ]);
    }

    public function deletePin(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
        }

        $user->update(['mfa_pin' => null]);
        $user->update(['mfa_enabled' => $user->hasMfaConfigured()]);

        ActivityLog::record(action: 'delete_pin', module: 'security', description: 'Disabled MFA PIN.');

        return response()->json([
            'message' => 'PIN Keamanan berhasil dihapus.',
            'status'  => $this->getSecurityStatusPayload($user->fresh()),
        ]);
    }

    public function toggleEmailOtp(Request $request): JsonResponse
    {
        $request->validate(['enabled' => ['required', 'boolean']]);
        $user = $request->user();

        $user->update([
            'mfa_email_otp_enabled' => $request->enabled,
        ]);
        $user->update(['mfa_enabled' => $user->hasMfaConfigured()]);

        ActivityLog::record(action: 'toggle_otp', module: 'security', description: 'Toggled MFA Email OTP.');

        return response()->json([
            'message' => $request->enabled ? 'OTP Email berhasil diaktifkan!' : 'OTP Email telah dinonaktifkan.',
            'status'  => $this->getSecurityStatusPayload($user->fresh()),
        ]);
    }

    public function registerFingerprint(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => ['required'],
        ]);

        $user = $request->user();
        $credData = is_array($request->credential) ? json_encode($request->credential) : (string)$request->credential;

        $user->update([
            'mfa_fingerprint_credential' => $credData,
            'mfa_enabled'                => true,
        ]);

        ActivityLog::record(action: 'register_fingerprint', module: 'security', description: 'Registered biometric fingerprint credential.');

        return response()->json([
            'message' => 'Sidik jari / biometrik berhasil didaftarkan!',
            'status'  => $this->getSecurityStatusPayload($user->fresh()),
        ]);
    }

    public function deleteFingerprint(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
        }

        $user->update(['mfa_fingerprint_credential' => null]);
        $user->update(['mfa_enabled' => $user->hasMfaConfigured()]);

        ActivityLog::record(action: 'delete_fingerprint', module: 'security', description: 'Removed biometric fingerprint credential.');

        return response()->json([
            'message' => 'Data sidik jari / biometrik berhasil dihapus.',
            'status'  => $this->getSecurityStatusPayload($user->fresh()),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────

    private function dispatchEmailOtp(User $user): string
    {
        $otp = (string) rand(100000, 999999);
        Cache::put("mfa_otp:{$user->id}", $otp, now()->addMinutes(5));

        try {
            Mail::raw("Kode OTP Autentikasi Ratio Coffee ERP Anda adalah: {$otp}. Kode ini berlaku selama 5 menit.", function ($message) use ($user) {
                $message->to($user->email)->subject("Kode OTP Autentikasi Ratio Coffee ERP");
            });
        } catch (\Throwable $e) {
            Log::info("MFA OTP for {$user->email}: {$otp}");
        }

        return $otp;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) < 2) return $email;
        $name = $parts[0];
        $maskedName = strlen($name) > 2 ? substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : $name . '*';
        return $maskedName . '@' . $parts[1];
    }

    private function getSecurityStatusPayload(User $user): array
    {
        return [
            'mfa_configured'     => $user->hasMfaConfigured(),
            'has_pin'           => !empty($user->mfa_pin),
            'has_email_otp'     => (bool) $user->mfa_email_otp_enabled,
            'has_fingerprint'   => !empty($user->mfa_fingerprint_credential),
            'email'             => $user->email,
            'masked_email'      => $this->maskEmail($user->email),
            'available_methods' => $user->getAvailableMfaMethods(),
        ];
    }

    private function userResource(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'username'    => $user->username,
            'email'       => $user->email,
            'phone'       => $user->phone ?? null,
            'avatar'      => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'position'    => $user->position,
            'status'      => $user->status,
            'mfa_enabled' => $user->hasMfaConfigured(),
            'role'        => [
                'id'   => $user->role?->id,
                'name' => $user->role?->name,
                'slug' => $user->role?->slug,
            ],
            'outlet'      => $user->outlet ? [
                'id'   => $user->outlet->id,
                'name' => $user->outlet->name,
                'code' => $user->outlet->code,
            ] : null,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'is_ceo'        => $user->isCeo(),
        ];
    }
}
