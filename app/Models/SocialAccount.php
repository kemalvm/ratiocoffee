<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'platform',
        'account_name',
        'handle',
        'avatar_url',
        'access_token',
        'status',
        'connected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }
}
