<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'name', 'bank_name', 'account_number', 'branch', 'swift_code',
        'currency', 'opening_balance', 'gl_account_id', 'purpose',
        'is_active', 'notes',
    ];
    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function glAccount() { return $this->belongsTo(ChartOfAccount::class, 'gl_account_id'); }
}
