<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partners';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'roles'    => 'array',
            'regions'  => 'array',
            'affiliate_evaluation_snapshot' => 'array',
            'affiliate_fraud_snapshot' => 'array',
            'membership_started_at' => 'datetime',
            'membership_expires_at' => 'datetime',
            'membership_payment_due_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function partnerRoles(): array
    {
        $roles = $this->roles ?? [];

        if ($roles === [] && filled($this->category)) {
            return [$this->category];
        }

        return array_values($roles);
    }

    public function hasPartnerRole(string $role): bool
    {
        return in_array($role, $this->partnerRoles(), true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PartnerTask::class, 'partner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class, 'partner_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PartnerPayment::class, 'partner_id');
    }

    public function marketplaceAssets(): HasMany
    {
        return $this->hasMany(MarketplaceAsset::class, 'partner_id');
    }

    public function affiliateEvents(): HasMany
    {
        return $this->hasMany(AffiliateEvent::class, 'partner_id');
    }

    public function affiliateEvaluations(): HasMany
    {
        return $this->hasMany(AffiliateEvaluation::class, 'partner_id');
    }

    public function isSupplier(): bool
    {
        return $this->category === 'supplier';
    }

    public function isAffiliate(): bool
    {
        return $this->category === 'affiliate';
    }

    public function isRecoveryPartner(): bool
    {
        return app(\App\Services\RecoveryPartnerService::class)->isRecoveryPartner($this);
    }

    public function getVendorNumberAttribute(): ?string
    {
        return $this->partner_number;
    }

    public function setVendorNumberAttribute(?string $value): void
    {
        $this->attributes['partner_number'] = $value;
    }

    public function recoveryAssignments(): HasMany
    {
        return $this->hasMany(RecoveryAssignment::class, 'partner_id');
    }

    public function coverageLabel(): string
    {
        if (($this->coverage_type ?? 'regions') === 'nationwide') {
            return 'Nationwide';
        }

        $regions = array_values(array_filter($this->regions ?? []));

        return $regions === [] ? 'Regional' : implode(', ', $regions);
    }
}
