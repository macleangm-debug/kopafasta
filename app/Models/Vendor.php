<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'roles'    => 'array',
            'regions'  => 'array',
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
        return $this->hasMany(VendorTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function marketplaceAssets(): HasMany
    {
        return $this->hasMany(MarketplaceAsset::class);
    }

    public function affiliateEvents(): HasMany
    {
        return $this->hasMany(AffiliateEvent::class);
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

    public function recoveryAssignments(): HasMany
    {
        return $this->hasMany(RecoveryAssignment::class);
    }
}
