<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\DocumentTemplate;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanAgreementService
{
    /**
     * Generate (or regenerate) an offer letter PDF for a loan application.
     */
    public function generateOfferLetter(LoanApplication $application, bool $regenerate = false): LoanAgreement
    {
        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->first();

        if ($existing && ! $regenerate && $existing->isSigned()) {
            return $existing;
        }

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors']);

        $snapshot = $this->snapshotFromApplication($application);

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id'         => $application->customer_id,
            'document_type'       => 'offer_letter',
            'reference'           => 'OL-'.strtoupper(Str::random(8)),
        ]);

        $wasSigned = $existing && $existing->isSigned();

        $fill = [
            'snapshot'             => $snapshot,
            'expires_at'           => now()->addDays(app(LegalSettingsService::class)->offerValidityDays()),
            'generated_by_user_id' => Auth::id(),
        ];

        if (! $wasSigned) {
            $fill['status'] = 'sent';
            $fill['sent_at'] = now();
        }

        $agreement->fill($fill);

        if ($regenerate) {
            $this->resetDeclinedOfferState($application);
        }

        // Render PDF
        $viewData = [
            'application' => $application,
            'snapshot'    => $snapshot,
            'agreement'   => $agreement,
        ];
        $pdf = $this->renderAgreementPdf(
            $application->product?->offerLetterTemplate,
            'pdf.offer-letter',
            $viewData,
        );

        $path = "agreements/{$agreement->reference}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $agreement->file_path = $path;
        $agreement->save();

        return $agreement;
    }

    /**
     * Generate a loan contract PDF after offer acceptance.
     */
    public function generateLoanContract(LoanApplication $application, bool $regenerate = false): LoanAgreement
    {
        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'loan_contract')
            ->first();

        if ($existing && ! $regenerate && $existing->isSigned()) {
            return $existing;
        }

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors']);
        $snapshot = $this->snapshotFromApplication($application);

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id'         => $application->customer_id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-'.strtoupper(Str::random(8)),
        ]);

        $wasSigned = $existing && $existing->isSigned();

        $fill = [
            'snapshot'             => $snapshot,
            'generated_by_user_id' => Auth::id(),
        ];

        if (! $wasSigned) {
            $fill['status'] = 'sent';
            $fill['sent_at'] = now();
        }

        $agreement->fill($fill);

        $viewData = [
            'application' => $application,
            'snapshot'    => $snapshot,
            'agreement'   => $agreement,
        ];
        $template = ($application->product?->code ?? '') === config('asset_marketplace.asset_loan_product_code', 'AL')
            ? ($application->product?->assetLendingAgreementTemplate ?? $application->product?->loanContractTemplate)
            : $application->product?->loanContractTemplate;

        $pdf = $this->renderAgreementPdf($template, 'pdf.loan-contract', $viewData);

        $path = "agreements/{$agreement->reference}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $agreement->file_path = $path;
        $agreement->save();

        return $agreement;
    }

    /** Re-render agreement PDFs after guarantor signature without clearing borrower signatures. */
    public function refreshGuarantorOnDocuments(LoanApplication $application): void
    {
        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors']);
        $snapshot = $this->snapshotFromApplication($application);

        foreach (['offer_letter', 'loan_contract'] as $documentType) {
            $agreement = LoanAgreement::query()
                ->where('loan_application_id', $application->id)
                ->where('document_type', $documentType)
                ->latest('id')
                ->first();

            if (! $agreement) {
                continue;
            }

            $wasSigned = $agreement->isSigned();
            $agreement->snapshot = $snapshot;

            $viewData = [
                'application' => $application,
                'snapshot'    => $snapshot,
                'agreement'   => $agreement,
            ];

            $template = $documentType === 'offer_letter'
                ? $application->product?->offerLetterTemplate
                : (($application->product?->code ?? '') === config('asset_marketplace.asset_loan_product_code', 'AL')
                    ? ($application->product?->assetLendingAgreementTemplate ?? $application->product?->loanContractTemplate)
                    : $application->product?->loanContractTemplate);

            $fallbackView = $documentType === 'offer_letter' ? 'pdf.offer-letter' : 'pdf.loan-contract';
            $pdf = $this->renderAgreementPdf($template, $fallbackView, $viewData);

            $path = $agreement->file_path ?: "agreements/{$agreement->reference}.pdf";
            Storage::disk('public')->put($path, $pdf->output());
            $agreement->file_path = $path;

            if (! $wasSigned) {
                $agreement->status = 'sent';
            }

            $agreement->save();
        }
    }

    /**
     * Issue a fresh OTP for signing. Returns the agreement after persisting code + expiry.
     */
    public function issueSigningOtp(LoanAgreement $agreement): string
    {
        if ($agreement->document_type === 'offer_letter' && $agreement->isOfferExpired()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp' => 'This offer has expired. Please contact the lender for a new offer letter.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $agreement->update([
            'otp_code'        => $code,
            'otp_sent_at'     => now(),
            'otp_expires_at'  => now()->addMinutes(10),
            'otp_attempts'    => 0,
        ]);
        return $code;
    }

    /**
     * Verify OTP and mark the agreement as signed. Returns [ok, message].
     *
     * @return array{0:bool,1:string}
     */
    public function signWithOtp(LoanAgreement $agreement, string $code, ?string $ip = null, ?string $ua = null): array
    {
        if ($agreement->isSigned()) {
            return [true, 'Already signed.'];
        }

        if ($agreement->isCancelled()) {
            return [false, __('borrower.agreement.already_declined')];
        }

        if ($agreement->document_type === 'offer_letter' && $agreement->isOfferExpired()) {
            return [false, 'This offer has expired. Please contact the lender for a new offer letter.'];
        }

        $application = $agreement->loanApplication;
        if ($agreement->document_type === 'offer_letter' && $application && ! $this->borrowerCanRespondToOffer($application, $agreement)) {
            return [false, __('borrower.agreement.already_declined')];
        }

        if (! $agreement->otp_code || ! $agreement->otp_expires_at) {
            return [false, 'No OTP issued. Please request a new code.'];
        }
        if (now()->greaterThan($agreement->otp_expires_at)) {
            return [false, 'OTP has expired. Please request a new code.'];
        }
        if ($agreement->otp_attempts >= 5) {
            return [false, 'Too many attempts. Request a new code.'];
        }
        if (! hash_equals((string) $agreement->otp_code, trim($code))) {
            $agreement->increment('otp_attempts');

            return [false, 'Incorrect code.'];
        }

        $this->markSigned($agreement, 'otp', $ip, $ua);

        return [true, 'Signed successfully.'];
    }

    /**
     * Accept without OTP when acceptance codes are disabled in settings.
     *
     * @return array{0:bool,1:string}
     */
    public function acceptDirectly(LoanAgreement $agreement, ?string $ip = null, ?string $ua = null): array
    {
        if ($agreement->isSigned()) {
            return [true, 'Already signed.'];
        }

        if ($agreement->isCancelled()) {
            return [false, __('borrower.agreement.already_declined')];
        }

        if ($agreement->document_type === 'offer_letter' && $agreement->isOfferExpired()) {
            return [false, 'This offer has expired. Please contact the lender for a new offer letter.'];
        }

        $application = $agreement->loanApplication;
        if ($agreement->document_type === 'offer_letter' && $application && ! $this->borrowerCanRespondToOffer($application, $agreement)) {
            return [false, __('borrower.agreement.already_declined')];
        }

        $this->markSigned($agreement, 'direct', $ip, $ua);

        return [true, __('borrower.agreement.accepted_success')];
    }

    public function borrowerCanRespondToOffer(LoanApplication $application, ?LoanAgreement $agreement): bool
    {
        if (! $agreement?->isRespondable()) {
            return false;
        }

        if ($application->offer_status === 'declined') {
            return false;
        }

        return ! in_array((string) $application->status, ['withdrawn', 'rejected'], true);
    }

    public function borrowerOfferDeclined(LoanApplication $application, ?LoanAgreement $agreement): bool
    {
        return $application->offer_status === 'declined'
            || ($agreement?->isCancelled() ?? false)
            || (string) $application->status === 'withdrawn';
    }

    /**
     * Generate repayment schedule annex PDF after disbursement.
     */
    public function generateRepaymentScheduleAnnex(Loan $loan, bool $regenerate = false): ?LoanAgreement
    {
        $application = $loan->application;
        if (! $application) {
            return null;
        }

        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'repayment_schedule')
            ->first();

        if ($existing && ! $regenerate && $existing->file_path) {
            return $existing;
        }

        $loan->loadMissing(['product', 'repaymentSchedules']);
        $schedules = $loan->repaymentSchedules()->orderBy('installment_no')->get();
        if ($schedules->isEmpty()) {
            return null;
        }

        $application->loadMissing(['customer', 'product']);
        $snapshot = $this->snapshotFromApplication($application);
        $snapshot['disbursement_date'] = $loan->disbursement_date?->toDateString();
        $snapshot['first_due_date'] = $schedules->first()?->due_date?->toDateString();
        $snapshot['last_due_date'] = $schedules->last()?->due_date?->toDateString();
        $snapshot['repayment_schedule'] = $schedules->map(fn ($row) => [
            'installment_no' => $row->installment_no,
            'label'          => ($loan->product->repayment_cadence ?? 'weekly') === 'monthly'
                ? 'Month '.$row->installment_no
                : 'Week '.$row->installment_no,
            'due_date'       => $row->due_date?->toDateString(),
            'principal_due'  => (float) $row->principal_due,
            'interest_due'   => (float) $row->interest_due,
            'total_due'      => (float) $row->total_due,
        ])->all();

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id'         => $application->customer_id,
            'document_type'       => 'repayment_schedule',
            'reference'           => 'RS-'.strtoupper(Str::random(8)),
        ]);

        $agreement->fill([
            'snapshot'             => $snapshot,
            'status'               => 'sent',
            'sent_at'              => now(),
            'generated_by_user_id' => Auth::id(),
        ]);

        $viewData = [
            'application' => $application,
            'snapshot'    => $snapshot,
            'agreement'   => $agreement,
            'loan'        => $loan,
        ];

        $pdf = $this->withBorrowerLocale($application, fn () => Pdf::loadView('pdf.repayment-schedule', $viewData)->setPaper('a4'));
        $path = "agreements/{$agreement->reference}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $agreement->file_path = $path;
        $agreement->save();

        return $agreement;
    }

    /**
     * Generate the executed final contract with dated repayment schedule annex after disbursement.
     */
    public function generateFinalLoanContract(Loan $loan, bool $regenerate = false): ?LoanAgreement
    {
        $application = $loan->application;
        if (! $application) {
            return null;
        }

        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'final_loan_contract')
            ->first();

        if ($existing && ! $regenerate && $existing->file_path) {
            return $existing;
        }

        $loan->loadMissing(['product', 'repaymentSchedules']);
        $schedules = $loan->repaymentSchedules()->orderBy('installment_no')->get();
        if ($schedules->isEmpty()) {
            return null;
        }

        $signedContract = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'loan_contract')
            ->where('status', 'signed')
            ->latest('id')
            ->first();

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors']);
        $snapshot = $this->snapshotFromApplication($application);
        $snapshot['disbursement_date'] = $loan->disbursement_date?->toDateString();
        $snapshot['first_due_date'] = $schedules->first()?->due_date?->toDateString();
        $snapshot['last_due_date'] = $schedules->last()?->due_date?->toDateString();
        $snapshot['schedule_is_estimate'] = false;
        $runningBalance = (float) $loan->principal_amount;
        $snapshot['repayment_schedule'] = $schedules->map(function ($row) use ($loan, &$runningBalance) {
            $totalDue = (float) $row->total_due;
            $outstandingAfter = max(0, round($runningBalance - (float) $row->principal_due, 2));
            $entry = [
                'installment_no'      => $row->installment_no,
                'label'               => ($loan->product->repayment_cadence ?? 'weekly') === 'monthly'
                    ? 'Month '.$row->installment_no
                    : 'Week '.$row->installment_no,
                'due_date'            => $row->due_date?->toDateString(),
                'principal_due'       => (float) $row->principal_due,
                'interest_due'        => (float) $row->interest_due,
                'total_due'           => $totalDue,
                'outstanding_balance' => $outstandingAfter,
            ];
            $runningBalance = $outstandingAfter;

            return $entry;
        })->all();

        if ($signedContract?->acceptance_signature_data) {
            $customer = $application->customer;
            $snapshot['borrower_signature'] = (object) [
                'signature_data' => $signedContract->acceptance_signature_data,
                'signer_name'    => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Borrower',
                'signed_at'      => $signedContract->signed_at,
            ];
        }

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id'         => $application->customer_id,
            'document_type'       => 'final_loan_contract',
            'reference'           => 'FLC-'.strtoupper(Str::random(8)),
        ]);

        $agreement->fill([
            'snapshot'             => $snapshot,
            'status'               => 'signed',
            'sent_at'              => now(),
            'signed_at'            => now(),
            'generated_by_user_id' => Auth::id(),
        ]);

        $viewData = [
            'application'    => $application,
            'snapshot'       => $snapshot,
            'agreement'      => $agreement,
            'loan'           => $loan,
            'signedContract' => $signedContract,
        ];

        $pdf = $this->withBorrowerLocale($application, fn () => Pdf::loadView('pdf.final-loan-contract', $viewData)->setPaper('a4'));
        $path = "agreements/{$agreement->reference}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $agreement->file_path = $path;
        $agreement->save();

        return $agreement;
    }

    /** Cancel an unsigned offer letter when the borrower declines. */
    public function declineOfferLetter(LoanAgreement $agreement): void
    {
        if ($agreement->isSigned()) {
            return;
        }

        $agreement->update(['status' => 'cancelled']);
    }

    private function markSigned(LoanAgreement $agreement, string $method, ?string $ip = null, ?string $ua = null): void
    {
        $agreement->loadMissing('loanApplication.customer', 'loanApplication.signatures');
        $application = $agreement->loanApplication;
        $customer = $application?->customer;
        $signerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: ($customer?->legalDisplayName() ?? 'Borrower');

        $acceptanceSignature = null;
        if ($agreement->document_type === 'loan_contract') {
            $appSignature = app(BorrowerSignatureService::class)->signature($application);
            if ($appSignature) {
                $acceptanceSignature = $appSignature->signature_data;
            }
        }

        if (! filled($acceptanceSignature)) {
            $acceptanceSignature = app(OtpSignatureImageService::class)->generateDataUri($signerName);
        }

        $agreement->update([
            'status'                    => 'signed',
            'signed_at'                 => now(),
            'signed_ip'                 => $ip,
            'signed_user_agent'         => $ua ? substr($ua, 0, 255) : null,
            'signature_method'          => $method,
            'acceptance_signature_data' => $acceptanceSignature,
            'otp_code'                  => null,
        ]);
    }

    /**
     * After the borrower accepts an offer: persist acceptance, generate fees,
     * advance current_stage, and return the refreshed application.
     */
    public function advanceAfterOfferAcceptance(LoanApplication $application): LoanApplication
    {
        $application->loadMissing('product');
        $readiness = app(ApplicationDisbursementReadinessService::class);

        $updates = [
            'offer_status'       => 'accepted',
            'offer_responded_at' => now(),
        ];

        if (in_array((string) $application->status, ['withdrawn', 'awaiting_offer'], true)
            || in_array((string) ($application->current_stage ?? ''), ['approval', 'disbursement', 'post_approval_fees'], true)) {
            $updates['status'] = 'approved';
        }

        if ($application->offered_amount) {
            $updates['recommended_amount'] = $application->offered_amount;
        }

        if ($application->offered_tenure_months) {
            $updates['requested_tenure_months'] = $application->offered_tenure_months;
            $updates['approved_tenure_months'] = $application->offered_tenure_months;
        }

        $application->update($updates);
        $application = $application->fresh(['product']);

        if ($this->shouldGeneratePostApprovalFees($application)) {
            app(PostApprovalFeeService::class)->generateForApplication($application);
            $application = $application->fresh(['product', 'postApprovalFees']);
        }

        return $readiness->syncBorrowerProgress($application->fresh(['product', 'postApprovalFees']));
    }

    /** @deprecated Use advanceAfterOfferAcceptance() */
    public function recordOfferAcceptance(LoanApplication $application): LoanApplication
    {
        return $this->advanceAfterOfferAcceptance($application);
    }

    private function resetDeclinedOfferState(LoanApplication $application): void
    {
        $offer = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        $wasDeclined = $application->offer_status === 'declined'
            || (string) $application->status === 'withdrawn'
            || ($offer?->isCancelled() ?? false);

        if (! $wasDeclined) {
            return;
        }

        $postApproval = in_array((string) ($application->current_stage ?? ''), [
            'approval',
            'disbursement',
            'post_approval_fees',
            'awaiting_disbursement_details',
            'contract_generation',
        ], true);

        $application->update([
            'status'               => $postApproval ? 'approved' : 'awaiting_offer',
            'offer_status'         => 'pending_borrower',
            'offer_responded_at'   => null,
            'offer_decline_reason' => null,
            'offer_issued_at'      => now(),
        ]);
    }

    private function shouldGeneratePostApprovalFees(LoanApplication $application): bool
    {
        return in_array((string) ($application->current_stage ?? ''), ['approval', 'disbursement'], true)
            || in_array((string) $application->status, ['approved', 'pre_approved'], true);
    }

    /** Generate the loan contract once post-approval fees are settled. */
    public function ensureLoanContractAfterFees(LoanApplication $application): ?LoanAgreement
    {
        $readiness = app(ApplicationDisbursementReadinessService::class);

        if (! $readiness->offerSigned($application)) {
            return null;
        }

        if ($readiness->hasPostApprovalFees($application) && ! $readiness->feesPaid($application)) {
            return null;
        }

        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'loan_contract')
            ->first();

        $contract = $this->generateLoanContract($application);

        if ($contract && ! $existing) {
            $application->loadMissing('customer');
            if ($application->customer) {
                app(NotificationService::class)->notifyInApp(
                    $application->customer,
                    __('borrower.contract.notify_message', ['reference' => $application->application_number]),
                    'application',
                    'contract_ready',
                    __('borrower.contract.notify_title'),
                    route('site.borrower.application.contract', $application->id),
                    __('borrower.loan_profile.actions.view_contract'),
                );
            }
        }

        return $contract;
    }

    private function snapshotFromApplication(LoanApplication $a): array
    {
        $amount = app(ApplicationOfferService::class)->effectiveAmount($a);
        $product = $a->product;
        $rateBreakdown = app(DisplayedRateService::class)->breakdown($product, $amount);
        $monthlyRate = $rateBreakdown['displayed_monthly_rate'];
        $tenure = (int) ($a->offered_tenure_months ?? $a->approved_tenure_months ?? $a->requested_tenure_months ?? $product->default_tenure_months ?? 0);
        $cadence = $a->product->repayment_cadence ?? 'weekly';

        $schedule = app(RepaymentScheduleGenerator::class)->previewEstimate($amount, $monthlyRate, $tenure, $cadence);
        $instalment = $schedule[0]['total_due'] ?? 0.0;
        $totalInterest = round(collect($schedule)->sum('interest_due'), 2);
        $totalFees = (float) ($a->processing_fee ?? 0);
        $totalRepayable = round(collect($schedule)->sum('total_due') + $totalFees, 2);
        $offerSettings = app(OfferSettingsService::class);
        $isAssetLoan = ($a->product->code ?? '') === config('asset_marketplace.asset_loan_product_code', 'AL');
        $reservation = AssetReservation::query()
            ->with('asset')
            ->where('loan_application_id', $a->id)
            ->first();

        $legal = app(LegalSettingsService::class);
        $customer = $a->customer;
        $guarantorLink = $a->customerGuarantors()->with('guarantor')->first();
        $guarantor = $guarantorLink?->guarantor;

        $borrowerAddress = collect([
            $customer?->street,
            $customer?->ward,
            $customer?->district,
            $customer?->region,
        ])->filter()->implode(', ');

        if ($borrowerAddress === '' && $customer?->address) {
            $borrowerAddress = (string) $customer->address;
        }

        $activityLabel = display_label($customer?->activity_type, 'activity_type')
            ?: ($customer?->business_name ?? $customer?->employment_type);

        return [
            'application_number'   => $a->application_number,
            'product_name'         => $a->product->name ?? null,
            'product_code'         => $a->product->code ?? null,
            'principal'            => $amount,
            'interest_rate'        => $monthlyRate,
            'bot_regulated_rate'   => $rateBreakdown['bot_regulated_rate'],
            'internal_fee_rate'    => $rateBreakdown['internal_fee_rate'],
            'displayed_monthly_rate' => $monthlyRate,
            'rate_breakdown'       => $rateBreakdown,
            'tenure_months'        => $tenure,
            'repayment_cadence'    => $cadence,
            'installment_count'    => count($schedule),
            'estimated_emi'        => $instalment,
            'installment_label'    => app(RepaymentScheduleGenerator::class)->installmentLabel($cadence),
            'total_interest'       => $totalInterest,
            'total_fees'           => $totalFees,
            'total_repayable'      => $totalRepayable,
            'repayment_schedule'   => $schedule,
            'schedule_is_estimate' => true,
            'repayment_commencement_days' => $offerSettings->repaymentCommencementDays(),
            'customer_name'        => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            'customer_id'          => $customer->national_id ?? null,
            'customer_phone'       => $customer->phone ?? null,
            'customer_address'     => $borrowerAddress ?: null,
            'customer_activity'    => $activityLabel,
            'customer_income'      => $customer->monthly_income
                ? format_money((float) $customer->monthly_income)
                : (income_range_label($customer->income_range ?? '') ?: $customer->income_range),
            'guarantor_name'       => $guarantor ? trim(($guarantor->first_name ?? '').' '.($guarantor->last_name ?? '')) : null,
            'guarantor_nida'       => $guarantor?->national_id,
            'guarantor_address'    => $guarantor?->address,
            'guarantor_phone'      => $guarantor?->phone,
            'guarantor_relationship' => $guarantor?->relationship,
            'purpose'              => $a->purpose ?? null,
            'offer_expires_at'     => ($existing = LoanAgreement::query()
                ->where('loan_application_id', $a->id)
                ->where('document_type', 'offer_letter')
                ->latest('id')
                ->first())?->expires_at?->toDateString()
                ?? now()->addDays($legal->offerValidityDays())->toDateString(),
            'offer_validity_days'  => $legal->offerValidityDays(),
            'legal_clauses'        => $legal->contractClauses(),
            'contract_sections'    => $legal->contractSections(),
            'generated_at'         => now()->toIso8601String(),
            'borrower_signature'   => $this->borrowerSignatureForPdf($a, 'loan_contract'),
            'guarantor_signature'  => $a->signatures->firstWhere('signer_type', 'guarantor'),
            'company_signatory'    => brand('legal_name'),
            ...$this->companySignatorySnapshot($legal),
            'is_asset_loan'        => $isAssetLoan,
            'asset_title'          => $reservation?->asset?->title,
            'asset_ownership_note' => $isAssetLoan ? config('asset_marketplace.ownership_note') : null,
        ];
    }

    /** @param 'offer_letter'|'loan_contract' $documentType */
    private function borrowerSignatureForPdf(LoanApplication $application, string $documentType): ?object
    {
        $agreement = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', $documentType)
            ->latest('id')
            ->first();

        if ($agreement?->isSigned() && filled($agreement->acceptance_signature_data)) {
            $customer = $application->customer;

            return (object) [
                'signature_data' => $agreement->acceptance_signature_data,
                'signer_name'    => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Borrower',
                'signed_at'      => $agreement->signed_at,
            ];
        }

        return $application->signatures->firstWhere('signer_type', 'borrower');
    }

    /** @param array<string, mixed> $data */
    private function renderAgreementPdf(?DocumentTemplate $template, string $fallbackView, array $data): \Barryvdh\DomPDF\PDF
    {
        $application = $data['application'] ?? null;

        return $this->withBorrowerLocale($application, function () use ($template, $fallbackView, $data) {
            if ($template && filled($template->content)) {
                $html = Blade::render($template->content, $data);

                return Pdf::loadHTML($html)->setPaper('a4');
            }

            return Pdf::loadView($fallbackView, $data)->setPaper('a4');
        });
    }

    /** @return array<string, mixed> */
    private function companySignatorySnapshot(LegalSettingsService $legal): array
    {
        $signatory = $legal->activeSignatory();

        if ($signatory) {
            return [
                'company_signatory_name'  => $signatory->name,
                'company_signatory_title' => $signatory->position,
                'company_signature_path'  => $signatory->signatureFilesystemPath(),
                'company_stamp_path'      => $legal->stampFilesystemPath(),
            ];
        }

        return [
            'company_signatory_name'  => $legal->signatoryName() ?: brand('legal_name'),
            'company_signatory_title' => $legal->signatoryTitle(),
            'company_signature_path'  => $legal->signatureFilesystemPath(),
            'company_stamp_path'    => $legal->stampFilesystemPath(),
        ];
    }

    private function borrowerContractLocale(?LoanApplication $application): string
    {
        $locale = $application?->customer?->user?->preferences['locale']
            ?? session('locale', config('app.locale', 'en'));

        return in_array($locale, ['en', 'sw'], true) ? $locale : 'en';
    }

    private function withBorrowerLocale(?LoanApplication $application, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($this->borrowerContractLocale($application));

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
