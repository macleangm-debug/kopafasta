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
            $this->notifications->notifyPartnerOnce($vendor, 'affiliate_welcome_profile', [
                'partner' => $vendor->name,
                'brand' => brand_name(),
                '_fallback_subject' => __('site.affiliate_portal.notify_welcome_subject'),
                '_fallback_body' => __('site.affiliate_portal.notify_welcome_body', [
                    'name' => $vendor->name,
                    'brand' => brand_name(),
                ]),
            ], route('site.affiliate.profile'), 'welcome');

            app(AffiliateService::class)->ensureCode($vendor);
            $vendor->refresh();

            $this->notifications->notifyPartnerOnce($vendor, 'affiliate_promo_ready', [
                'partner' => $vendor->name,
                'code' => (string) $vendor->affiliate_code,
                '_fallback_subject' => __('site.affiliate_portal.notify_promo_subject'),
                '_fallback_body' => __('site.affiliate_portal.notify_promo_body', [
                    'code' => (string) ($vendor->affiliate_code ?: '—'),
                ]),
            ], route('site.affiliate.share').'#promo-code', 'promo');

            if ($vendor->isPremiumAffiliate()) {
                $this->notifications->notifyPartnerOnce($vendor, 'affiliate_premium_active', [
                    'partner' => $vendor->name,
                    '_fallback_subject' => __('site.affiliate_portal.notify_premium_subject'),
                    '_fallback_body' => __('site.affiliate_portal.notify_premium_body'),
                ], route('site.affiliate.profile', ['section' => 'agreement']), 'premium');
            }
        }

        $prefs['partner_welcome_sent_at'] = now()->toIso8601String();
        $user->forceFill(['preferences' => $prefs])->save();
    }

    /** @return array{subject: string, body: string, url: string} */
    private function copyFor(Vendor $vendor): array
    {
        $brand = brand_name();
        $name = strtok($vendor->name, ' ') ?: $vendor->name;
        $roles = array_values(array_filter(array_unique(array_merge(
            [(string) $vendor->category],
            is_array($vendor->roles) ? $vendor->roles : []
        ))));

        $lines = [];
        foreach ($roles as $role) {
            $line = $this->roleLine($role, $name, $brand);
            if ($line !== null) {
                $lines[] = $line;
            }
        }
        if ($lines === []) {
            $lines[] = [
                'subject' => __('site.partner_portal.welcome_title', ['brand' => $brand]),
                'body' => __('site.partner_portal.welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.partner.dashboard'),
            ];
        }

        $primary = $lines[0];
        if (count($lines) > 1) {
            $primary['body'] = collect($lines)->pluck('body')->unique()->implode(' ');
        }

        return $primary;
    }

    /** @return array{subject: string, body: string, url: string}|null */
    private function roleLine(string $role, string $name, string $brand): ?array
    {
        return match ($role) {
            'affiliate' => [
                'subject' => __('account_welcome.affiliate.welcome_title'),
                'body' => __('site.affiliate_portal.notify_welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.affiliate.profile'),
            ],
            'supplier' => [
                'subject' => __('account_welcome.supplier.welcome_title'),
                'body' => __('site.supplier_portal.notify_welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.supplier.dashboard'),
            ],
            'insurance' => [
                'subject' => __('account_welcome.insurance.welcome_title'),
                'body' => __('site.partner_portal.notify_welcome_insurance', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.partner.dashboard'),
            ],
            'valuer' => [
                'subject' => __('account_welcome.valuer.welcome_title'),
                'body' => __('site.partner_portal.welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.partner.dashboard'),
            ],
            'gps_installer' => [
                'subject' => __('account_welcome.gps.welcome_title'),
                'body' => __('account_welcome.gps.welcome_body'),
                'url' => route('site.partner.dashboard'),
            ],
            'debt_collector', 'auctioneer', 'towing', 'legal_partner', 'call_center' => [
                'subject' => __('account_welcome.recovery.welcome_title'),
                'body' => __('account_welcome.recovery.welcome_body'),
                'url' => route('site.partner.dashboard'),
            ],
            'yard' => [
                'subject' => __('site.partner_portal.welcome_title', ['brand' => $brand]),
                'body' => __('site.partner_portal.welcome_body', ['name' => $name, 'brand' => $brand]),
                'url' => route('site.partner.dashboard'),
            ],
            default => null,
        };
    }
}
