<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanGroupMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'disbursement_unlocked_at' => 'datetime',
            'disbursed_at'             => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LoanGroup::class, 'loan_group_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isLeader(): bool
    {
        return $this->role === 'leader';
    }
}
