<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    public function __construct(
        private readonly GamificationSettingsService $settings,
        private readonly PortalContextService $portal,
    ) {}

    /** @return list<string> */
    public function categories(): array
    {
        return $this->settings->notificationCategories();
    }

    /** @return array<string, Collection<int, NotificationLog>> */
    public function groupedForCustomer(Customer $customer, ?string $category = null): array
    {
        $query = $this->portal->borrowerNotificationsQuery($customer)->latest();

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $items = $query->get();
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $groups = [
            'today'    => collect(),
            'yesterday'=> collect(),
            'earlier'  => collect(),
        ];

        foreach ($items as $item) {
            $date = Carbon::parse($item->created_at)->startOfDay();

            if ($date->equalTo($today)) {
                $groups['today']->push($item);
            } elseif ($date->equalTo($yesterday)) {
                $groups['yesterday']->push($item);
            } else {
                $groups['earlier']->push($item);
            }
        }

        return $groups;
    }

    public function normalizeCategory(?string $category): string
    {
        $map = [
            'payment'  => 'repayment',
            'loan'     => 'repayment',
            'kyc'      => 'application',
            'document' => 'application',
            'system'   => 'promotions',
            'guarantor'=> 'application',
        ];

        $normalized = $map[$category] ?? $category ?? 'application';

        if (! in_array($normalized, $this->categories(), true)) {
            return 'application';
        }

        return $normalized;
    }

    public function categoryLabel(string $category): string
    {
        $key = 'borrower.notifications.categories.'.$category;
        $label = __($key);

        return $label !== $key ? $label : ucfirst(str_replace('_', ' ', $category));
    }
}
