<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id', 'outlet_id', 'name', 'username', 'email', 'password',
        'phone', 'avatar', 'employee_id', 'position', 'status',
        'last_login_at', 'last_login_ip',
        'mfa_enabled', 'mfa_pin', 'mfa_email_otp_enabled', 'mfa_fingerprint_credential',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_pin'];

    protected $casts = [
        'email_verified_at'     => 'datetime',
        'last_login_at'         => 'datetime',
        'password'              => 'hashed',
        'mfa_enabled'           => 'boolean',
        'mfa_email_otp_enabled' => 'boolean',
    ];

    /**
     * Check if user has configured at least one MFA method.
     */
    public function hasMfaConfigured(): bool
    {
        return !empty($this->mfa_pin) || $this->mfa_email_otp_enabled || !empty($this->mfa_fingerprint_credential);
    }

    /**
     * Get list of enabled MFA methods for this user.
     */
    public function getAvailableMfaMethods(): array
    {
        $methods = [];
        if (!empty($this->mfa_pin)) {
            $methods[] = 'pin';
        }
        if ($this->mfa_email_otp_enabled) {
            $methods[] = 'otp';
        }
        if (!empty($this->mfa_fingerprint_credential)) {
            $methods[] = 'fingerprint';
        }
        return $methods;
    }

    // ─── Relations ────────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('type');
    }

    public function delegations()
    {
        return $this->hasMany(Delegation::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ─── Permission Helpers ───────────────────────────────────────────────

    /**
     * Check if user has a given permission (slug).
     * CEO role bypasses all checks.
     */
    public function can($ability, $arguments = []): bool
    {
        // CEO has full access
        if ($this->role && $this->role->slug === 'ceo') {
            return true;
        }

        // Employee has full access like CEO, except for tax, admin-fee and activity logs
        if ($this->role && $this->role->slug === 'employee') {
            if (str_starts_with($ability, 'tax_settings') || str_starts_with($ability, 'admin_fee') || str_starts_with($ability, 'activity_log') || $ability === 'logs') {
                return false;
            }
            return true;
        }

        // Check explicit user-level revoke
        $slugs = [$ability];
        if (str_contains($ability, '.')) {
            [$module, $action] = explode('.', $ability, 2);
            $slugs[] = str_ends_with($module, 's') ? rtrim($module, 's') . '.' . $action : $module . 's.' . $action;
        }

        $userPermission = $this->permissions()->whereIn('slug', $slugs)->first();
        if ($userPermission && $userPermission->pivot->type === 'revoke') {
            return false;
        }

        // Check explicit user-level grant
        if ($userPermission && $userPermission->pivot->type === 'grant') {
            return true;
        }

        // Check role-level permission
        if ($this->role && $this->role->hasPermission($ability)) {
            return true;
        }

        // Check active delegations
        return $this->delegations()
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->get()
            ->contains(fn($d) => in_array($ability, json_decode($d->permissions, true) ?? []));
    }

    public function isCeo(): bool
    {
        return $this->role && $this->role->slug === 'ceo';
    }

    public function isManager(): bool
    {
        return $this->role && $this->role->slug === 'manager';
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
