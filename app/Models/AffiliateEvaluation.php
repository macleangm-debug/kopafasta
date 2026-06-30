<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateEvaluation extends Model
{
    protected $fillable = [
        'partner_id',
        'period_start',
        'period_end',
        'kpi_score',
        'risk_score',
        'fraud_score',
        'recommendation',
        'action_taken',
        'metrics',
        'notes',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start'  => 'date',
            'period_end'    => 'date',
            'kpi_score'     => 'decimal:2',
            'risk_score'    => 'decimal:2',
            'fraud_score'   => 'decimal:2',
            'metrics'       => 'array',
            'evaluated_at'  => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->partner();
    }
}
