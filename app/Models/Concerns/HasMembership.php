<?php

namespace App\Models\Concerns;

use App\Models\MembershipHistory;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Membership lifecycle helpers attached to the Customer model.
 *
 * When borrower membership is OFF for the country (e.g. Tanzania), the digital
 * card is a permanent customer identity (member_no) with no paid expiry.
 *
 * @property \Illuminate\Support\Carbon|null $membership_issued_at
 * @property \Illuminate\Support\Carbon|null $membership_expires_at
 * @property \Illuminate\Support\Carbon|null $last_renewal_at
 * @property string|null $membership_status
 * @property string|null $member_no
 * @property int|null $renewal_count
 * @property array|null $reminders_sent
 */
trait HasMembership
{
    public function membershipHistories(): HasMany
    {
        return $this->hasMany(MembershipHistory::class);
    }

    // ---------- status checks ----------

    public function hasMembership(): bool
    {
        return $this->membership_expires_at !== null;
    }

    /** Permanent digital customer identity (member_no) — independent of paid membership. */
    public function hasCustomerIdentity(): bool
    {
        return filled($this->member_no);
    }

    public function usesPermanentIdentityCard(): bool
    {
        return MembershipService::usesPermanentIdentityCard($this->country_code ?? null);
    }

    public function customerSinceDate(): ?CarbonInterface
    {
        return $this->membership_issued_at ?? $this->created_at;
    }

    public function isMembershipActive(): bool
    {
        if ($this->usesPermanentIdentityCard() && $this->hasCustomerIdentity()) {
            return ($this->status ?? 'active') === 'active';
        }

        return $this->hasMembership() && CarbonImmutable::today()->lte($this->membership_expires_at);
    }

    public function isMembershipExpired(): bool
    {
        if ($this->usesPermanentIdentityCard()) {
            return ! $this->hasCustomerIdentity() || ($this->status ?? '') === 'archived';
        }

        if (! $this->hasMembership()) {
            return true;
        }
        $today = CarbonImmutable::today();
        $expiry = CarbonImmutable::parse($this->membership_expires_at);
        $grace = (int) (MembershipService::config()['grace_period_days'] ?? 0);

        return $today->gt($expiry->addDays($grace));
    }

    public function isMembershipInGrace(): bool
    {
        if ($this->usesPermanentIdentityCard() || ! $this->hasMembership()) {
            return false;
        }
        $today = CarbonImmutable::today();
        $expiry = CarbonImmutable::parse($this->membership_expires_at);
        $grace = (int) (MembershipService::config()['grace_period_days'] ?? 0);

        return $today->gt($expiry) && $today->lte($expiry->addDays($grace));
    }

    public function isMembershipExpiringSoon(int $withinDays = 30): bool
    {
        if ($this->usesPermanentIdentityCard() || ! $this->isMembershipActive()) {
            return false;
        }

        return $this->membershipDaysRemaining() <= $withinDays;
    }

    public function membershipDaysRemaining(): int
    {
        if ($this->usesPermanentIdentityCard() || ! $this->hasMembership()) {
            return 0;
        }
        $diff = CarbonImmutable::today()->diffInDays(
            CarbonImmutable::parse($this->membership_expires_at),
            false
        );

        return (int) $diff;
    }

    public function membershipStatusColor(): string
    {
        if ($this->usesPermanentIdentityCard()) {
            return match (true) {
                ! $this->hasCustomerIdentity() => 'red',
                ($this->status ?? '') === 'active' => 'green',
                default => 'slate',
            };
        }

        return match (true) {
            $this->isMembershipExpired() => 'red',
            $this->isMembershipInGrace() => 'red',
            $this->isMembershipExpiringSoon(30) => 'orange',
            $this->isMembershipActive() => 'green',
            default => 'gray',
        };
    }

    public function membershipStatusLabel(): string
    {
        if ($this->usesPermanentIdentityCard()) {
            return match (true) {
                ! $this->hasCustomerIdentity() => __('borrower.membership.badge_not_issued'),
                ($this->status ?? '') === 'active' => __('borrower.membership.badge_active'),
                default => __('borrower.membership.badge_inactive'),
            };
        }

        return match (true) {
            ! $this->hasMembership() => __('borrower.membership.badge_not_issued'),
            $this->isMembershipExpired() => __('borrower.membership.badge_expired'),
            $this->isMembershipInGrace() => __('borrower.membership.badge_grace'),
            $this->isMembershipExpiringSoon(7) => __('borrower.membership.badge_expiring_soon'),
            $this->isMembershipExpiringSoon(30) => __('borrower.membership.badge_expiring'),
            default => __('borrower.membership.badge_active'),
        };
    }

    // ---------- query scopes ----------

    public function scopeWhereMembershipActive(Builder $q): Builder
    {
        return $q->whereNotNull('membership_expires_at')
            ->whereDate('membership_expires_at', '>=', now()->toDateString());
    }

    public function scopeWhereMembershipExpired(Builder $q): Builder
    {
        return $q->whereNotNull('membership_expires_at')
            ->whereDate('membership_expires_at', '<', now()->toDateString());
    }

    public function scopeWhereMembershipExpiringIn(Builder $q, int $days): Builder
    {
        $today = now()->toDateString();
        $target = now()->addDays($days)->toDateString();

        return $q->whereNotNull('membership_expires_at')
            ->whereBetween('membership_expires_at', [$today, $target]);
    }
}
