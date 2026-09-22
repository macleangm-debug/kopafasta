<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\CapacityAutoRejectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Staging/local only. Exercises the real CapacityAutoRejectService timer/fire path.
 */
class StagingCapacityAutoRejectUatController extends Controller
{
    public const SIM_APPLICATION_NUMBER = 'APP-UAT-CAPACITY-SIM';

    public const NATURAL_SCHEDULE_NUMBER = 'APP-UAT-G1-260916072731';

    public function __construct(
        private readonly CapacityAutoRejectService $capacity,
    ) {}

    public function scenario(): RedirectResponse
    {
        $this->guardStaging();

        $application = $this->ensureScenarioApplication();

        return redirect()
            ->route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ])
            ->with('status', 'Staging capacity auto-reject SIM scenario ready (Pending).');
    }

    public function makeDue(LoanApplication $loan_application): RedirectResponse
    {
        $this->guardStaging();
        $this->guardNotNaturalSchedule($loan_application);

        if (! $this->capacity->isPending($loan_application)) {
            $this->capacity->evaluateAndPark($loan_application->fresh(['customer', 'product']));
            $loan_application = $loan_application->fresh();
        }

        if (! $this->capacity->advanceTimerDue($loan_application->fresh())) {
            return back()->withErrors(['uat' => 'Could not advance timer — application is not pending automatic rejection.']);
        }

        return back()->with('status', 'Timer advanced to due. Next: Fire scheduled path.');
    }

    public function fireDue(LoanApplication $loan_application): RedirectResponse
    {
        $this->guardStaging();
        $this->guardNotNaturalSchedule($loan_application);

        if (! $this->capacity->isPending($loan_application)) {
            return back()->withErrors(['uat' => 'Application is not pending automatic rejection.']);
        }

        $this->capacity->advanceTimerDue($loan_application->fresh());

        try {
            $this->capacity->fireScheduledPath($loan_application->fresh(['customer', 'product']));
        } catch (\Throwable $e) {
            return back()->withErrors(['uat' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.loan-applications.show', [
                'loan_application' => $loan_application->fresh(),
                'workspace' => 'overview',
            ])
            ->with('status', 'Scheduled fire path completed (Rejected via CapacityAutoRejectService).');
    }

    public function reset(LoanApplication $loan_application): RedirectResponse
    {
        $this->guardStaging();
        $this->guardNotNaturalSchedule($loan_application);

        $state = $this->capacity->resetPendingPark($loan_application->fresh(['customer', 'product']));
        if (($state['status'] ?? null) !== CapacityAutoRejectService::STATUS_PENDING) {
            return back()->withErrors(['uat' => 'Reset ran but affordability no longer parks. Adjust income/amount so capacity fails.']);
        }

        return redirect()
            ->route('admin.loan-applications.show', [
                'loan_application' => $loan_application->fresh(),
                'workspace' => 'overview',
            ])
            ->with('status', 'SIM scenario reset to Pending automatic rejection.');
    }

    private function guardStaging(): void
    {
        abort_unless(app()->environment(['local', 'staging', 'testing']), 404);
        abort_unless(auth('admin')->check(), 403);
        $user = auth('admin')->user();
        abort_unless(
            $user && (
                $user->hasPermission('applications.review')
                || in_array((string) $user->role, ['admin', 'super_admin', 'credit_committee'], true)
            ),
            403
        );
    }

    private function guardNotNaturalSchedule(LoanApplication $application): void
    {
        abort_if(
            $application->application_number === self::NATURAL_SCHEDULE_NUMBER,
            403,
            'Natural schedule UAT app is protected. Use the SIM scenario.'
        );
    }

    private function ensureScenarioApplication(): LoanApplication
    {
        Setting::set('underwriting.enable_automatic_rejection', true);
        Setting::set('underwriting.enable_capacity_auto_reject', true);

        $existing = LoanApplication::query()
            ->where('application_number', self::SIM_APPLICATION_NUMBER)
            ->first();

        if ($existing) {
            if ($this->capacity->isPending($existing)) {
                return $existing;
            }
            $this->capacity->resetPendingPark($existing->fresh(['customer', 'product']));

            return $existing->fresh(['customer', 'product']);
        }

        $customer = Customer::query()->where('customer_number', 'CU-UAT-CAPACITY-SIM')->first();
        if (! $customer) {
            $user = \App\Models\User::query()->firstOrCreate(
                ['email' => 'uat.capacity.sim@staging.kopafasta.com'],
                [
                    'name' => 'Capacity Sim Borrower',
                    'phone' => '255799000991',
                    'password' => Hash::make('StagingUat!2026'),
                    'role' => 'customer',
                    'is_active' => true,
                ]
            );
            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_number' => 'CU-UAT-CAPACITY-SIM',
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Capacity',
                'last_name' => 'SimBorrower',
                'phone' => '255799000991',
                'monthly_income' => 100_000,
            ]);
        }

        $product = LoanProduct::query()->where('is_active', true)->orderBy('id')->first()
            ?? LoanProduct::create([
                'code' => 'IL-CAP-SIM',
                'name' => 'Capacity Sim',
                'is_active' => true,
                'interest_rate' => 0.05,
                'min_amount' => 100_000,
                'max_amount' => 5_000_000,
                'tenure_min_months' => 1,
                'tenure_max_months' => 12,
            ]);

        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => self::SIM_APPLICATION_NUMBER,
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
            'screening_payload' => [
                'uat_tag' => 'capacity_auto_reject_sim',
            ],
        ]);

        $state = $this->capacity->evaluateAndPark($application->fresh(['customer', 'product']));
        if (($state['status'] ?? null) !== CapacityAutoRejectService::STATUS_PENDING) {
            // Force a parked state when live affordability does not fail (still uses real park payload shape).
            $payload = $application->fresh()->screening_payload ?? [];
            $payload['capacity_auto_reject'] = [
                'status' => CapacityAutoRejectService::STATUS_PENDING,
                'gate' => 'declared',
                'reason_code' => CapacityAutoRejectService::REASON_CODE,
                'parked_at' => now()->toIso8601String(),
                'auto_reject_at' => now()->addHours(12)->toIso8601String(),
                'requested_amount' => (float) $application->requested_amount,
                'proposed_installment' => 450_000,
                'available_capacity' => 33_000,
                'repayment_ratio_pct' => app(\App\Services\AffordabilityPolicyService::class)->repaymentRatioPct(),
                'is_group' => false,
                'uat_forced_park' => true,
            ];
            $application->update(['screening_payload' => $payload]);
        }

        return $application->fresh(['customer', 'product']);
    }
}
