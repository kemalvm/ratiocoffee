<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    protected $fillable = [
        'social_account_id',
        'platform',
        'post_id',
        'post_type',
        'thumbnail_url',
        'caption',
        'permalink',
        'views_count',
        'likes_count',
        'comments_count',
        'shares_count',
        'saves_count',
        'reach_count',
        'engagement_rate',
        'promo_code',
        'attributed_revenue',
        'attributed_transactions_count',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'engagement_rate' => 'float',
        'attributed_revenue' => 'float',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
