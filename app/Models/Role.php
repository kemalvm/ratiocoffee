<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    // ─── Relations ────────────────────────────────────────────────────────

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /** Check if role has a given permission slug */
    public function hasPermission(string $slug): bool
    {
        $slugs = [$slug];
        if (str_contains($slug, '.')) {
            [$module, $action] = explode('.', $slug, 2);
            if (str_ends_with($module, 's')) {
                $slugs[] = rtrim($module, 's') . '.' . $action;
            } else {
                $slugs[] = $module . 's.' . $action;
            }
        }
        return $this->permissions()->whereIn('slug', $slugs)->exists();
    }
}
