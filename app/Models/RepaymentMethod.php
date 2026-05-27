<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepaymentMethod extends Model
{
    protected $fillable = [
        'name', 'code', 'channel',
        'fixed_fee', 'percentage_fee', 'auto_reconcile', 'is_active',
    ];
    protected $casts = [
        'fixed_fee' => 'decimal:2',
        'percentage_fee' => 'decimal:4',
        'auto_reconcile' => 'boolean',
        'is_active' => 'boolean',
    ];
}
