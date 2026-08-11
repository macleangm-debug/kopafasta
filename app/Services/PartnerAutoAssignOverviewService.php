<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Collection;

class PartnerAutoAssignOverviewService
{
    public function __construct(
        private readonly PartnerAutoAssignPolicy $policy,
        private readonly RecoveryPolicyService $recoveryPolicy,
        private readonly PartnerRegionCoverage $coverage,
        private readonly RecoveryPartnerKpiService $kpis,
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
            $partners = $this->activePartnersForCategory($category);

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
                'partners' => $partners->map(fn (Vendor $p) => $this->partnerRow($p, true))->all(),
                'partner_count' => $partners->count(),
                'create_url' => route('admin.partners.create', ['category' => $category]),
            ];
        }

        foreach (config('partner_defaults.categories', []) as $category => $meta) {
            $settings = $this->policy->forServiceCategory($category);
            $partners = $this->activePartnersForCategory($category);

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
                'partners' => $partners->map(fn (Vendor $p) => $this->partnerRow($p, false))->all(),
                'partner_count' => $partners->count(),
                'create_url' => route('admin.partners.create', ['category' => $meta['add_category'] ?? $category]),
            ];
        }

        return $boards;
    }

    /** @return Collection<int, Vendor> */
    private function activePartnersForCategory(string $category): Collection
    {
        return Vendor::query()
            ->where('status', 'active')
            ->where(function ($q) use ($category): void {
                $q->where('category', $category)
                    ->orWhere('roles', 'like', '%"'.$category.'"%');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerRow(Vendor $partner, bool $includeRecoveryKpi): array
    {
        $row = [
            'id' => $partner->id,
            'name' => $partner->name,
            'number' => $partner->vendor_number ?? $partner->partner_number ?? null,
            'phone' => $partner->phone,
            'coverage' => $this->coverage->label($partner),
            'coverage_type' => $partner->coverage_type ?? 'regions',
            'show_url' => route('admin.partners.show', $partner),
            'edit_url' => route('admin.partners.edit', $partner),
        ];

        if ($includeRecoveryKpi) {
            $kpi = $this->kpis->kpis($partner);
            $row['kpi'] = [
                'open' => $kpi['assigned_cases'] ?? 0,
                'recovery_rate' => $kpi['recovery_rate'] ?? 0,
                'sla_breaches' => $kpi['sla_breaches'] ?? 0,
            ];
        }

        return $row;
    }
}
