<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriteOffRequest extends Model
{
    public const STATUS_RECOMMENDED = 'recommended';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'loan_id',
        'arrear_case_id',
        'write_off_rule_id',
        'amount',
        'reason',
        'status',
        'auto_proposed',
        'recommended_by',
        'recommended_at',
        'manager_approved_by',
        'manager_approved_at',
        'manager_notes',
        'finance_approved_by',
        'finance_approved_at',
        'finance_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'auto_proposed'        => 'boolean',
            'recommended_at'       => 'datetime',
            'manager_approved_at'  => 'datetime',
            'finance_approved_at'  => 'datetime',
            'rejected_at'          => 'datetime',
            'completed_at'         => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function arrearCase(): BelongsTo
    {
        return $this->belongsTo(ArrearCase::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WriteOffRule::class, 'write_off_rule_id');
    }

    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function financeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_RECOMMENDED, self::STATUS_MANAGER_APPROVED], true);
    }
}
