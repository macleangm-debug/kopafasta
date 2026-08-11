<?php

namespace App\Services;

use App\Models\RecoveryAssignment;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RecoveryPartnerService
{
    public function __construct(
        private readonly RecoveryPolicyService $policy,
    ) {}

    /** @return list<string> */
    public function recoveryVendorCategories(): array
    {
        return config('recovery.vendor_categories', []);
    }

    public function isRecoveryPartner(Vendor $vendor): bool
    {
        $categories = $this->recoveryVendorCategories();

        return in_array((string) $vendor->category, $categories, true)
            || array_intersect($vendor->partnerRoles(), $categories) !== [];
    }

    /** @return array<string, string> */
    public function partnerTypeOptions(): array
    {
        return collect($this->policy->partnerTypes())
            ->mapWithKeys(fn (array $row, string $key) => [$key => $row['label']])
            ->all();
    }

    public function partnerTypeForVendor(Vendor $vendor): ?string
    {
        foreach ($this->policy->partnerTypes() as $type => $meta) {
            $category = $meta['vendor_category'] ?? null;
            if ($category && ($vendor->category === $category || $vendor->hasPartnerRole($category))) {
                return $type;
            }
        }

        return null;
    }

    public function filteredQuery(?string $partnerType = null, ?string $search = null): Builder
    {
        $category = $partnerType ? $this->policy->vendorCategoryForType($partnerType) : null;

        return Vendor::query()
            ->when($category, fn (Builder $q) => $q->where(function (Builder $qq) use ($category): void {
                $qq->where('category', $category)
                    ->orWhere('roles', 'like', '%"'.$category.'"%');
            }))
            ->when(! $category, fn (Builder $q) => $q->where(function (Builder $qq): void {
                foreach ($this->recoveryVendorCategories() as $cat) {
                    $qq->orWhere('category', $cat)
                        ->orWhere('roles', 'like', '%"'.$cat.'"%');
                }
            }))
            ->when(filled($search), function (Builder $q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function (Builder $qq) use ($term): void {
                    $qq->where('name', 'like', $term)
                        ->orWhere('partner_number', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });
    }

    /** @return array<string, int|float> */
    public function statsForVendor(Vendor $vendor): array
    {
        $assignments = RecoveryAssignment::query()->where('partner_id', $vendor->id);

        return [
            'assignments'       => (int) $assignments->count(),
            'active_cases'      => (int) (clone $assignments)->whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed_cases'   => (int) (clone $assignments)->where('status', 'completed')->count(),
            'sla_breaches'      => (int) (clone $assignments)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->where('sla_due_at', '<', now())
                ->count(),
            'commission_earned' => (float) (clone $assignments)->sum('commission_earned'),
            'commission_paid'   => (float) (clone $assignments)->sum('commission_paid'),
        ];
    }

    /** @return Collection<int, Vendor> */
    public function activePartnersForType(string $partnerType): Collection
    {
        $category = $this->policy->vendorCategoryForType($partnerType);

        if (! $category) {
            return collect();
        }

        return Vendor::query()
            ->where('status', 'active')
            ->where(function (Builder $q) use ($category): void {
                $q->where('category', $category)
                    ->orWhere('roles', 'like', '%"'.$category.'"%');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Active partners for a recovery type filtered by borrower region (nationwide or listed).
     *
     * @return Collection<int, Vendor>
     */
    public function activePartnersForTypeInRegion(string $partnerType, ?string $region): Collection
    {
        $all = $this->activePartnersForType($partnerType);
        $settings = app(PartnerAutoAssignPolicy::class)->forRecoveryType($partnerType);
        $requireRegion = (bool) ($settings['require_region'] ?? false);

        return app(PartnerRegionCoverage::class)->filterAvailable($all, $region, $requireRegion);
    }
}
