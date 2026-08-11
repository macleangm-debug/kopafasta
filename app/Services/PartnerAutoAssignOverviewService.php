<?php

namespace App\Services;

use App\Models\Vendor;

class PartnerAutoAssignOverviewService
{
    public function __construct(
        private readonly PartnerAutoAssignPolicy $policy,
        private readonly RecoveryPolicyService $recoveryPolicy,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function boards(): array
    {
        $strategies = config('partner_auto_assign.strategies', []);
        $boards = [];

        foreach ($this->recoveryPolicy->partnerTypes() as $type => $meta) {
            $settings = $this->policy->forRecoveryType($type);
            $category = (string) ($meta['vendor_category'] ?? $type);

            $boards[] = [
                'key' => 'recovery_'.$type,
                'group' => 'recovery',
                'type' => $type,
                'suffix' => $type,
                'label' => (string) ($meta['label'] ?? $type),
                'category' => $category,
                'settings' => $settings,
                'show_sla_days' => false,
                'strategy_label' => $strategies[$settings['strategy'] ?? ''] ?? ($settings['strategy'] ?? 'least_load'),
                'kpi_source' => 'Recovery KPIs: recovery rate, open roster, SLA breaches, fairness (time since last assignment).',
                'partner_count' => $this->activePartnerCount($category),
                'create_url' => route('admin.partners.create', ['category' => $category]),
            ];
        }

        foreach (config('partner_defaults.categories', []) as $category => $meta) {
            $settings = $this->policy->forServiceCategory($category);

            $boards[] = [
                'key' => 'service_'.$category,
                'group' => 'service',
                'type' => $category,
                'suffix' => 'svc_'.$category,
                'label' => (string) ($meta['label'] ?? $category),
                'category' => $category,
                'settings' => $settings,
                'show_sla_days' => true,
                'strategy_label' => $strategies[$settings['strategy'] ?? ''] ?? ($settings['strategy'] ?? 'least_load'),
                'kpi_source' => 'Service KPIs: task completion rate, open roster, fairness (time since last task).',
                'partner_count' => $this->activePartnerCount($category),
                'create_url' => route('admin.partners.create', ['category' => $meta['add_category'] ?? $category]),
            ];
        }

        return $boards;
    }

    private function activePartnerCount(string $category): int
    {
        return (int) Vendor::query()
            ->where('status', 'active')
            ->where(function ($q) use ($category): void {
                $q->where('category', $category)
                    ->orWhere('roles', 'like', '%"'.$category.'"%');
            })
            ->count();
    }
}
