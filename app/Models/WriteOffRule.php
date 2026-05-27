<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WriteOffRule extends Model
{
    protected $fillable = [
        'name', 'days_past_due', 'min_outstanding', 'max_outstanding',
        'require_committee_approval', 'auto_propose', 'description', 'is_active',
    ];
    protected $casts = [
        'min_outstanding' => 'decimal:2',
        'max_outstanding' => 'decimal:2',
        'require_committee_approval' => 'boolean',
        'auto_propose' => 'boolean',
        'is_active' => 'boolean',
    ];
}
