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
            'affiliate_premium' => 'boolean',
            'affiliate_evaluation_snapshot' => 'array',
            'affiliate_fraud_snapshot' => 'array',
            'membership_started_at' => 'datetime',
            'membership_expires_at' => 'datetime',
            'membership_payment_due_at' => 'datetime',
            'activated_at' => 'datetime',
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

    public function isPremiumAffiliate(): bool
    {
        return $this->isAffiliate() && (bool) $this->affiliate_premium;
    }

    public function isInsurance(): bool
    {
        return $this->category === 'insurance' || $this->hasPartnerRole('insurance');
    }

    public function isValuer(): bool
    {
        return $this->category === 'valuer' || $this->hasPartnerRole('valuer');
    }

    public function isGpsInstaller(): bool
    {
        return $this->category === 'gps_installer' || $this->hasPartnerRole('gps_installer');
    }

    public function isCapitalPartner(): bool
    {
        return $this->category === 'capital' || $this->hasPartnerRole('capital');
    }

    public function isTowing(): bool
    {
        return $this->category === 'towing' || $this->hasPartnerRole('towing');
    }

    public function isYard(): bool
    {
        return $this->category === 'yard' || $this->hasPartnerRole('yard');
    }

    /** Affiliates and valuers may be individual or company; other service types are company. */
    public function allowsPersonApplicant(): bool
    {
        return $this->isAffiliate() || $this->isValuer();
    }

    public function isCompanyApplicant(): bool
    {
        if (! $this->allowsPersonApplicant()) {
            return true;
        }

        return ($this->applicant_category ?? 'company') !== 'individual';
    }

    public function isIndividualApplicant(): bool
    {
        return $this->allowsPersonApplicant() && ($this->applicant_category ?? 'company') === 'individual';
    }

    /** Contact person for company portals (not the trading / company name). */
    public function contactPersonName(): string
    {
        $fromMeta = trim((string) data_get($this->metadata, 'contact_person.name', ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        if ($this->isIndividualApplicant()) {
            return (string) ($this->name ?? '');
        }

        return '';
    }

    /**
     * Primary portal shell for this partner (category wins over extra roles).
     *
     * @return 'affiliate'|'supplier'|'capital'|'service'
     */
    public function portalShell(): string
    {
        if ($this->isAffiliate()) {
            return 'affiliate';
        }

        if ($this->isSupplier()) {
            return 'supplier';
        }

        if ($this->category === 'capital') {
            return 'capital';
        }

        return 'service';
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

    public function agreementAcceptances(): HasMany
    {
        return $this->hasMany(PartnerAgreementAcceptance::class, 'partner_id');
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
