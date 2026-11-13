<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'symbol', 'type'];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }
}
