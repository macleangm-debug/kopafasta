<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'requires_collateral' => 'boolean',
            'requires_guarantor' => 'boolean',
            'collateral_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(LoanProductRequirement::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
