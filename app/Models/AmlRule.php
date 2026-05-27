<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmlRule extends Model
{
    protected $fillable = [
        'name', 'code', 'rule_type', 'threshold_amount',
        'threshold_count', 'window_days', 'action', 'severity',
        'is_active', 'description',
    ];
    protected $casts = [
        'threshold_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
