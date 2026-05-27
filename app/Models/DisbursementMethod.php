<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisbursementMethod extends Model
{
    protected $fillable = [
        'name', 'code', 'channel',
        'fixed_fee', 'percentage_fee', 'priority', 'is_active',
    ];
    protected $casts = [
        'fixed_fee' => 'decimal:2',
        'percentage_fee' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
