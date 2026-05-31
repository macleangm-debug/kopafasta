<?php

namespace App\Models;

use App\Models\Concerns\HasMembership;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasMembership;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'onboarded_at' => 'date',
            'date_of_birth' => 'date',
            'monthly_income' => 'decimal:2',
            'activity_details' => 'array',
            'membership_issued_at' => 'date',
            'membership_expires_at' => 'date',
            'last_renewal_at' => 'date',
            'nida_verified_at' => 'datetime',
            'identity_locked' => 'boolean',
            'face_verified_at' => 'datetime',
            'kyc_reconfirmed_at' => 'datetime',
            'reminders_sent' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function kyc(): HasOne
    {
        return $this->hasOne(CustomerKyc::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function faceVerifications(): HasMany
    {
        return $this->hasMany(FaceVerification::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
