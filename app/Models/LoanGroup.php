<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanGroup extends Model
{
    protected $guarded = [];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'leader_customer_id');
    }

    public function primaryApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'primary_application_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LoanGroupMember::class)->orderBy('sort_order');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
