<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'normal_balance',
        'is_header', 'active', 'sort_order', 'description',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'active'    => 'boolean',
    ];

    public function parent()   { return $this->belongsTo(Account::class, 'parent_id'); }
    public function children() { return $this->hasMany(Account::class, 'parent_id'); }
}
