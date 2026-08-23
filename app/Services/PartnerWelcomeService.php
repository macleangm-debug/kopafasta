<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;

class PartnerWelcomeService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function sendIfFirstLogin(User $user): void
    {
        if ($user->role !== 'vendor') {
            return;
        }

        $prefs = is_array($user->preferences) ? $user->preferences : [];
        if (! empty($prefs['partner_welcome_sent_at'])) {
            return;
        }

        $vendor = Vendor::query()->where('user_id', $user->id)->first();
        if (! $vendor) {
            return;
        }

        $this->notifications->notifyPartner($vendor, 'partner_welcome', [
            'partner' => $vendor->name,
            'brand' => brand_name(),
            '_fallback_subject' => __('site.partner_portal.welcome_title', ['brand' => brand_name()]),
            '_fallback_body' => __('site.partner_portal.welcome_body', [
                'name' => $vendor->name,
                'brand' => brand_name(),
            ]),
        ], route('site.partner.dashboard'));

        $prefs['partner_welcome_sent_at'] = now()->toIso8601String();
        $user->forceFill(['preferences' => $prefs])->save();
    }
}
