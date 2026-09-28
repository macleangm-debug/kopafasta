<?php

namespace App\Console\Commands;

use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\CapacityAutoRejectService;
use Illuminate\Console\Command;

/**
 * Staging-only: make APP-UAT-UZ16 due and fire the existing scheduled auto-reject path.
 * Does not change the global 12-hour setting. Does not touch APP-IL-UZ16.
 */
class StagingUz16FireAutoRejectCommand extends Command
{
    protected $signature = 'staging:uz16-fire-auto-reject';

    protected $description = 'Make the staging UZ16 park due and fire CapacityAutoRejectService::fireDue (scheduled path).';

    public function handle(CapacityAutoRejectService $capacity): int
    {
        if (! app()->environment(['staging', 'local', 'testing'])) {
            $this->error('Refusing: only staging/local/testing.');

            return self::FAILURE;
        }

        $application = LoanApplication::query()
            ->where('application_number', StagingUz16ParkUatCommand::APPLICATION_NUMBER)
            ->with(['customer.user', 'product'])
            ->first();
        if (! $application) {
            $this->error(StagingUz16ParkUatCommand::APPLICATION_NUMBER.' not found.');

            return self::FAILURE;
        }

        $delay = Setting::get('underwriting.capacity_auto_reject_delay_hours');
        $this->info('Global delay hours unchanged: '.($delay ?? '12'));

        if ((string) $application->status === 'rejected') {
            $this->printReport($application->fresh(['customer.user']));

            return self::SUCCESS;
        }

        if (! $capacity->isPending($application)) {
            $this->error('Application is not a pending capacity park.');

            return self::FAILURE;
        }

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $state = is_array($payload['capacity_auto_reject'] ?? null) ? $payload['capacity_auto_reject'] : [];
        $state['auto_reject_at'] = now()->subMinute()->toIso8601String();
        $state['due_made_at'] = now()->toIso8601String();
        $payload['capacity_auto_reject'] = $state;
        $application->forceFill(['screening_payload' => $payload])->save();

        $fired = $capacity->fireDue();
        $match = $fired->first(fn (LoanApplication $row) => (int) $row->id === (int) $application->id);
        if (! $match) {
            $this->error('fireDue did not reject '.StagingUz16ParkUatCommand::APPLICATION_NUMBER);

            return self::FAILURE;
        }

        $this->printReport($match->fresh(['customer.user']));

        return self::SUCCESS;
    }

    private function printReport(LoanApplication $application): void
    {
        $letter = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'rejection_letter')
            ->latest('id')
            ->first();
        $notice = NotificationLog::query()
            ->where('customer_id', $application->customer_id)
            ->where('template', 'application_rejected')
            ->latest('id')
            ->first();

        $this->table(
            ['Field', 'Value'],
            [
                ['Staging application', $application->application_number],
                ['Status/stage', $application->status.'/'.$application->current_stage],
                ['Production APP-IL-UZ16', 'untouched'],
                ['Notification', $notice?->id ? 'YES' : 'NO'],
                ['Notification template', $notice?->template ?? ''],
                ['Document type', $letter?->document_type ?? 'none'],
                ['Document generated', $letter ? 'YES' : 'NO'],
                ['Borrower letter URL', $letter
                    ? route('site.borrower.application.rejection-letter', $application->id)
                    : route('site.borrower.application', $application->id)],
            ]
        );
    }
}
