<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GpsPartnerService
{
    public function __construct(
        private readonly PartnerMatchingService $matching,
    ) {}

    public function suggestInstaller(LoanApplication $application): ?Vendor
    {
        return $this->installersForApplication($application)->first();
    }

    /** @return \Illuminate\Support\Collection<int, Vendor> */
    public function installersForApplication(LoanApplication $application): \Illuminate\Support\Collection
    {
        $application->loadMissing('customer');
        $region = $application->customer?->region;

        $query = Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'gps_installer')->orWhere('roles', 'like', '%"gps_installer"%');
            });

        if (blank($region)) {
            return $query->orderBy('name')->get();
        }

        $matches = $query->get()->filter(function (Vendor $vendor) use ($region): bool {
            if (($vendor->coverage_type ?? 'regions') === 'nationwide') {
                return true;
            }

            $regions = $vendor->regions ?? [];

            return $regions === [] || in_array($region, $regions, true);
        });

        return $matches->isNotEmpty()
            ? $matches->sortBy('name')->values()
            : $query->orderBy('name')->get();
    }

    public function assign(LoanApplication $application, Vendor $installer, User $actor, ?string $notes = null): VendorTask
    {
        if ($installer->category !== 'gps_installer' && ! $installer->hasPartnerRole('gps_installer')) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Selected partner is not a GPS installer.',
            ]);
        }

        $open = VendorTask::query()
            ->where('loan_application_id', $application->id)
            ->where('task_type', 'gps_install')
            ->whereIn('status', ['assigned', 'in_progress'])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'gps' => 'This application already has an open GPS installation task.',
            ]);
        }

        return DB::transaction(function () use ($application, $installer, $notes) {
            $application->loadMissing(['customer', 'assetReservation.asset']);
            $customer = $application->customer;
            $asset = $application->assetReservation?->asset;

            $location = trim(collect([
                $customer->street ?? null,
                $customer->district ?? null,
                $customer->region ?? null,
            ])->filter()->implode(', '));

            return VendorTask::create([
                'vendor_id'           => $installer->id,
                'loan_id'             => $application->loan_id,
                'loan_application_id' => $application->id,
                'task_type'           => 'gps_install',
                'status'              => 'assigned',
                'due_at'              => now()->addDays(3),
                'customer_name'       => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
                'customer_phone'      => $customer->phone ?? null,
                'vehicle_details'     => $asset?->title,
                'location'            => $location ?: null,
                'notes'               => $notes,
            ]);
        });
    }
}
