<?php

namespace App\Services;

use App\Models\Setting;

class IdentityVerificationPolicyService
{
    public const STAGE_PROFILE = 'profile_creation';

    public const STAGE_UNDERWRITING = 'underwriting';

    public function settings(): array
    {
        $values = Setting::group('identity_verification');

        return [
            'require_facial'        => (bool) ($values['require_facial'] ?? true),
            'require_nida'          => (bool) ($values['require_nida'] ?? true),
            'verification_stage'    => (string) ($values['verification_stage'] ?? self::STAGE_UNDERWRITING),
        ];
    }

    public function requiredDuringProfileCreation(): bool
    {
        return $this->settings()['verification_stage'] === self::STAGE_PROFILE;
    }

    public function requiredDuringUnderwriting(): bool
    {
        return $this->settings()['verification_stage'] === self::STAGE_UNDERWRITING;
    }

    public function facialRequired(): bool
    {
        return $this->settings()['require_facial'];
    }

    public function nidaRequired(): bool
    {
        return $this->settings()['require_nida'];
    }
}
