<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class PartnerCoverageRequestService
{
    public const CATEGORIES = [
        'valuer',
        'gps_installer',
        'insurance',
        'any',
    ];

    public function __construct(
        private readonly PartnerAssignmentNotifier $notifier,
        private readonly ValuationPartnerService $valuers,
        private readonly PartnerRegionCoverage $coverage,
    ) {}

    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'valuer' => 'valuer',
            'gps_installer' => 'GPS installer',
            'insurance' => 'insurance partner',
            'any' => 'partner',
            default => str_replace('_', ' ', $category),
        };
    }

    /** @return array<string, mixed>|null */
    public function openRequest(LoanApplication $application, string $category): ?array
    {
        $this->clearIfCovered($application);

        foreach ($this->requests($application->fresh()) as $row) {
            if (($row['status'] ?? '') !== 'open') {
                continue;
            }
            if ((string) ($row['category'] ?? '') !== $category) {
                continue;
            }

            return $row;
        }

        return null;
    }

    public function request(LoanApplication $application, User $actor, string $category, ?string $note = null): bool
    {
        if ($this->openRequest($application, $category)) {
            return false;
        }

        $application->refresh();
        $region = $application->customer?->region;
        $payload = (array) ($application->screening_payload ?? []);
        $requests = $this->requests($application);
        $requests[] = [
            'category' => $category,
            'region' => $region,
            'status' => 'open',
            'note' => $note,
            'requested_by' => $actor->id,
            'requested_at' => now()->toIso8601String(),
            'application_number' => $application->application_number,
        ];
        $payload['partner_coverage_requests'] = $requests;
        $payload['partner_coverage_open'] = true;
        $application->update(['screening_payload' => $payload]);

        $label = $this->categoryLabel($category);
        $regionBit = filled($region) ? ' covering '.$region : '';

        $this->notifier->notifyPartnerManagers(
            'Partner needed'.$regionBit,
            trim($actor->name.' asked Partner support to add a '.$label.$regionBit
                .' for '.$application->application_number
                .'. Add the region on an existing partner, or enroll a new one.'),
            $this->reviewUrl($application),
        );

        return true;
    }

    public function clearIfCovered(LoanApplication $application): void
    {
        $requests = $this->requests($application);
        if ($requests === []) {
            return;
        }

        $region = $application->customer?->region;
        $changed = false;
        foreach ($requests as $i => $row) {
            if (($row['status'] ?? '') !== 'open') {
                continue;
            }
            if (! $this->isCovered($application, (string) ($row['category'] ?? ''), $region)) {
                continue;
            }
            $requests[$i]['status'] = 'closed';
            $requests[$i]['closed_at'] = now()->toIso8601String();
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $this->storeRequests($application, $requests);
    }

    public function closeForApplication(LoanApplication $application, ?string $category = null): void
    {
        $requests = $this->requests($application);
        $changed = false;
        foreach ($requests as $i => $row) {
            if (($row['status'] ?? '') !== 'open') {
                continue;
            }
            if ($category && (string) ($row['category'] ?? '') !== $category) {
                continue;
            }
            $requests[$i]['status'] = 'closed';
            $requests[$i]['closed_at'] = now()->toIso8601String();
            $changed = true;
        }
        if ($changed) {
            $this->storeRequests($application, $requests);
        }
    }

    /** @return Collection<int, array{key: string, label: string, count: int, url: string, category: string}> */
    public function staffAlerts(): Collection
    {
        $rows = LoanApplication::query()
            ->whereNotIn('status', LoanApplication::CLOSED_STATUSES)
            ->where('screening_payload->partner_coverage_open', true)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'application_number', 'screening_payload']);

        return $rows->map(function (LoanApplication $application) {
            $open = collect($this->requests($application))
                ->first(fn ($row) => ($row['status'] ?? '') === 'open');
            $category = (string) ($open['category'] ?? 'valuer');
            $region = $open['region'] ?? null;

            return [
                'key' => 'partner_coverage_'.$application->id,
                'label' => 'Partner needed'
                    .(filled($region) ? ' in '.$region : '')
                    .' · '.$application->application_number,
                'count' => 1,
                'url' => $this->reviewUrl($application),
                'category' => 'partners',
            ];
        })->values();
    }

    public function reviewUrl(LoanApplication $application): string
    {
        return route('admin.partners.coverage-request', $application);
    }

    /**
     * Existing partners of this type who do not yet cover the borrower region.
     *
     * @return list<array{
     *   partner: Vendor,
     *   coverage: string,
     *   home_region: ?string,
     *   connected: bool,
     *   connection: ?string,
     *   can_add_region: bool
     * }>
     */
    public function existingCandidates(LoanApplication $application, string $category): array
    {
        $region = $application->customer?->region;
        $categories = $category === 'any'
            ? ['valuer', 'gps_installer', 'insurance']
            : [$category];

        $rows = [];
        foreach ($categories as $type) {
            foreach ($this->partnersOfType($type) as $partner) {
                if ($this->coverage->covers($partner, $region)) {
                    continue;
                }
                $home = $this->homeRegion($partner);
                $connection = $this->connectionLabel($partner, $region, $home);
                $rows[] = [
                    'partner' => $partner,
                    'type' => $type,
                    'coverage' => $this->coverage->label($partner),
                    'home_region' => $home,
                    'connected' => $connection !== null && filled($region) && strcasecmp((string) $home, (string) $region) === 0,
                    'connection' => $connection,
                    'can_add_region' => filled($region) && ($partner->coverage_type ?? 'regions') !== 'nationwide',
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            return [$b['connected'], $a['partner']->name] <=> [$a['connected'], $b['partner']->name];
        });

        return $rows;
    }

    public function addRegion(Vendor $partner, string $region): void
    {
        $region = trim($region);
        if ($region === '' || ($partner->coverage_type ?? 'regions') === 'nationwide') {
            return;
        }

        $regions = array_values(array_unique(array_filter(array_merge(
            array_map('strval', $partner->regions ?? []),
            [$region],
        ))));

        $partner->update([
            'coverage_type' => 'regions',
            'regions' => $regions,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function requests(LoanApplication $application): array
    {
        $rows = data_get($application->screening_payload, 'partner_coverage_requests', []);

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param  list<array<string, mixed>>  $requests */
    private function storeRequests(LoanApplication $application, array $requests): void
    {
        $payload = (array) ($application->screening_payload ?? []);
        $payload['partner_coverage_requests'] = $requests;
        $payload['partner_coverage_open'] = collect($requests)
            ->contains(fn ($row) => ($row['status'] ?? '') === 'open');
        $application->update(['screening_payload' => $payload]);
    }

    private function isCovered(LoanApplication $application, string $category, ?string $region): bool
    {
        if ($category === 'valuer') {
            $valuer = $this->valuers->suggestValuer($application);

            return $valuer !== null && $this->coverage->covers($valuer, $region);
        }

        return false;
    }

    /** @return Collection<int, Vendor> */
    private function partnersOfType(string $category): Collection
    {
        return Vendor::query()
            ->whereNotIn('status', ['suspended'])
            ->where(function ($q) use ($category): void {
                $q->where('category', $category)->orWhere('roles', 'like', '%"'.$category.'"%');
            })
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('name')
            ->get();
    }

    private function homeRegion(Vendor $partner): ?string
    {
        $home = trim((string) ($partner->region
            ?: data_get($partner->metadata, 'residence.region')
            ?: ''));

        return $home !== '' ? $home : null;
    }

    private function connectionLabel(Vendor $partner, ?string $region, ?string $home): ?string
    {
        if (filled($region) && filled($home) && strcasecmp($home, $region) === 0) {
            return 'Based in '.$region;
        }

        $cover = array_values(array_filter(array_map('strval', $partner->regions ?? [])));
        if ($cover !== [] && filled($region)) {
            return 'Already covers '.implode(', ', array_slice($cover, 0, 3))
                .' — add '.$region.' if they can work there';
        }

        if (filled($home)) {
            return 'Registered in '.$home;
        }

        return null;
    }
}
