<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delegation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'granted_by', 'module', 'permissions',
        'valid_from', 'valid_until', 'active', 'notes',
    ];

    protected $casts = [
        'permissions' => 'array',
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
        'active'      => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isActive(): bool
    {
        if (!$this->active) return false;
        if ($this->valid_from && $this->valid_from->isFuture()) return false;
        if ($this->valid_until && $this->valid_until->isPast()) return false;
        return true;
    }
}
