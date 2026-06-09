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
            'nida_locked_until' => 'datetime',
            'reminders_sent' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function affiliateVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'affiliate_vendor_id');
    }

    public function assetReservations(): HasMany
    {
        return $this->hasMany(AssetReservation::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '));
    }

    /** Name shown in navigation, profile, and underwriting once NIDA is verified. */
    public function legalDisplayName(): string
    {
        $name = $this->full_name;

        if ($name !== '') {
            return $name;
        }

        return (string) ($this->user?->name ?? 'Account');
    }

    public function hasVerifiedIdentity(): bool
    {
        return $this->nida_verification_status === 'verified' && $this->identity_locked;
    }
}
