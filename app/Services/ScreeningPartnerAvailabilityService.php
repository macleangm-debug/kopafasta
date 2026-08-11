<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class ScreeningPartnerAvailabilityService
{
    /** @var list<string> */
    private const ORIGINATION_CATEGORIES = ['valuer', 'gps_installer', 'insurance'];

    public function __construct(
        private readonly PartnerRegionCoverage $coverage,
        private readonly RecoveryPolicyService $recoveryPolicy,
    ) {}

    /**
     * @return array{
     *   region: ?string,
     *   region_missing: bool,
     *   available: list<array<string, mixed>>,
     *   unavailable: list<array<string, mixed>>,
     *   counts: array{available: int, unavailable: int, by_type_available: array<string, int>, by_type_unavailable: array<string, int>}
     * }
     */
    public function forApplication(LoanApplication $application): array
    {
        $application->loadMissing('customer');
        $region = $application->customer?->region;
        $regionMissing = blank($region);

        $available = [];
        $unavailable = [];
        $byTypeAvailable = [];
        $byTypeUnavailable = [];

        foreach ($this->typeCatalog() as $key => $meta) {
            $all = $this->activePartnersForCategory($meta['category']);
            $avail = $this->coverage->filterAvailable($all, $region, true);
            $unavail = $this->coverage->filterUnavailable($all, $region);

            $byTypeAvailable[$key] = $avail->count();
            $byTypeUnavailable[$key] = $unavail->count();

            foreach ($avail as $partner) {
                $available[] = $this->row($partner, $key, $meta['label'], true, $region);
            }
            foreach ($unavail as $partner) {
                $unavailable[] = $this->row($partner, $key, $meta['label'], false, $region);
            }
        }

        usort($available, fn ($a, $b) => [$a['type_label'], $a['name']] <=> [$b['type_label'], $b['name']]);
        usort($unavailable, fn ($a, $b) => [$a['type_label'], $a['name']] <=> [$b['type_label'], $b['name']]);

        return [
            'region' => $region,
            'region_missing' => $regionMissing,
            'available' => $available,
            'unavailable' => $unavailable,
            'counts' => [
                'available' => count($available),
                'unavailable' => count($unavailable),
                'by_type_available' => $byTypeAvailable,
                'by_type_unavailable' => $byTypeUnavailable,
            ],
        ];
    }

    /** @return array<string, array{label: string, category: string}> */
    private function typeCatalog(): array
    {
        $types = [];
        foreach (self::ORIGINATION_CATEGORIES as $category) {
            $types[$category] = [
                'label' => match ($category) {
                    'valuer' => 'Valuer',
                    'gps_installer' => 'GPS installer',
                    'insurance' => 'Insurance',
                    default => ucfirst(str_replace('_', ' ', $category)),
                },
                'category' => $category,
            ];
        }

        foreach ($this->recoveryPolicy->partnerTypes() as $type => $meta) {
            $category = (string) ($meta['vendor_category'] ?? $type);
            $types[$type] = [
                'label' => (string) ($meta['label'] ?? $type),
                'category' => $category,
            ];
        }

        return $types;
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
    private function row(Vendor $partner, string $typeKey, string $typeLabel, bool $available, ?string $region): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'partner_number' => $partner->vendor_number ?? $partner->partner_number ?? null,
            'phone' => $partner->phone,
            'type' => $typeKey,
            'type_label' => $typeLabel,
            'coverage' => $this->coverage->label($partner),
            'coverage_type' => $partner->coverage_type ?? 'regions',
            'available' => $available,
            'region' => $region,
            'edit_url' => route('admin.partners.edit', $partner),
            'show_url' => route('admin.partners.show', $partner),
        ];
    }
}
