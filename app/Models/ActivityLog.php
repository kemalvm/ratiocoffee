<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'role_id', 'action', 'module', 'entity', 'entity_id',
        'old_data', 'new_data', 'ip_address', 'device', 'browser',
        'user_agent', 'description',
    ];

    protected $casts = [
        'old_data'   => 'array',
        'new_data'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // ─── Static logger helper ─────────────────────────────────────────────

    public static function record(
        string $action,
        string $module = null,
        string $entity = null,
        int $entityId = null,
        array|string $oldData = null,
        array $newData = null,
        string $description = null,
    ): self {
        if (is_string($oldData)) {
            $description = $oldData;
            $oldData = null;
        }

        $request   = request();
        $user      = auth()->user() ?? auth('sanctum')->user() ?? $request->user();
        if ($user && !$user->role_id && $user->role) {
            $user->role_id = $user->role->id;
        }
        $userAgent = $request->userAgent() ?? '';

        $browser = 'Unknown';
        if (str_contains($userAgent, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($userAgent, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($userAgent, 'Safari')) $browser = 'Safari';
        elseif (str_contains($userAgent, 'Edge')) $browser = 'Edge';

        return static::create([
            'user_id'     => $user?->id,
            'role_id'     => $user?->role_id ?? $user?->role?->id,
            'action'      => $action,
            'module'      => $module,
            'entity'      => $entity,
            'entity_id'   => $entityId,
            'old_data'    => $oldData,
            'new_data'    => $newData,
            'ip_address'  => $request->ip(),
            'device'      => 'Web Browser',
            'browser'     => $browser,
            'user_agent'  => $userAgent,
            'description' => $description,
            'created_at'  => now(),
        ]);
    }
}
