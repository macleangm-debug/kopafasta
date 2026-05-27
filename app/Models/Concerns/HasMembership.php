<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MembershipHistory;

/**
 * Membership lifecycle helpers attached to the Customer model.
 *
 * Status values:
 *  - active   : within validity window
 *  - expiring : within reminder window (<= 30 days) but not yet expired
 *  - grace    : past expiry but inside grace period
 *  - expired  : past expiry + grace
 *  - archived : explicitly archived
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

    public function isMembershipActive(): bool
    {
        return $this->hasMembership() && CarbonImmutable::today()->lte($this->membership_expires_at);
    }

    public function isMembershipExpired(): bool
    {
        if (! $this->hasMembership()) {
            return true;
        }
        $today = CarbonImmutable::today();
        $expiry = CarbonImmutable::parse($this->membership_expires_at);
        $grace  = (int) (\App\Services\MembershipService::config()['grace_period_days'] ?? 0);
        return $today->gt($expiry->addDays($grace));
    }

    public function isMembershipInGrace(): bool
    {
        if (! $this->hasMembership()) {
            return false;
        }
        $today = CarbonImmutable::today();
        $expiry = CarbonImmutable::parse($this->membership_expires_at);
        $grace  = (int) (\App\Services\MembershipService::config()['grace_period_days'] ?? 0);
        return $today->gt($expiry) && $today->lte($expiry->addDays($grace));
    }

    public function isMembershipExpiringSoon(int $withinDays = 30): bool
    {
        if (! $this->isMembershipActive()) {
            return false;
        }
        return $this->membershipDaysRemaining() <= $withinDays;
    }

    public function membershipDaysRemaining(): int
    {
        if (! $this->hasMembership()) {
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
        return match (true) {
            $this->isMembershipExpired()       => 'red',
            $this->isMembershipInGrace()       => 'red',
            $this->isMembershipExpiringSoon(30)=> 'orange',
            $this->isMembershipActive()        => 'green',
            default                            => 'gray',
        };
    }

    public function membershipStatusLabel(): string
    {
        return match (true) {
            ! $this->hasMembership()           => 'NOT ISSUED',
            $this->isMembershipExpired()       => 'EXPIRED',
            $this->isMembershipInGrace()       => 'GRACE',
            $this->isMembershipExpiringSoon(7) => 'EXPIRING SOON',
            $this->isMembershipExpiringSoon(30)=> 'EXPIRING',
            default                            => 'ACTIVE',
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
        $today  = now()->toDateString();
        $target = now()->addDays($days)->toDateString();
        return $q->whereNotNull('membership_expires_at')
            ->whereBetween('membership_expires_at', [$today, $target]);
    }
}
