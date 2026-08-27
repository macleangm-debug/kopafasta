<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

class BorrowerMobileNavService
{
    /**
     * @return list<array{key: string, label: string, route: string, icon: string}>
     */
    public function mobilePrimaryNav(): array
    {
        $plusRoute = $this->restoredPlusRoute();

        return [
            ['key' => 'dashboard', 'label' => __('borrower.nav.home'), 'route' => 'site.borrower.dashboard', 'icon' => 'home'],
            ['key' => 'loans', 'label' => __('borrower.nav.loans'), 'route' => 'site.borrower.loans', 'icon' => 'wallet'],
            ['key' => 'marketplace', 'label' => __('borrower.nav.marketplace_short'), 'route' => 'site.borrower.marketplace', 'icon' => 'folder'],
            ['key' => 'plus', 'label' => __('borrower.nav.plus_short'), 'route' => $plusRoute, 'icon' => 'plus'],
            ['key' => 'profile', 'label' => __('borrower.nav.profile'), 'route' => 'site.borrower.profile', 'icon' => 'user'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, route: ?string, icon: string, action?: string}>
     */
    public function plusWorkspaceNav(): array
    {
        return [
            ['key' => 'plus-home', 'label' => __('plus.nav.plus_home'), 'route' => 'site.borrower.plus.home', 'icon' => 'home'],
            ['key' => 'money', 'label' => __('plus.nav.money'), 'route' => 'site.borrower.plus.money', 'icon' => 'wallet'],
            ['key' => 'business', 'label' => __('plus.nav.business'), 'route' => 'site.borrower.plus.business', 'icon' => 'chart'],
            ['key' => 'goals', 'label' => __('plus.nav.goals'), 'route' => 'site.borrower.plus.goals', 'icon' => 'trend'],
            ['key' => 'more', 'label' => __('plus.nav.more'), 'route' => null, 'icon' => 'list', 'action' => 'more'],
        ];
    }

    /**
     * @return list<array{label: string, route: string, hint: string}>
     */
    public function plusMoreItems(): array
    {
        return [
            ['label' => __('plus.home.reports'), 'route' => 'site.borrower.plus.reports', 'hint' => __('plus.home.reports_hint')],
            ['label' => __('plus.home.offers'), 'route' => 'site.borrower.plus.offers', 'hint' => __('plus.nav.more')],
            ['label' => __('plus.home.rewards'), 'route' => 'site.borrower.plus.rewards', 'hint' => __('plus.nav.more')],
            ['label' => __('plus.home.learn'), 'route' => 'site.borrower.plus.learn', 'hint' => __('plus.home.learn_hint')],
        ];
    }

    public function isPlusWorkspace(?string $routeName): bool
    {
        return is_string($routeName) && str_starts_with($routeName, 'site.borrower.plus.');
    }

    public function rememberPlusRoom(?string $routeName): void
    {
        $remember = [
            'site.borrower.plus.home',
            'site.borrower.plus.welcome',
            'site.borrower.plus.money',
            'site.borrower.plus.business',
            'site.borrower.plus.goals',
            'site.borrower.plus.reports',
            'site.borrower.plus.offers',
            'site.borrower.plus.rewards',
            'site.borrower.plus.learn',
        ];
        if (in_array($routeName, $remember, true)) {
            session(['plus.last_room' => $routeName]);
        }
    }

    public function restoredPlusRoute(): string
    {
        $last = (string) session('plus.last_room', 'site.borrower.plus.home');

        return Route::has($last) ? $last : 'site.borrower.plus.home';
    }

    public function plusActiveKey(?string $routeName): string
    {
        return match ($routeName) {
            'site.borrower.plus.money' => 'money',
            'site.borrower.plus.business' => 'business',
            'site.borrower.plus.goals' => 'goals',
            'site.borrower.plus.reports',
            'site.borrower.plus.offers',
            'site.borrower.plus.rewards',
            'site.borrower.plus.learn',
            'site.borrower.plus.subject',
            'site.borrower.plus.lesson' => 'more',
            default => 'plus-home',
        };
    }

    public function hidesMobileNav(?string $routeName): bool
    {
        if (! is_string($routeName)) {
            return false;
        }

        foreach ([
            'site.borrower.payments.show',
            'site.borrower.payments.create',
            'site.borrower.application.agreement',
            'site.borrower.application.contract',
            'site.borrower.application.offer',
            'site.borrower.marketplace.reserve',
            'site.borrower.face-verification',
            'site.borrower.kyc-reconfirm',
            'site.borrower.profile.wizard',
        ] as $focused) {
            if ($routeName === $focused) {
                return true;
            }
        }

        return false;
    }

    /**
     * Current desktop/hamburger destinations → new mobile homes.
     * Every row must stay reachable before the old mobile menu is removed.
     *
     * @return list<array{current: string, parent: string, route: string, shortcut: string, reachable: bool}>
     */
    public function parityMatrix(): array
    {
        return [
            ['current' => 'Dashboard', 'parent' => 'Home', 'route' => 'site.borrower.dashboard', 'shortcut' => 'Home', 'reachable' => Route::has('site.borrower.dashboard')],
            ['current' => 'Current application / status', 'parent' => 'Loans', 'route' => 'site.borrower.loans', 'shortcut' => 'Home status card', 'reachable' => Route::has('site.borrower.loans')],
            ['current' => 'Apply / loan products', 'parent' => 'Loans', 'route' => 'site.borrower.loan-products', 'shortcut' => 'Loans → Apply', 'reachable' => Route::has('site.borrower.loan-products') || Route::has('site.borrower.loans')],
            ['current' => 'Applications', 'parent' => 'Loans', 'route' => 'site.borrower.applications', 'shortcut' => 'Loans', 'reachable' => Route::has('site.borrower.applications') || Route::has('site.borrower.loans')],
            ['current' => 'Loan offer', 'parent' => 'Loans', 'route' => 'site.borrower.application.offer', 'shortcut' => 'Home → Review offer', 'reachable' => Route::has('site.borrower.application.offer')],
            ['current' => 'Payments / repayments', 'parent' => 'Loans', 'route' => 'site.borrower.payments', 'shortcut' => 'Loans → Pay now', 'reachable' => Route::has('site.borrower.payments')],
            ['current' => 'payment.show', 'parent' => 'Loans (contextual)', 'route' => 'site.borrower.payments.show', 'shortcut' => 'Action needed card', 'reachable' => Route::has('site.borrower.payments.show')],
            ['current' => 'Marketplace', 'parent' => 'Marketplace', 'route' => 'site.borrower.marketplace', 'shortcut' => 'Home explore card', 'reachable' => Route::has('site.borrower.marketplace')],
            ['current' => 'Kopafasta Plus', 'parent' => 'Plus', 'route' => 'site.borrower.plus.home', 'shortcut' => 'Plus tab', 'reachable' => Route::has('site.borrower.plus.home')],
            ['current' => 'Plus Money', 'parent' => 'Plus', 'route' => 'site.borrower.plus.money', 'shortcut' => 'Plus → Money', 'reachable' => Route::has('site.borrower.plus.money')],
            ['current' => 'Plus Business', 'parent' => 'Plus', 'route' => 'site.borrower.plus.business', 'shortcut' => 'Plus → Business', 'reachable' => Route::has('site.borrower.plus.business')],
            ['current' => 'Plus Goals', 'parent' => 'Plus', 'route' => 'site.borrower.plus.goals', 'shortcut' => 'Plus → Goals', 'reachable' => Route::has('site.borrower.plus.goals')],
            ['current' => 'Plus Reports', 'parent' => 'Plus More', 'route' => 'site.borrower.plus.reports', 'shortcut' => 'Plus Home card', 'reachable' => Route::has('site.borrower.plus.reports')],
            ['current' => 'Plus Offers', 'parent' => 'Plus More', 'route' => 'site.borrower.plus.offers', 'shortcut' => 'Plus Home card', 'reachable' => Route::has('site.borrower.plus.offers')],
            ['current' => 'Plus Rewards', 'parent' => 'Plus More', 'route' => 'site.borrower.plus.rewards', 'shortcut' => 'Plus Home card', 'reachable' => Route::has('site.borrower.plus.rewards')],
            ['current' => 'Plus Learn', 'parent' => 'Plus More', 'route' => 'site.borrower.plus.learn', 'shortcut' => 'Plus Home card', 'reachable' => Route::has('site.borrower.plus.learn')],
            ['current' => 'Rewards & referrals', 'parent' => 'Profile', 'route' => 'site.borrower.engagement', 'shortcut' => 'Profile → My relationships', 'reachable' => Route::has('site.borrower.engagement')],
            ['current' => 'Notifications', 'parent' => 'Header bell', 'route' => 'site.borrower.notifications', 'shortcut' => 'Header 🔔', 'reachable' => Route::has('site.borrower.notifications')],
            ['current' => 'Profile', 'parent' => 'Profile', 'route' => 'site.borrower.profile', 'shortcut' => 'Profile tab', 'reachable' => Route::has('site.borrower.profile')],
            ['current' => 'Personal details', 'parent' => 'Profile', 'route' => 'site.borrower.profile', 'shortcut' => 'Profile hub', 'reachable' => Route::has('site.borrower.profile')],
            ['current' => 'Documents / KYC', 'parent' => 'Profile', 'route' => 'site.borrower.documents', 'shortcut' => 'Profile → Documents', 'reachable' => Route::has('site.borrower.documents') && Route::has('site.borrower.profile')],
            ['current' => 'Security / PIN', 'parent' => 'Profile', 'route' => 'site.borrower.profile', 'shortcut' => 'Profile → Security', 'reachable' => Route::has('site.borrower.profile')],
            ['current' => 'Payment / payout details', 'parent' => 'Profile', 'route' => 'site.borrower.profile', 'shortcut' => 'Profile hub', 'reachable' => Route::has('site.borrower.profile')],
            ['current' => 'Guarantors', 'parent' => 'Profile', 'route' => 'site.borrower.guarantors', 'shortcut' => 'Profile → My relationships', 'reachable' => Route::has('site.borrower.guarantors')],
            ['current' => 'Settings', 'parent' => 'Profile', 'route' => 'site.borrower.settings', 'shortcut' => 'Profile grouped links', 'reachable' => Route::has('site.borrower.settings')],
            ['current' => 'Help / support', 'parent' => 'Profile', 'route' => 'site.borrower.support', 'shortcut' => 'Profile → Help', 'reachable' => Route::has('site.borrower.support')],
            ['current' => 'Contract signing', 'parent' => 'Loans (focused journey)', 'route' => 'site.borrower.application.contract', 'shortcut' => 'Application → Contract', 'reachable' => Route::has('site.borrower.application.contract')],
            ['current' => 'Marketplace reserve', 'parent' => 'Marketplace (focused journey)', 'route' => 'site.borrower.marketplace.reserve', 'shortcut' => 'Marketplace item', 'reachable' => Route::has('site.borrower.marketplace.reserve')],
        ];
    }
}
