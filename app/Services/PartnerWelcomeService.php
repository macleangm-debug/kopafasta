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

        $copy = $this->copyFor($vendor);

        $this->notifications->notifyPartner($vendor, 'partner_welcome', [
            'partner' => $vendor->name,
            'brand' => brand_name(),
            '_fallback_subject' => $copy['subject'],
            '_fallback_body' => $copy['body'],
        ], $copy['url']);

        if ($vendor->isAffiliate()) {
            $this->notifications->notifyPartnerOnce($vendor, 'affiliate_account_active', [
                'partner' => $vendor->name,
                'code' => (string) $vendor->affiliate_code,
                '_fallback_subject' => __('site.affiliate_portal.notify_active_subject'),
                '_fallback_body' => __('site.affiliate_portal.notify_active_body', [
                    'code' => (string) ($vendor->affiliate_code ?: '—'),
                ]),
            ], route('site.affiliate.share'), 'active');

            if ($vendor->isPremiumAffiliate()) {
                $this->notifications->notifyPartnerOnce($vendor, 'affiliate_premium_active', [
                    'partner' => $vendor->name,
                    '_fallback_subject' => __('site.affiliate_portal.notify_premium_subject'),
                    '_fallback_body' => __('site.affiliate_portal.notify_premium_body'),
                ], route('site.affiliate.agreement'), 'premium');
            }
        }

        $prefs['partner_welcome_sent_at'] = now()->toIso8601String();
        $user->forceFill(['preferences' => $prefs])->save();
    }

    /** @return array{subject: string, body: string, url: string} */
    private function copyFor(Vendor $vendor): array
    {
        $brand = brand_name();
        $name = $vendor->name;

        return match ((string) $vendor->category) {
            'affiliate' => [
                'subject' => __('account_welcome.affiliate.welcome_title'),
                'body' => __('site.affiliate_portal.notify_welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.affiliate.dashboard'),
            ],
            'valuer' => [
                'subject' => __('account_welcome.valuer.welcome_title'),
                'body' => __('site.partner_portal.welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.partner.dashboard'),
            ],
            default => [
                'subject' => __('site.partner_portal.welcome_title', ['brand' => $brand]),
                'body' => __('site.partner_portal.welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.partner.dashboard'),
            ],
        };
    }
}
