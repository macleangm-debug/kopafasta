<?php

namespace App\Models;

use App\Models\Concerns\HasMembership;
use App\Models\Concerns\MapsLegacyAffiliatePartnerId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasMembership;
    use MapsLegacyAffiliatePartnerId;

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
            'no_physical_nida_card' => 'boolean',
            'alternate_id_types' => 'array',
            'face_verified_at' => 'datetime',
            'kyc_reconfirmed_at' => 'datetime',
            'profile_section_confirmed_at' => 'array',
            'nida_locked_until' => 'datetime',
            'reminders_sent' => 'array',
            'legal_signed_at' => 'datetime',
            'grade_review_until' => 'datetime',
            'grade_next_review_at' => 'datetime',
            'grade_override_expires_at' => 'datetime',
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

    public function assets(): HasMany
    {
        return $this->hasMany(CustomerAsset::class);
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

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function borrowerRefunds(): HasMany
    {
        return $this->hasMany(BorrowerRefund::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function guarantorInvitations(): HasMany
    {
        return $this->hasMany(GuarantorInvitation::class);
    }

    public function affiliateVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'affiliate_partner_id');
    }

    public function assetReservations(): HasMany
    {
        return $this->hasMany(AssetReservation::class);
    }

    public function plusSubscriptions(): HasMany
    {
        return $this->hasMany(PlusSubscription::class);
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
