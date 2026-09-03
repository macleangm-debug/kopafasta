<?php

namespace App\Support;

class AffiliatePerformanceStatus
{
    public const RAMP_UP = 'ramp_up';

    public const EXCELLENT = 'excellent';

    public const GOOD_STANDING = 'good_standing';

    public const PREMIUM = 'premium';

    public const NEEDS_ATTENTION = 'needs_attention';

    public const AT_RISK = 'at_risk';

    public const SUSPENDED = 'suspended';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::RAMP_UP,
            self::EXCELLENT,
            self::GOOD_STANDING,
            self::PREMIUM,
            self::NEEDS_ATTENTION,
            self::AT_RISK,
            self::SUSPENDED,
        ];
    }

    public static function label(string $status, ?string $locale = null): string
    {
        $key = match ($status) {
            self::RAMP_UP => 'site.affiliate_portal.performance_ramp_up',
            self::EXCELLENT => 'site.affiliate_portal.performance_excellent',
            self::GOOD_STANDING => 'site.affiliate_portal.performance_good',
            self::PREMIUM => 'site.affiliate_portal.performance_premium',
            self::NEEDS_ATTENTION => 'site.affiliate_portal.performance_needs_attention',
            self::AT_RISK => 'site.affiliate_portal.performance_at_risk',
            self::SUSPENDED => 'site.affiliate_portal.performance_suspended',
            default => null,
        };

        if ($key === null) {
            return ucfirst(str_replace('_', ' ', $status));
        }

        return trans($key, [], $locale ?: app()->getLocale());
    }

    public static function blocksNewBusiness(string $status): bool
    {
        return $status === self::SUSPENDED;
    }
}
