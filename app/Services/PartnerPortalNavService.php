<?php

namespace App\Services;

use App\Models\Vendor;

class PartnerPortalNavService
{
    /**
     * @return list<array{key: string, label: string, route: string, icon: string}>
     */
    public function serviceNav(?Vendor $vendor): array
    {
        $nav = [
            ['key' => 'dashboard', 'label' => __('site.partner_portal.nav_dashboard'), 'route' => 'site.partner.dashboard', 'icon' => 'home'],
            ['key' => 'tasks', 'label' => __('site.partner_portal.nav_jobs'), 'route' => 'site.partner.tasks', 'icon' => 'clipboard'],
            ['key' => 'recovery', 'label' => __('site.partner_portal.nav_recovery'), 'route' => 'site.partner.recovery-cases', 'icon' => 'alert'],
            ['key' => 'recovery_wallet', 'label' => __('site.partner_portal.nav_commission'), 'route' => 'site.partner.recovery-wallet', 'icon' => 'wallet'],
            ['key' => 'payments', 'label' => __('site.partner_portal.nav_payments'), 'route' => 'site.partner.payments', 'icon' => 'wallet'],
            ['key' => 'calendar', 'label' => __('site.partner_portal.nav_calendar'), 'route' => 'site.partner.calendar', 'icon' => 'calendar'],
            ['key' => 'notifications', 'label' => __('site.partner_portal.nav_notifications'), 'route' => 'site.partner.notifications', 'icon' => 'bell'],
            ['key' => 'profile', 'label' => __('site.partner_portal.nav_profile'), 'route' => 'site.partner.profile', 'icon' => 'user'],
            ['key' => 'support', 'label' => __('site.partner_portal.nav_support'), 'route' => 'site.partner.support', 'icon' => 'help'],
        ];

        if ($vendor?->isInsurance()) {
            $nav = [
                ['key' => 'dashboard', 'label' => __('site.partner_portal.nav_dashboard'), 'route' => 'site.partner.dashboard', 'icon' => 'home'],
                ['key' => 'tasks', 'label' => __('site.partner_portal.nav_cover_jobs'), 'route' => 'site.partner.tasks', 'icon' => 'clipboard'],
                ['key' => 'payments', 'label' => __('site.partner_portal.nav_payments'), 'route' => 'site.partner.payments', 'icon' => 'wallet'],
                ['key' => 'notifications', 'label' => __('site.partner_portal.nav_notifications'), 'route' => 'site.partner.notifications', 'icon' => 'bell'],
                ['key' => 'profile', 'label' => __('site.partner_portal.nav_profile'), 'route' => 'site.partner.profile', 'icon' => 'user'],
                ['key' => 'support', 'label' => __('site.partner_portal.nav_support'), 'route' => 'site.partner.support', 'icon' => 'help'],
            ];

            return $nav;
        }

        if ($vendor?->isValuer() || $vendor?->isGpsInstaller()) {
            $nav = array_values(array_filter(
                $nav,
                fn (array $item) => ! in_array($item['key'], ['recovery', 'recovery_wallet', 'calendar'], true)
            ));
            if ($vendor->isValuer()) {
                foreach ($nav as &$item) {
                    if ($item['key'] === 'tasks') {
                        $item['label'] = __('site.partner_portal.nav_valuation_jobs');
                    }
                }
                unset($item);
            }
            if ($vendor->isGpsInstaller()) {
                foreach ($nav as &$item) {
                    if ($item['key'] === 'tasks') {
                        $item['label'] = __('site.partner_portal.nav_gps_jobs');
                    }
                }
                unset($item);
            }

            return $nav;
        }

        $showRecovery = $vendor && app(RecoveryPartnerService::class)->isRecoveryPartner($vendor);
        if (! $showRecovery) {
            $nav = array_values(array_filter(
                $nav,
                fn (array $item) => ! in_array($item['key'], ['recovery', 'recovery_wallet'], true)
            ));
        }

        // Collection-focused partners: lead with recovery tools; hide job calendar noise
        $recoveryFocused = $showRecovery && (
            in_array($vendor?->category, ['debt_collector', 'call_center', 'legal_partner', 'auctioneer', 'gps_installer'], true)
            || array_intersect($vendor?->partnerRoles() ?? [], ['debt_collector', 'call_center', 'legal_partner', 'auctioneer', 'gps_installer']) !== []
        );
        if ($recoveryFocused) {
            $priority = ['dashboard', 'recovery', 'recovery_wallet', 'tasks', 'payments', 'notifications', 'profile', 'support'];
            usort($nav, function (array $a, array $b) use ($priority) {
                $ai = array_search($a['key'], $priority, true);
                $bi = array_search($b['key'], $priority, true);
                $ai = $ai === false ? 100 : $ai;
                $bi = $bi === false ? 100 : $bi;

                return $ai <=> $bi;
            });
            if (in_array($vendor?->category, ['debt_collector', 'call_center', 'legal_partner'], true)
                || array_intersect($vendor?->partnerRoles() ?? [], ['debt_collector', 'call_center', 'legal_partner']) !== []) {
                $nav = array_values(array_filter(
                    $nav,
                    fn (array $item) => $item['key'] !== 'calendar'
                ));
            }
        }

        return $nav;
    }

    /**
     * @return list<array{key: string, label: string, route: string, icon: string}>
     */
    public function affiliateNav(): array
    {
        return [
            ['key' => 'dashboard', 'label' => __('site.affiliate_portal.nav_dashboard'), 'route' => 'site.affiliate.dashboard', 'icon' => 'home'],
            ['key' => 'referrals', 'label' => __('site.affiliate_portal.nav_referrals'), 'route' => 'site.affiliate.referrals', 'icon' => 'users'],
            ['key' => 'wallet', 'label' => __('site.affiliate_portal.nav_wallet'), 'route' => 'site.affiliate.wallet', 'icon' => 'wallet'],
            ['key' => 'notifications', 'label' => __('site.partner_portal.nav_notifications'), 'route' => 'site.affiliate.notifications', 'icon' => 'bell'],
            ['key' => 'profile', 'label' => __('site.affiliate_portal.nav_profile'), 'route' => 'site.affiliate.profile', 'icon' => 'user'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, route: string, icon: string}>
     */
    public function supplierNav(): array
    {
        return [
            ['key' => 'dashboard', 'label' => __('site.supplier_portal.nav_dashboard'), 'route' => 'site.supplier.dashboard', 'icon' => 'home'],
            ['key' => 'assets', 'label' => __('site.supplier_portal.nav_assets'), 'route' => 'site.supplier.assets', 'icon' => 'folder'],
            ['key' => 'applications', 'label' => __('site.supplier_portal.nav_applications'), 'route' => 'site.supplier.applications', 'icon' => 'clipboard'],
            ['key' => 'reservations', 'label' => __('site.supplier_portal.nav_reservations'), 'route' => 'site.supplier.reservations', 'icon' => 'calendar'],
            ['key' => 'requests', 'label' => __('site.supplier_portal.nav_requests'), 'route' => 'site.supplier.requests', 'icon' => 'bell'],
            ['key' => 'settlements', 'label' => __('site.supplier_portal.nav_settlements'), 'route' => 'site.supplier.settlements', 'icon' => 'wallet'],
            ['key' => 'notifications', 'label' => __('site.partner_portal.nav_notifications'), 'route' => 'site.supplier.notifications', 'icon' => 'bell'],
            ['key' => 'profile', 'label' => __('site.supplier_portal.nav_profile'), 'route' => 'site.supplier.profile', 'icon' => 'user'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, route: string, icon: string}>
     */
    public function capitalNav(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'site.investor.dashboard', 'icon' => 'home'],
            ['key' => 'funded', 'label' => 'Funded Loans', 'route' => 'site.investor.funded-loans', 'icon' => 'chart'],
            ['key' => 'returns', 'label' => 'Earnings', 'route' => 'site.investor.returns', 'icon' => 'trend'],
            ['key' => 'wallet', 'label' => 'Wallet', 'route' => 'site.investor.wallet', 'icon' => 'wallet'],
            ['key' => 'documents', 'label' => 'Reports', 'route' => 'site.investor.documents', 'icon' => 'folder'],
            ['key' => 'notifications', 'label' => 'Notifications', 'route' => 'site.investor.notifications', 'icon' => 'bell'],
            ['key' => 'profile', 'label' => 'Profile', 'route' => 'site.investor.profile', 'icon' => 'user'],
            ['key' => 'support', 'label' => 'Support', 'route' => 'site.investor.support', 'icon' => 'help'],
        ];
    }

    public function portalSubtitle(?Vendor $vendor): string
    {
        return match ($vendor?->category) {
            'affiliate' => __('site.affiliate_portal.title'),
            'debt_collector' => __('site.partner_portal.shell_debt_collector'),
            'call_center' => __('site.partner_portal.shell_call_center'),
            'legal_partner' => __('site.partner_portal.shell_legal'),
            'auctioneer' => __('site.partner_portal.shell_auctioneer'),
            'valuer' => __('site.partner_portal.shell_valuer'),
            'gps_installer' => __('site.partner_portal.shell_gps'),
            'insurance' => __('site.partner_portal.shell_insurance'),
            'yard' => __('site.partner_portal.shell_yard'),
            'towing' => __('site.partner_portal.shell_towing'),
            'supplier' => __('site.supplier_portal.title'),
            default => __('site.partner_portal.shell_default'),
        };
    }

    public function roleBanner(?Vendor $vendor): ?string
    {
        return null;
    }

    public function iconSvg(string $name): string
    {
        return match ($name) {
            'home' => '<path d="M3 12 12 4l9 8M5 10v10h14V10"/>',
            'clipboard' => '<path d="M9 5h6a2 2 0 0 1 2 2v0h-10v0a2 2 0 0 1 2-2zM7 7H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/>',
            'alert' => '<path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
            'play' => '<path d="M8 5l12 7-12 7z"/>',
            'check' => '<path d="M5 13l4 4L19 7"/>',
            'folder' => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"/>',
            'wallet' => '<path d="M3 7h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0V5a2 2 0 0 1 2-2h11M16 13h2"/>',
            'calendar' => '<path d="M5 7h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1zM8 3v4M16 3v4M4 11h16"/>',
            'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9zM10 21a2 2 0 0 0 4 0"/>',
            'help' => '<path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/>',
            'user' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0"/>',
            'users' => '<path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM3 21a7 7 0 0 1 14 0M22 11a3 3 0 1 0-3-3"/>',
            'layers' => '<path d="M12 3 2 8l10 5 10-5-10-5zM2 14l10 5 10-5M2 19l10 5 10-5"/>',
            'chart' => '<path d="M4 19V5M4 19h16M8 16V9M12 16V6M16 16v-4"/>',
            'trend' => '<path d="M3 17l6-6 4 4 8-8M21 7h-5M21 7v5"/>',
            'pie' => '<path d="M21 12A9 9 0 1 1 12 3v9h9z"/>',
            'list' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            default => '<circle cx="12" cy="12" r="8"/>',
        };
    }
}
