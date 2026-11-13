<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $table = 'expense_categories';

    protected $fillable = ['name', 'account_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function expenses()  { return $this->hasMany(Expense::class, 'category_id'); }
    public function account()   { return $this->belongsTo(Account::class); }
}
