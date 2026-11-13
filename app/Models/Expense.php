<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'outlet_id', 'category_id', 'created_by', 'approved_by',
        'reference_number', 'expense_date', 'amount', 'description',
        'attachment', 'status', 'payment_method', 'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────
    public function outlet()    { return $this->belongsTo(Outlet::class); }
    public function category()  { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }

    // ── Auto-generate reference number ───────────────────────────────────
    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->reference_number)) {
                $year   = now()->format('Y');
                $month  = now()->format('m');
                $prefix = "EXP-{$year}{$month}-";

                // Query max existing number for current year/month including soft deleted records
                $maxRef = static::withTrashed()
                    ->where('reference_number', 'like', "{$prefix}%")
                    ->orderByRaw('CAST(SUBSTRING(reference_number, -4) AS UNSIGNED) DESC')
                    ->value('reference_number');

                $nextNumber = 1;
                if ($maxRef) {
                    $lastDigits = (int) substr($maxRef, -4);
                    $nextNumber = $lastDigits + 1;
                }

                // Guarantee uniqueness by checking existence
                do {
                    $candidate = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                    $exists    = static::withTrashed()->where('reference_number', $candidate)->exists();
                    if ($exists) {
                        $nextNumber++;
                    }
                } while ($exists);

                $expense->reference_number = $candidate;
            }
        });
    }
}
