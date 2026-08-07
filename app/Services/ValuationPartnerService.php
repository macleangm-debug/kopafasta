<?php

namespace App\Services;

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

    public function suggestValuer(LoanApplication $application): ?Vendor
    {
        return $this->matching->suggestValuer($application);
    }

    /** @return \Illuminate\Support\Collection<int, Vendor> */
    public function valuersForApplication(LoanApplication $application): \Illuminate\Support\Collection
    {
        $application->loadMissing('customer');

        return $this->matching->valuersForRegion($application->customer?->region);
    }

    public function assign(LoanApplication $application, Vendor $valuer, User $actor, ?string $notes = null): ValuationAssignment
    {
        if ($valuer->category !== 'valuer' && ! $valuer->hasPartnerRole('valuer')) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Selected partner is not a valuation partner.',
            ]);
        }

        $asset = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('uw_status', '!=', LoanApplicationAsset::UW_DECLINED)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if (! $asset) {
            $asset = LoanApplicationAsset::query()->create([
                'loan_application_id' => $application->id,
                'asset_type'          => 'saloon_car',
                'valuation_status'    => 'awaiting_valuation',
                'uw_status'           => LoanApplicationAsset::UW_PENDING,
                'is_primary'          => true,
            ]);
        }

        $open = ValuationAssignment::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'valuation' => 'This application already has an open valuation assignment.',
            ]);
        }

        return DB::transaction(function () use ($application, $valuer, $actor, $notes, $asset) {
            $customer = $application->customer;
            $assetDescription = trim(collect([
                $asset->description,
                $asset->asset_type ? ucfirst(str_replace('_', ' ', $asset->asset_type)) : null,
            ])->filter()->implode(' · '));

            $location = trim(collect([
                $customer->street ?? null,
                $customer->district ?? null,
                $customer->region ?? null,
            ])->filter()->implode(', '));

            $task = VendorTask::create([
                'vendor_id'       => $valuer->id,
                'loan_id'         => $application->loan_id,
                'loan_application_id' => $application->id,
                'task_type'       => 'asset_valuation',
                'status'          => 'assigned',
                'due_at'          => now()->addDays(5),
                'customer_name'   => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
                'customer_phone'  => $customer->phone ?? null,
                'vehicle_details' => $assetDescription ?: null,
                'location'        => $location ?: null,
                'instructions'    => $notes ?: 'Inspect the asset physically, upload photos, and submit market and forced sale values.',
                'notes'           => $notes,
                'fee_amount'      => (int) round(app(PartnerDefaultsService::class)->valuerBaseCost($valuer)),
            ]);

            $assignment = ValuationAssignment::create([
                'loan_application_id' => $application->id,
                'vendor_id'           => $valuer->id,
                'vendor_task_id'      => $task->id,
                'status'              => ValuationAssignment::STATUS_ASSIGNED,
                'notes'               => $notes,
                'assigned_by'         => $actor->id,
                'assigned_at'         => now(),
            ]);

            $asset->update(['valuation_status' => 'assigned']);

            return $assignment->fresh(['vendor', 'vendorTask', 'application.customer']);
        });
    }

    public function complete(
        ValuationAssignment $assignment,
        float $marketValue,
        float $forcedSaleValue,
        ?string $notes = null,
    ): ValuationAssignment {
        if (! in_array($assignment->status, [ValuationAssignment::STATUS_ASSIGNED, ValuationAssignment::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'status' => 'Valuation assignment is already closed.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $marketValue, $forcedSaleValue, $notes) {
            $assignment->update([
                'status'            => ValuationAssignment::STATUS_COMPLETED,
                'market_value'      => $marketValue,
                'forced_sale_value' => $forcedSaleValue,
                'notes'             => trim(($assignment->notes ? $assignment->notes."\n" : '').($notes ?? '')),
                'completed_at'      => now(),
            ]);

            $assignment->vendorTask?->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $application = $assignment->application;
            $assetType = LoanApplicationAsset::query()
                ->where('loan_application_id', $application->id)
                ->value('asset_type') ?? 'saloon_car';

            $ltvPercent = (float) (config("repossession_charges.ltv_percent.{$assetType}")
                ?? config('repossession_charges.ltv_percent.default', 60));

            $maxLoan = round($forcedSaleValue * ($ltvPercent / 100), 2);
            $gpsRequired = in_array($assetType, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery'], true);

            LoanApplicationAsset::query()->updateOrCreate(
                ['loan_application_id' => $application->id],
                [
                    'market_value'      => $marketValue,
                    'forced_sale_value' => $forcedSaleValue,
                    'ltv_percent'       => $ltvPercent,
                    'max_loan_amount'   => $maxLoan,
                    'gps_required'      => $gpsRequired,
                    'valuation_status'  => 'completed',
                    'valuer_notes'      => $notes,
                ],
            );

            return $assignment->fresh(['application.collateralAsset']);
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
