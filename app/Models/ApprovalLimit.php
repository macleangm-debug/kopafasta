<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalLimit extends Model
{
    protected $fillable = [
        'role_code', 'action', 'min_amount', 'max_amount',
        'currency', 'requires_dual_control', 'is_active',
    ];
    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'requires_dual_control' => 'boolean',
        'is_active' => 'boolean',
    ];
}
