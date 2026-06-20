<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ArrearCase;
use App\Services\LoanCollectionActionService;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\AuditService;
use App\Services\CapitalPartnerAllocationService;
use App\Services\CapitalPartnerMetricsService;
use App\Services\ActiveLoanServicingService;
use App\Services\AssetReservationService;
use App\Services\LoanDisbursementOrchestrator;
use App\Services\LoanDisbursementService;
use App\Services\GuarantorNotificationService;
use App\Services\LoanOriginationService;
use App\Services\RepaymentScheduleGenerator;
use App\Services\LoanWriteOffService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanController extends Controller
{
    use AuditsActions;

    protected function auditResourceKey(): string
    {
        return 'loans';
    }

    public function create(): View
    {
        $customers = Customer::orderBy('first_name')->limit(500)->get()
            ->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name).($c->customer_number ? ' ('.$c->customer_number.')' : '')]);

        $products = LoanProduct::where('is_active', true)->orderBy('name')->get()->map(fn (LoanProduct $p) => [
            'id'                 => $p->id,
            'code'               => $p->code,
            'name'               => $p->name,
            'rate'               => (float) $p->interest_rate,
            'min'                => (float) $p->min_amount,
            'max'                => (float) $p->max_amount,
            'tmin'               => (int) $p->tenure_min_months,
            'tmax'               => (int) $p->tenure_max_months,
            'desc'               => $p->description,
            'requires_guarantor' => (bool) $p->requires_guarantor,
        ])->values();

        return view('admin.loans.create', [
            'customers'     => $customers,
            'products'      => $products,
            'wizardDataUrl' => route('admin.loans.wizard-data', ['customer' => '__ID__']),
        ]);
    }

    public function wizardCustomerData(Customer $customer, SmartLoanApplicationWizardService $wizard): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('loans.view'), 403);

        $applications = LoanApplication::query()
            ->with('product')
            ->where('customer_id', $customer->id)
            ->where('current_stage', 'disbursement')
            ->whereDoesntHave('loan')
            ->latest()
            ->get()
            ->map(fn (LoanApplication $a) => [
                'id'                 => $a->id,
                'application_number' => $a->application_number,
                'product_id'         => $a->loan_product_id,
                'product_name'       => $a->product?->name,
                'product_code'       => $a->product?->code,
                'amount'             => (float) ($a->recommended_amount ?: $a->requested_amount),
                'tenure'             => (int) $a->requested_tenure_months,
                'rate'               => (float) ($a->product?->interest_rate ?? 0),
            ])
            ->values();

        return response()->json([
            'eligibility'  => $wizard->eligibilityForCustomer($customer),
            'profile'      => $wizard->profileSections($customer),
            'applications' => $applications,
        ]);
    }

    public function store(Request $request, LoanOriginationService $origination): RedirectResponse
    {
        if ($request->filled('loan_application_id')) {
            $application = LoanApplication::query()->findOrFail($request->input('loan_application_id'));

            abort_unless(
                (int) $application->customer_id === (int) $request->input('customer_id'),
                422,
                'Application does not belong to the selected customer.'
            );

            try {
                $loan = $origination->createFromApplication($application);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            $this->auditAdminCreated($loan);

            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('status', 'Loan '.$loan->loan_number.' created from application. Disburse when ready.');
        }

        $data = $this->validated($request);

        $data['loan_number'] = $data['loan_number'] ?? $this->generateLoanNumber(
            LoanProduct::find($data['loan_product_id'] ?? null)
        );
        $data['approved_amount']     = $data['approved_amount'] ?? $data['principal_amount'];
        $data['outstanding_balance'] = $data['outstanding_balance'] ?? $data['principal_amount'];
        $data['status']              = $data['status'] ?? 'pending';

        $loan = Loan::create($data);
        $loan->load('product');
        $this->auditAdminCreated($loan);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Pending loan '.$loan->loan_number.' created. Capital is allocated at disbursement.');
    }

    public function show(Loan $loan)
    {
        $loan->load([
            'customer',
            'product',
            'application',
            'fees',
            'disbursements',
            'capitalAllocations.lender',
            'capitalAllocations.pool',
            'repaymentSchedules' => fn ($q) => $q->orderBy('installment_no'),
        ]);

        $metrics = app(CapitalPartnerMetricsService::class);
        $capitalTotals = $metrics->loanTotals($loan);
        $capitalAllocations = $metrics->allocationsForLoan($loan);
        $disbursementReadiness = $loan->application
            ? app(\App\Services\ApplicationDisbursementReadinessService::class)
            : null;
        $canDisburse = $loan->status === 'pending'
            && (! $loan->application || ($disbursementReadiness?->canMarkDisbursement($loan->application) ?? true));
        $disbursementBlocking = $loan->application
            ? ($disbursementReadiness?->blockingMessages($loan->application) ?? [])
            : [];
        $disbursementChecklist = $loan->application
            ? ($disbursementReadiness?->disbursementChecklist($loan->application) ?? [])
            : [];
        $disbursementDestination = $loan->application
            ? app(\App\Services\CustomerDisbursementDetailsService::class)->snapshotForApplication($loan->application)
            : [];
        $disbursementDetailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
        $orchestrator = app(LoanDisbursementOrchestrator::class);
        $canReverseDisbursement = $orchestrator->canReverseDisbursement($loan);
        $reverseBlocking = $orchestrator->reverseBlockingMessages($loan);
        $capitalService = app(CapitalPartnerAllocationService::class);
        $needsManualCapitalAllocation = $capitalService->needsManualAllocation($loan);
        $capitalPartnerOptions = $needsManualCapitalAllocation
            ? $capitalService->partnerOptionsForLoan($loan)
            : collect();

        $servicing = null;
        $arrearCase = null;
        $collectionActions = collect();
        $recentRepayments = collect();
        $restructureRequests = collect();
        $topUpRequests = collect();

        if (in_array($loan->status, ['active', 'arrears', 'defaulted', 'closed'], true)) {
            $servicing = app(ActiveLoanServicingService::class)->forLoan($loan);
            $arrearCase = ArrearCase::query()
                ->where('loan_id', $loan->id)
                ->where('status', 'open')
                ->with(['actions' => fn ($q) => $q->with('performer')->latest('performed_at')->limit(10)])
                ->latest('id')
                ->first();
            $collectionActions = $arrearCase?->actions ?? collect();
            $recentRepayments = $loan->repayments()->latest('paid_at')->limit(8)->get();
            $restructureRequests = $loan->restructureRequests()->latest()->limit(5)->get();
            $topUpRequests = $loan->topUpRequests()->latest()->limit(5)->get();
        }

        return view('admin.loans.show', compact(
            'loan',
            'capitalTotals',
            'capitalAllocations',
            'disbursementReadiness',
            'canDisburse',
            'disbursementBlocking',
            'disbursementChecklist',
            'disbursementDestination',
            'disbursementDetailsService',
            'canReverseDisbursement',
            'reverseBlocking',
            'servicing',
            'arrearCase',
            'collectionActions',
            'recentRepayments',
            'restructureRequests',
            'topUpRequests',
            'needsManualCapitalAllocation',
            'capitalPartnerOptions',
        ));
    }

    public function edit(Loan $loan)
    {
        abort_if($loan->isServicingLocked(), 403, 'Active loans cannot be edited. Use restructure, top-up, or write-off workflows.');

        return view('admin.loans.edit', ['loan' => $loan] + $this->formData());
    }

    public function update(Request $request, Loan $loan)
    {
        abort_if($loan->isServicingLocked(), 403, 'Active loans cannot be edited. Use restructure, top-up, or write-off workflows.');

        $before = app(AuditService::class)->snapshot($loan);
        $loan->update($this->validated($request));
        $loan->refresh();
        $this->auditAdminUpdated($loan, $before);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan updated.');
    }

    public function destroy(Loan $loan)
    {
        if ($loan->status === 'pending' && $loan->capitalAllocations()->exists()) {
            app(CapitalPartnerAllocationService::class)->releaseAllocationForLoan($loan);
        }

        $this->auditAdminDeleted($loan);
        $loan->delete();

        return redirect()
            ->route('admin.loans.index')
            ->with('status', 'Loan deleted.');
    }

    public function disburse(Loan $loan, LoanDisbursementOrchestrator $orchestrator)
    {
        try {
            $loan = $orchestrator->disburse($loan, auth('admin')->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $installments = $loan->repaymentSchedules()->count();
        $feesCount = \App\Models\LoanFee::where('loan_id', $loan->id)->where('charge_when', 'disbursement')->count();

        $this->auditAdmin('admin.loans.disbursed', $loan, [
            'fees_applied' => $feesCount,
            'installments' => $installments,
        ]);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan disbursed. '.$feesCount.' fee(s) applied · '.$installments.' installment(s) scheduled.');
    }

    public function allocateCapital(Request $request, Loan $loan, CapitalPartnerAllocationService $capital): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->hasPermission('loans.disburse') || auth('admin')->user()?->hasPermission('applications.disburse'), 403);

        $data = $request->validate([
            'allocations'             => ['required', 'array', 'min:1'],
            'allocations.*.lender_id' => ['required', 'integer', 'exists:lenders,id'],
            'allocations.*.amount'    => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $capital->allocateManually($loan, $data['allocations']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->auditAdmin('admin.loans.capital_allocated', $loan, [
            'partners' => count(array_filter($data['allocations'], fn ($row) => (float) ($row['amount'] ?? 0) > 0)),
        ]);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Capital partners assigned. You can disburse when other prerequisites are met.');
    }

    public function clearCapitalAllocation(Loan $loan, CapitalPartnerAllocationService $capital): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->hasPermission('loans.disburse') || auth('admin')->user()?->hasPermission('applications.disburse'), 403);
        abort_unless($loan->status === 'pending', 422, 'Only pending loans can have allocations cleared.');

        if (! $loan->capitalAllocations()->exists()) {
            return back()->with('error', 'This loan has no capital allocations to clear.');
        }

        $capital->releaseAllocationForLoan($loan);

        $this->auditAdmin('admin.loans.capital_allocation_cleared', $loan);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Capital allocation cleared. Re-assign partners before disbursement.');
    }

    public function reverseDisbursement(Request $request, Loan $loan, LoanDisbursementOrchestrator $orchestrator)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $loan = $orchestrator->reverseDisbursement($loan, auth()->user(), $data['reason']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->auditAdmin('admin.loans.disbursement_reversed', $loan, [
            'reason' => $data['reason'],
        ]);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Disbursement reversed. Loan returned to pending — ready for disbursement queue.');
    }

    public function addCollectionAction(Request $request, Loan $loan, LoanCollectionActionService $service): RedirectResponse
    {
        abort_unless(in_array($loan->status, ['active', 'arrears', 'defaulted'], true), 422);
        $this->authorize('viewAny', ArrearCase::class);

        $data = $request->validate([
            'action_type' => ['required', 'string', 'max:100'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'result'      => ['nullable', 'string', 'max:100'],
        ]);

        $service->logForLoan(
            $loan,
            $request->user(),
            $data['action_type'],
            $data['notes'] ?? null,
            $data['result'] ?? null,
        );

        $this->auditAdmin('admin.loans.collection_action', $loan, [
            'action_type' => $data['action_type'],
        ]);

        return back()->with('status', 'Collection action logged.');
    }

    public function writeOffForm(Loan $loan)
    {
        $approvalRequired = (bool) \App\Models\Setting::get('finance.write_off_approval_required');

        return view('admin.loans.write-off', compact('loan', 'approvalRequired'));
    }

    public function writeOff(Request $request, Loan $loan, LoanWriteOffService $service)
    {
        if ((bool) \App\Models\Setting::get('finance.write_off_approval_required')) {
            return back()->withErrors([
                'reason' => 'Write-off requires manager and finance approval. Use the collections workflow to recommend a write-off.',
            ]);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $entry = $service->writeOff($loan, $data['reason'], $data['amount'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $this->auditAdmin('admin.loans.written_off', $loan->fresh(), [
            'reason' => $data['reason'],
            'amount' => $data['amount'] ?? null,
            'journal' => $entry?->entry_number,
        ]);

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan written off.'.($entry ? ' Journal '.$entry->entry_number.' posted.' : ''));
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'customers'    => Customer::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'customer_number']),
            'products'     => LoanProduct::orderBy('name')->get(['id', 'name', 'code']),
            'applications' => LoanApplication::orderByDesc('id')->limit(200)->get(['id', 'application_number']),
            'statuses'     => ['pending', 'active', 'closed', 'defaulted', 'written_off'],
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'customer_id'           => ['required', 'exists:customers,id'],
            'loan_product_id'       => ['required', 'exists:loan_products,id'],
            'loan_application_id'   => ['nullable', 'exists:loan_applications,id'],
            'loan_number'           => ['nullable', 'string', 'max:64'],
            'principal_amount'      => ['required', 'numeric', 'min:0'],
            'approved_amount'       => ['nullable', 'numeric', 'min:0'],
            'outstanding_balance'   => ['nullable', 'numeric', 'min:0'],
            'interest_rate'         => ['required', 'numeric', 'min:0', 'max:1'],
            'tenure_months'         => ['required', 'integer', 'min:1', 'max:120'],
            'status'                => ['required', 'in:pending,active,closed,defaulted,written_off'],
            'disbursement_date'     => ['nullable', 'date'],
            'maturity_date'         => ['nullable', 'date'],
            'next_due_date'         => ['nullable', 'date'],
        ]);
    }

    protected function generateLoanNumber(?LoanProduct $product = null): string
    {
        if ($product) {
            return app(\App\Services\ReferenceNumberService::class)->loanReference($product);
        }

        return 'LN-IL-'.str_pad((string) ((int) Loan::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
