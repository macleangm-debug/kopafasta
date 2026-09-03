<?php

namespace App\Support;

class PartnerPerformanceStatus
{
    public const RAMP_UP = 'ramp_up';

    public const EXCELLENT = 'excellent';

    public const GOOD_STANDING = 'good_standing';

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
            self::NEEDS_ATTENTION,
            self::AT_RISK,
            self::SUSPENDED,
        ];
    }

    public static function label(string $status, ?string $locale = null): string
    {
        $key = match ($status) {
            self::RAMP_UP => 'partner_governance.status_ramp_up',
            self::EXCELLENT => 'partner_governance.status_excellent',
            self::GOOD_STANDING => 'partner_governance.status_good',
            self::NEEDS_ATTENTION => 'partner_governance.status_needs_attention',
            self::AT_RISK => 'partner_governance.status_at_risk',
            self::SUSPENDED => 'partner_governance.status_suspended',
            default => null,
        };

        if ($key === null) {
            return ucfirst(str_replace('_', ' ', $status));
        }

        return trans($key, [], $locale ?: app()->getLocale());
    }

    public static function blocksWork(string $status): bool
    {
        return $status === self::SUSPENDED;
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::EXCELLENT, self::GOOD_STANDING => 'emerald',
            self::RAMP_UP => 'gray',
            self::NEEDS_ATTENTION => 'amber',
            self::AT_RISK, self::SUSPENDED => 'rose',
            default => 'gray',
        };
    }
}
