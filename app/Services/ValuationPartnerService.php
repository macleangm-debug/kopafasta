<?php

namespace App\Services;

use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValuationPartnerService
{
    public function __construct(
        private readonly PartnerMatchingService $matching,
    ) {}

    /** @param  list<int>  $excludeIds */
    public function suggestValuer(LoanApplication $application, array $excludeIds = []): ?Vendor
    {
        return $this->matching->suggestValuer($application, $excludeIds);
    }

    /** @return \Illuminate\Support\Collection<int, Vendor> */
    public function valuersForApplication(LoanApplication $application): \Illuminate\Support\Collection
    {
        $application->loadMissing('customer');

        return $this->matching->valuersForRegion($application->customer?->region);
    }

    public function assign(LoanApplication $application, Vendor $valuer, ?User $actor = null, ?string $notes = null): ValuationAssignment
    {
        if ($valuer->category !== 'valuer' && ! $valuer->hasPartnerRole('valuer')) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Selected partner is not a valuation partner.',
            ]);
        }

        $designatedIds = app(CustomerAssetService::class)->onLoanAssetIds($application);
        $pledges = LoanApplicationAsset::query()
            ->with('customerAsset')
            ->where('loan_application_id', $application->id)
            ->where('uw_status', '!=', LoanApplicationAsset::UW_DECLINED)
            ->when($designatedIds !== [], fn ($q) => $q->whereIn('customer_asset_id', $designatedIds))
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
        $asset = $pledges->first();
        if (! $asset) {
            $asset = LoanApplicationAsset::query()->create([
                'loan_application_id' => $application->id,
                'asset_type'          => 'saloon_car',
                'valuation_status'    => 'awaiting_valuation',
                'uw_status'           => LoanApplicationAsset::UW_PENDING,
                'is_primary'          => true,
            ]);
            $pledges = collect([$asset]);
        }

        $asset->loadMissing('customerAsset');

        $open = ValuationAssignment::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'valuation' => 'This application already has an open valuation assignment.',
            ]);
        }

        $assetCount = max(1, $pledges->filter(fn ($row) => $row->customer_asset_id)->count());
        if (! app(AssetBackedLoanService::class)->isAssetBackedApplication($application)) {
            $due = (int) quoted_valuation_fee($application->customer, $assetCount);
            $state = app(CollateralSecureService::class)->state($application);
            $assetPaid = $pledges->contains(fn ($row) => filled($row->valuation_fee_paid_at));
            $statePaid = filled($state['valuation_fee_paid_at'] ?? null);
            if ($due > 0 && ! $assetPaid && ! $statePaid) {
                throw ValidationException::withMessages([
                    'valuation' => 'The borrower must pay the valuation fee before a valuer is assigned.',
                ]);
            }
        }

        return DB::transaction(function () use ($application, $valuer, $actor, $notes, $asset, $pledges, $assetCount) {
            $customer = $application->customer;
            $labels = $pledges->map(function (LoanApplicationAsset $row) {
                return trim(collect([
                    $row->customerAsset?->label,
                    $row->description,
                    $row->asset_type ? ucfirst(str_replace('_', ' ', $row->asset_type)) : null,
                ])->filter()->implode(' · '));
            })->filter()->values();
            $assetDescription = $labels->implode(' | ');

            $location = trim(collect([
                $customer->street ?? null,
                $customer->district ?? null,
                $customer->region ?? null,
            ])->filter()->implode(', '));

            $slaDays = app(PartnerAutoAssignPolicy::class)->slaDaysForService('valuer');

            $profileIds = $pledges->pluck('customer_asset_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
            $profileAsset = $asset->customerAsset;
            $angleLabels = CustomerAsset::photoAngleLabels($profileAsset?->asset_type);
            $taskNotes = json_encode([
                'message' => $notes,
                'customer_asset_id' => $profileIds[0] ?? $profileAsset?->id,
                'customer_asset_ids' => $profileIds,
                'photo_angles' => array_keys($angleLabels),
            ], JSON_UNESCAPED_UNICODE);
            $angleList = implode(', ', array_values($angleLabels));
            $instruction = ($notes ?: 'Inspect each pledged asset physically, upload photos, and submit market and forced sale values per asset.')
                ."\n\nAssets on this job: ".($assetDescription ?: '1 asset')
                .".\nTake the same angles as the borrower profile: {$angleList}.";

            $unitCost = (int) round(app(PartnerDefaultsService::class)->valuerBaseCost($valuer));
            $task = VendorTask::create([
                'vendor_id'       => $valuer->id,
                'loan_id'         => $application->loan_id,
                'loan_application_id' => $application->id,
                'task_type'       => 'asset_valuation',
                'status'          => 'assigned',
                'due_at'          => now()->addDays($slaDays),
                'customer_name'   => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
                'customer_phone'  => $customer->phone ?? null,
                'vehicle_details' => $assetDescription ?: null,
                'location'        => $location ?: null,
                'instructions'    => $instruction,
                'notes'           => $taskNotes,
                'fee_amount'      => $unitCost * $assetCount,
            ]);

            $assignment = ValuationAssignment::create([
                'loan_application_id' => $application->id,
                'vendor_id'           => $valuer->id,
                'vendor_task_id'      => $task->id,
                'status'              => ValuationAssignment::STATUS_ASSIGNED,
                'notes'               => $notes,
                'assigned_by'         => $actor?->id,
                'assigned_at'         => now(),
            ]);

            foreach ($pledges as $pledge) {
                $pledge->update(['valuation_status' => 'assigned']);
            }

            $fresh = $assignment->fresh(['vendor', 'vendorTask', 'application.customer']);

            app(PartnerAssignmentNotifier::class)->notifyAssigned($valuer, 'Asset valuation', [
                'title' => 'New valuation task',
                'body' => 'Valuation assigned for application '.($application->application_number ?? '#'.$application->id).'. SLA '.$slaDays.' day(s).',
                'action_url' => '/partner/tasks',
                'staff_permission' => 'applications.view',
                'staff_url' => route('admin.loan-applications.show', $application),
            ]);

            return $fresh;
        });
    }

    /**
     * After valuation fee is paid, try to place the job with the nearest/suggested valuer.
     * Failures are non-blocking — ops can still assign manually.
     */
    /**
     * @param  list<int>  $excludeIds
     */
    public function autoAssignIfPossible(
        LoanApplication $application,
        ?User $actor = null,
        array $excludeIds = [],
        ?string $notes = null,
    ): ?ValuationAssignment {
        if (! app(PartnerAutoAssignPolicy::class)->enabledForService('valuer')) {
            return null;
        }

        $open = ValuationAssignment::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->exists();

        if ($open) {
            return null;
        }

        $valuer = $this->suggestValuer($application, $excludeIds);
        if (! $valuer) {
            return null;
        }

        try {
            return $this->assign(
                $application,
                $valuer,
                $actor,
                $notes ?? 'Auto-assigned after valuation fee payment.',
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function complete(
        ValuationAssignment $assignment,
        float $marketValue,
        float $forcedSaleValue,
        ?string $notes = null,
        array $perAsset = [],
    ): ValuationAssignment {
        if (! in_array($assignment->status, [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'status' => 'Valuation assignment is already closed.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $marketValue, $forcedSaleValue, $notes, $perAsset) {
            $assignment->update([
                'status'            => ValuationAssignment::STATUS_COMPLETED,
                'market_value'      => $marketValue,
                'forced_sale_value' => $forcedSaleValue,
                'notes'             => trim(($assignment->notes ? $assignment->notes."\n" : '').($notes ?? '')),
                'completed_at'      => now(),
            ]);

            $task = $assignment->vendorTask;
            $partnerShare = (int) ($task?->fee_amount ?? 0);
            if ($partnerShare <= 0) {
                $partnerShare = (int) round(app(PartnerDefaultsService::class)->valuerBaseCost($assignment->vendor));
            }

            $task?->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'fee_amount'   => $partnerShare > 0 ? $partnerShare : $task->fee_amount,
            ]);

            if ($partnerShare > 0 && $assignment->vendor_id && $task) {
                $already = \App\Models\VendorPayment::query()
                    ->where('partner_task_id', $task->id)
                    ->where('source_type', 'valuation_fee')
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if (! $already) {
                    $partner = $assignment->vendor ?? \App\Models\Vendor::query()->find($assignment->vendor_id);
                    if ($partner) {
                        app(PartnerSettlementService::class)->accrue(
                            $partner,
                            $partnerShare,
                            'valuation_fee',
                            $assignment->loan_application_id,
                            'Asset valuation '.$assignment->application?->application_number,
                            $task->id,
                        );
                    }
                }
            }

            $application = $assignment->application;
            $keepIds = app(CustomerAssetService::class)->onLoanAssetIds($application);
            $pledges = LoanApplicationAsset::query()
                ->where('loan_application_id', $application->id)
                ->where('uw_status', '!=', LoanApplicationAsset::UW_DECLINED)
                ->when($keepIds !== [], fn ($q) => $q->whereIn('customer_asset_id', $keepIds))
                ->with('customerAsset')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get();
            if ($pledges->isEmpty()) {
                $pledges = collect([
                    LoanApplicationAsset::query()
                        ->where('loan_application_id', $application->id)
                        ->orderByDesc('is_primary')
                        ->orderBy('id')
                        ->first(),
                ])->filter();
            }

            $coverage = app(CollateralCoverageService::class);
            $sumMarket = 0.0;
            $sumFsv = 0.0;
            foreach ($pledges as $pledge) {
                $cid = (int) ($pledge->customer_asset_id ?? 0);
                $ownMarket = (float) ($perAsset[$cid]['market_value'] ?? $perAsset[(string) $cid]['market_value'] ?? $marketValue);
                $ownFsv = (float) ($perAsset[$cid]['forced_sale_value'] ?? $perAsset[(string) $cid]['forced_sale_value'] ?? $forcedSaleValue);
                $assetType = (string) ($pledge->asset_type ?: $pledge->customerAsset?->asset_type ?: 'saloon_car');
                $ltvPercent = $coverage->ltvPercentFor($assetType);
                $maxLoan = $coverage->maxLoanFromForcedSale($ownFsv, $assetType);
                $gpsRequired = in_array($assetType, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery', 'vehicle'], true);
                $pledge->update([
                    'market_value' => $ownMarket,
                    'forced_sale_value' => $ownFsv,
                    'ltv_percent' => $ltvPercent,
                    'max_loan_amount' => $maxLoan,
                    'gps_required' => $gpsRequired,
                    'valuation_status' => 'completed',
                    'valuer_notes' => $notes,
                ]);
                $sumMarket += $ownMarket;
                $sumFsv += $ownFsv;
            }

            $assignment->update([
                'market_value' => $sumMarket > 0 ? $sumMarket : $marketValue,
                'forced_sale_value' => $sumFsv > 0 ? $sumFsv : $forcedSaleValue,
            ]);

            $fresh = $assignment->fresh(['application.collateralAsset']);
            $coverage->evaluate($application->fresh());

            return $fresh;
        });
    }

    public function markInProgress(ValuationAssignment $assignment): ValuationAssignment
    {
        if ($assignment->status !== ValuationAssignment::STATUS_ASSIGNED) {
            return $assignment;
        }

        $assignment->update(['status' => ValuationAssignment::STATUS_IN_PROGRESS]);
        $assignment->vendorTask?->update(['status' => 'in_progress', 'started_at' => now()]);

        return $assignment->fresh();
    }

    /** @return array<string, mixed>|null */
    public function reportForApplication(LoanApplication $application): ?array
    {
        $assignment = ValuationAssignment::query()
            ->with(['vendor', 'vendorTask.documents', 'assigner'])
            ->where('loan_application_id', $application->id)
            ->latest('id')
            ->first();

        if (! $assignment) {
            return null;
        }

        $asset = LoanApplicationAsset::query()->where('loan_application_id', $application->id)->first();
        $photos = $assignment->vendorTask?->documents
            ->map(fn ($doc) => [
                'label' => $doc->label,
                'url'   => asset('storage/'.$doc->file_path),
                'doc_type' => $doc->doc_type ?? null,
            ])
            ->values()
            ->all() ?? [];

        return [
            'assignment'        => $assignment,
            'status'            => $assignment->status,
            'valuer_name'       => $assignment->vendor?->name,
            'market_value'      => $assignment->market_value ?? $asset?->market_value,
            'forced_sale_value' => $assignment->forced_sale_value ?? $asset?->forced_sale_value,
            'max_loan_amount'   => $asset?->max_loan_amount,
            'ltv_percent'       => $asset?->ltv_percent,
            'notes'             => $assignment->notes ?? $asset?->valuer_notes,
            'photos'            => $photos,
            'assigned_at'       => $assignment->assigned_at,
            'completed_at'      => $assignment->completed_at,
        ];
    }
}
