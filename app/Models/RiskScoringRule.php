<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskScoringRule extends Model
{
    protected $fillable = [
        'factor', 'operator', 'value', 'weight', 'category', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];
}
