<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\MarketingDemoSession;
use App\Models\PlusOffer;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Str;

class AdminSearchService
{
    /**
     * @return list<array{group: string, items: list<array{title: string, subtitle: string, url: string, action?: bool}>}>
     */
    public function search(User $user, string $query): array
    {
        $q = trim($query);
        $permissions = app(PermissionService::class);
        $nav = app(ConsoleNavService::class);
        $groups = [];

        $priority = $this->groupPriority($user);

        if ($q === '') {
            return [];
        }

        $actions = $this->actions($user, $q, $permissions, $nav);
        if ($actions !== []) {
            $groups['Actions'] = $actions;
        }

        $pages = $this->pages($user, $q, $nav);
        if ($pages !== []) {
            $groups['Pages'] = $pages;
        }

        if ($permissions->has($user, 'settings.manage')) {
            $settings = $this->settings($q);
            if ($settings !== []) {
                $groups['Settings'] = $settings;
            }
        }

        if ($permissions->has($user, 'customers.view')) {
            $customers = $this->customers($q);
            if ($customers !== []) {
                $groups['Customers'] = $customers;
            }
        }

        if ($permissions->has($user, 'loans.view')) {
            $loans = $this->loans($q);
            if ($loans !== []) {
                $groups['Loans'] = $loans;
            }
        }

        if ($permissions->hasAny($user, ['marketing.view', 'marketing.campaigns.edit', 'marketing.campaigns.create'])) {
            $campaigns = $this->campaigns($q);
            if ($campaigns !== []) {
                $groups['Campaigns'] = $campaigns;
            }
        }

        if ($permissions->hasAny($user, ['marketing.view', 'marketing.demos.create'])) {
            $demos = $this->demos($q);
            if ($demos !== []) {
                $groups['Demo Accounts'] = $demos;
            }
        }

        if ($permissions->has($user, 'marketing.offers.manage')) {
            $offers = $this->offers($q);
            if ($offers !== []) {
                $groups['Offers'] = $offers;
            }
        }

        uksort($groups, function (string $a, string $b) use ($priority) {
            return ($priority[$a] ?? 99) <=> ($priority[$b] ?? 99);
        });

        return collect($groups)
            ->map(fn ($items, $group) => ['group' => $group, 'items' => array_slice($items, 0, 6)])
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    private function groupPriority(User $user): array
    {
        $role = (string) $user->role;

        $base = [
            'Actions' => 0,
            'Pages' => 1,
            'Customers' => 2,
            'Loans' => 3,
            'Campaigns' => 4,
            'Demo Accounts' => 5,
            'Offers' => 6,
            'Settings' => 8,
        ];

        if (in_array($role, ['officer', 'credit_analyst', 'credit_committee', 'manager'], true)) {
            return array_merge($base, ['Customers' => 1, 'Loans' => 2, 'Pages' => 3, 'Actions' => 0]);
        }

        if (in_array($role, ['marketer'], true)) {
            return array_merge($base, ['Campaigns' => 1, 'Demo Accounts' => 2, 'Offers' => 3, 'Customers' => 4, 'Pages' => 5]);
        }

        if ($role === 'agent') {
            return array_merge($base, ['Customers' => 1, 'Pages' => 2, 'Actions' => 0]);
        }

        return $base;
    }

    private function actions(User $user, string $q, PermissionService $permissions, ConsoleNavService $nav): array
    {
        $hay = Str::lower($q);
        $candidates = [];

        if ($permissions->has($user, 'marketing.campaigns.create') && (str_contains($hay, 'campaign') || str_contains($hay, 'promo'))) {
            $candidates[] = $this->item('Create campaign', 'Growth → Campaigns', route('admin.promotions.create'), true);
        }
        if ($permissions->has($user, 'marketing.demos.create') && str_contains($hay, 'demo')) {
            $candidates[] = $this->item('Create Demo Account', 'Growth → Demo Accounts', route('admin.growth.demos.create'), true);
            $candidates[] = $this->item('View active demos', 'Growth → Demo Accounts', route('admin.growth.demos.index'), true);
        }
        if ($this->canOpenRoute($user, $nav, 'admin.customers.index') && (str_contains($hay, 'customer') || str_contains($hay, 'asha'))) {
            $candidates[] = $this->item('Find customer', 'Customers', route('admin.customers.index'), true);
        }
        if ($permissions->has($user, 'marketing.offers.manage') && str_contains($hay, 'offer')) {
            $candidates[] = $this->item('Create offer', 'Growth → Offers', route('admin.growth.offers.index'), true);
        }
        if ($this->canOpenRoute($user, $nav, 'admin.customers.grade-watch') && str_contains($hay, 'grade')) {
            $candidates[] = $this->item('Open Grade Watch', 'Customers → Grade Watch', route('admin.customers.grade-watch'), true);
        }

        return array_values(array_filter($candidates, fn ($item) => $this->matches($hay, $item['title'].' '.$item['subtitle'])));
    }

    private function canOpenRoute(User $user, ConsoleNavService $nav, string $route): bool
    {
        foreach ($nav->visibleSections($user) as $section) {
            foreach ($section['items'] as $item) {
                if (($item[1] ?? '') === $route) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pages(User $user, string $q, ConsoleNavService $nav): array
    {
        $hay = Str::lower($q);
        $items = [];
        foreach ($nav->visibleSections($user) as $section) {
            foreach ($section['items'] as $item) {
                if (($item[1] ?? '') === '__group__') {
                    continue;
                }
                $label = (string) $item[0];
                $route = (string) $item[1];
                if (! $this->matches($hay, $section['label'].' '.$label.' '.$route)) {
                    continue;
                }
                try {
                    $url = route($route, is_array($item[3] ?? null) ? $item[3] : []);
                } catch (\Throwable) {
                    continue;
                }
                $items[] = $this->item($label, $section['label'], $url);
            }
        }

        return $items;
    }

    private function settings(string $q): array
    {
        $hay = Str::lower($q);
        $items = [];
        foreach (config('settings_nav', []) as $groupName => $links) {
            foreach ($links as $link) {
                $label = (string) $link[0];
                $route = (string) $link[1];
                $keywords = strtolower((string) ($link[3] ?? ''));
                if (! $this->matches($hay, $groupName.' '.$label.' '.$keywords)) {
                    continue;
                }
                try {
                    $url = route($route);
                } catch (\Throwable) {
                    continue;
                }
                $items[] = $this->item($label, 'Settings → '.$groupName, $url);
            }
        }

        return $items;
    }

    private function customers(string $q): array
    {
        $like = '%'.$q.'%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';

        return Customer::query()
            ->when($digits !== '' && strlen($digits) >= 4, fn ($query) => $query->where('phone', 'like', '%'.$digits.'%'))
            ->when($digits === '' || strlen($digits) < 4, function ($query) use ($like) {
                $query->where(function ($inner) use ($like) {
                    $inner->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('customer_number', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function (Customer $customer) {
                $phone = (string) $customer->phone;
                $masked = strlen($phone) > 6
                    ? substr($phone, 0, 4).'••••'.substr($phone, -2)
                    : $phone;

                return $this->item(
                    $customer->full_name !== '' ? $customer->full_name : (string) $customer->customer_number,
                    trim($masked.' · '.strtoupper((string) ($customer->grade ?: 'bronze'))),
                    route('admin.customers.show', $customer),
                );
            })
            ->all();
    }

    private function loans(string $q): array
    {
        $like = '%'.$q.'%';

        return Loan::query()
            ->with('customer')
            ->where(function ($query) use ($like, $q) {
                $query->where('loan_number', 'like', $like);
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Loan $loan) => $this->item(
                (string) ($loan->loan_number ?: 'Loan #'.$loan->id),
                trim(($loan->customer?->full_name ?? '').' · '.strtoupper((string) $loan->status)),
                route('admin.loans.show', $loan),
            ))
            ->all();
    }

    private function campaigns(string $q): array
    {
        $like = '%'.$q.'%';

        return Promotion::query()
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('code', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Promotion $promo) => $this->item(
                $promo->name,
                'Campaign · '.$promo->status,
                route('admin.promotions.show', $promo),
            ))
            ->all();
    }

    private function demos(string $q): array
    {
        $like = '%'.$q.'%';

        return MarketingDemoSession::query()
            ->where('display_name', 'like', $like)
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (MarketingDemoSession $demo) => $this->item(
                $demo->display_name,
                'Demo · '.$demo->status,
                route('admin.growth.demos.show', $demo),
            ))
            ->all();
    }

    private function offers(string $q): array
    {
        $like = '%'.$q.'%';

        return PlusOffer::query()
            ->where('title', 'like', $like)
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (PlusOffer $offer) => $this->item(
                $offer->title,
                'Offer · '.($offer->active ? 'Active' : 'Off'),
                route('admin.growth.offers.index'),
            ))
            ->all();
    }

    private function matches(string $hay, string $text): bool
    {
        $text = Str::lower($text);
        foreach (preg_split('/\s+/', $hay) ?: [] as $token) {
            if ($token !== '' && ! str_contains($text, $token)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{title: string, subtitle: string, url: string, action: bool} */
    private function item(string $title, string $subtitle, string $url, bool $action = false): array
    {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'url' => $url,
            'action' => $action,
        ];
    }
}
