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
        private readonly PartnerAutoAssignSelector $selector,
        private readonly PartnerAutoAssignPolicy $autoAssign,
        private readonly PartnerRegionCoverage $coverage,
    ) {}

    public function suggestInstaller(LoanApplication $application): ?Vendor
    {
        $candidates = $this->installersForApplication($application);

        return $this->selector->pickService('gps_installer', $candidates)
            ?? $candidates->first();
    }

    /** @return \Illuminate\Support\Collection<int, Vendor> */
    public function installersForApplication(LoanApplication $application): \Illuminate\Support\Collection
    {
        $application->loadMissing('customer');
        $region = $application->customer?->region;

        $all = Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'gps_installer')->orWhere('roles', 'like', '%"gps_installer"%');
            })
            ->orderBy('name')
            ->get();

        $requireRegion = (bool) ($this->autoAssign->forServiceCategory('gps_installer')['require_region'] ?? true);

        return app(PartnerProfileService::class)->onlyReadyForJobs(
            $this->coverage->filterAvailable($all, $region, $requireRegion)
        );
    }

    public function assign(LoanApplication $application, Vendor $installer, User $actor, ?string $notes = null): VendorTask
    {
        if ($installer->category !== 'gps_installer' && ! $installer->hasPartnerRole('gps_installer')) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Selected partner is not a GPS installer.',
            ]);
        }

        app(PartnerProfileService::class)->assertCanReceiveJobs($installer);

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

            $slaHours = $this->autoAssign->slaHoursForService('gps_installer');

            $task = VendorTask::create([
                'vendor_id'           => $installer->id,
                'loan_id'             => $application->loan_id,
                'loan_application_id' => $application->id,
                'task_type'           => 'gps_install',
                'status'              => 'assigned',
                'due_at'              => now()->addHours($slaHours),
                'customer_name'       => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
                'customer_phone'      => $customer->phone ?? null,
                'vehicle_details'     => $asset?->title,
                'location'            => $location ?: null,
                'notes'               => $notes,
            ]);

            app(PartnerAssignmentNotifier::class)->notifyAssigned($installer, 'GPS installation', [
                'title' => 'New GPS install task',
                'body' => 'GPS installation assigned for application '.($application->application_number ?? '#'.$application->id).'. Complete by '.$task->due_at?->format('d M Y H:i').'.',
                'action_url' => '/partner/tasks',
                'staff_permission' => 'applications.view',
                'staff_url' => route('admin.loan-applications.show', $application),
            ]);

            return $task;
        });
    }

    public function autoAssignIfPossible(LoanApplication $application, ?User $actor = null): ?VendorTask
    {
        if (! $this->autoAssign->enabledForService('gps_installer')) {
            return null;
        }

        $open = VendorTask::query()
            ->where('loan_application_id', $application->id)
            ->where('task_type', 'gps_install')
            ->whereIn('status', ['assigned', 'in_progress'])
            ->exists();

        if ($open) {
            return null;
        }

        $installer = $this->suggestInstaller($application);
        if (! $installer || ! $actor) {
            $actor = User::query()->whereIn('role', ['admin', 'super_admin'])->orderBy('id')->first();
        }
        if (! $installer || ! $actor) {
            return null;
        }

        try {
            return $this->assign($application, $installer, $actor, 'Auto-assigned GPS installer.');
        } catch (\Throwable) {
            return null;
        }
    }
}
