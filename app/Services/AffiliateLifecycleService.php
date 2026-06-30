<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;

class AffiliateLifecycleService
{
    public const PENDING_KYC = 'pending_kyc';

    public const ACTIVE = 'active';

    public const WATCHLIST = 'watchlist';

    public const SUSPENDED = 'suspended';

    public const TERMINATED = 'terminated';

    /** @return list<string> */
    public function statuses(): array
    {
        return [
            self::PENDING_KYC,
            self::ACTIVE,
            self::WATCHLIST,
            self::SUSPENDED,
            self::TERMINATED,
        ];
    }

    public function label(string $status): string
    {
        return match ($status) {
            self::PENDING_KYC => 'Pending KYC',
            self::ACTIVE      => 'Active',
            self::WATCHLIST   => 'Watchlist',
            self::SUSPENDED   => 'Suspended',
            self::TERMINATED  => 'Terminated',
            default           => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function statusFor(Vendor $affiliate): string
    {
        if (! $affiliate->isAffiliate()) {
            return self::ACTIVE;
        }

        return (string) ($affiliate->affiliate_lifecycle_status ?? self::PENDING_KYC);
    }

    public function canAccessPortal(Vendor $affiliate): bool
    {
        return ! in_array($this->statusFor($affiliate), [self::TERMINATED], true);
    }

    public function canReceiveReferrals(Vendor $affiliate): bool
    {
        if (! $affiliate->isAffiliate() || $affiliate->status !== 'active') {
            return false;
        }

        return ! in_array($this->statusFor($affiliate), [self::SUSPENDED, self::TERMINATED], true);
    }

    public function canSharePublicly(Vendor $affiliate): bool
    {
        if (! $this->canReceiveReferrals($affiliate)) {
            return false;
        }

        return in_array($affiliate->affiliate_kyc_status, ['verified', 'approved'], true)
            && in_array($this->statusFor($affiliate), [self::ACTIVE, self::WATCHLIST], true);
    }

    public function syncFromKyc(Vendor $affiliate): Vendor
    {
        if (! $affiliate->isAffiliate()) {
            return $affiliate;
        }

        $current = $this->statusFor($affiliate);

        if (in_array($current, [self::SUSPENDED, self::TERMINATED, self::WATCHLIST], true)) {
            return $affiliate;
        }

        if (in_array($affiliate->affiliate_kyc_status, ['verified', 'approved'], true)) {
            $affiliate->update(['affiliate_lifecycle_status' => self::ACTIVE]);

            return $affiliate->refresh();
        }

        if ($current !== self::ACTIVE) {
            $affiliate->update(['affiliate_lifecycle_status' => self::PENDING_KYC]);

            return $affiliate->refresh();
        }

        return $affiliate;
    }

    public function initializeNewAffiliate(Vendor $affiliate): Vendor
    {
        if (! $affiliate->isAffiliate()) {
            return $affiliate;
        }

        $affiliate->update([
            'affiliate_lifecycle_status' => self::PENDING_KYC,
        ]);

        return $affiliate->refresh();
    }

    public function transition(
        Vendor $affiliate,
        string $status,
        ?string $reason = null,
        ?User $actor = null,
    ): Vendor {
        abort_unless($affiliate->isAffiliate(), 404);
        abort_unless(in_array($status, $this->statuses(), true), 422);

        $updates = [
            'affiliate_lifecycle_status' => $status,
            'affiliate_lifecycle_note' => filled($reason) ? trim($reason) : null,
        ];

        if ($status === self::SUSPENDED) {
            $updates['status'] = 'suspended';
        } elseif ($status === self::ACTIVE && $affiliate->status === 'suspended') {
            $updates['status'] = 'active';
        }

        $affiliate->update($updates);

        return $affiliate->refresh();
    }
}
