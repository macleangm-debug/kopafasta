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
use Illuminate\Validation\ValidationException;

class LoanAgreementService
{
    /**
     * Offer letter, pre-disbursement contract, executed contract, and the letter to show as signed.
     *
     * @return array{offer: ?LoanAgreement, contract: ?LoanAgreement, final: ?LoanAgreement, signed: ?LoanAgreement}
     */
    public function creditFileLetters(?LoanApplication $application): array
    {
        if (! $application) {
            return ['offer' => null, 'contract' => null, 'final' => null, 'signed' => null];
        }

        $offer = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        $contract = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'loan_contract')
            ->latest('id')
            ->first();

        $final = LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'final_loan_contract')
            ->latest('id')
            ->first();

        $signed = null;
        if ($final?->file_path) {
            $signed = $final;
        } elseif ($contract && $contract->isSigned() && $contract->file_path) {
            $signed = $contract;
        } elseif ($final) {
            $signed = $final;
        } elseif ($contract?->isSigned()) {
            $signed = $contract;
        }

        return compact('offer', 'contract', 'final', 'signed');
    }

    /**
     * Generate (or regenerate) an offer letter PDF for a loan application.
     */
    public function generateOfferLetter(LoanApplication $application, bool $regenerate = false): LoanAgreement
    {
        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->first();

        if ($existing && $existing->isSigned()) {
            return $existing;
        }

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors.guarantor']);

        $snapshot = $this->snapshotFromApplication($application);

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'offer_letter',
            'reference' => 'OL-'.strtoupper(Str::random(8)),
        ]);

        $wasSigned = $existing && $existing->isSigned();

        $fill = [
            'snapshot' => $snapshot,
            'expires_at' => now()->addDays(app(LegalSettingsService::class)->offerValidityDays()),
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
            'snapshot' => $snapshot,
            'agreement' => $agreement,
        ];
        $pdf = $this->renderAgreementPdf(
            null,
            'pdf.offer-letter',
            $viewData,
        );

        $this->writeAgreementPdf($agreement, $pdf, $snapshot);

        return $agreement;
    }

    /**
     * Formal rejection letter PDF (offer-letter style with company signatory + stamp).
     */
    public function generateRejectionLetter(LoanApplication $application, bool $regenerate = false): LoanAgreement
    {
        $existing = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'rejection_letter')
            ->first();

        if ($existing && ! $regenerate) {
            return $existing;
        }

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors.guarantor']);

        $snapshot = $this->withRejectionLetterFields(
            $application,
            $this->snapshotFromApplication($application),
        );

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'rejection_letter',
            'reference' => 'RJ-'.strtoupper(Str::random(8)),
        ]);

        $agreement->fill([
            'snapshot' => $snapshot,
            'status' => 'sent',
            'sent_at' => now(),
            'generated_by_user_id' => Auth::id(),
        ]);

        $viewData = [
            'application' => $application,
            'snapshot' => $snapshot,
            'agreement' => $agreement,
        ];

        $pdf = $this->renderAgreementPdf(null, 'pdf.rejection-letter', $viewData);
        $this->writeAgreementPdf($agreement, $pdf, $snapshot);

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

        if ($existing && $existing->isSigned()) {
            return $existing;
        }

        if (! $existing || ! $existing->isSigned()) {
            $readiness = app(PostApprovalNextActionService::class)->contractReadiness($application);
            if (! $readiness['ready']) {
                throw ValidationException::withMessages([
                    'contract' => $readiness['headline'].'. '.$readiness['detail'],
                ]);
            }
        }

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors.guarantor']);
        $snapshot = $this->snapshotFromApplication($application);

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-'.strtoupper(Str::random(8)),
        ]);

        $wasSigned = $existing && $existing->isSigned();

        $fill = [
            'snapshot' => $snapshot,
            'generated_by_user_id' => Auth::id(),
        ];

        if (! $wasSigned) {
            $fill['status'] = 'sent';
            $fill['sent_at'] = now();
        }

        $agreement->fill($fill);

        $viewData = [
            'application' => $application,
            'snapshot' => $snapshot,
            'agreement' => $agreement,
        ];
        $pdf = $this->renderAgreementPdf(null, 'pdf.loan-contract', $viewData);

        $this->writeAgreementPdf($agreement, $pdf, $snapshot);

        return $agreement;
    }

    /** Re-render agreement PDFs after guarantor signature without clearing borrower signatures. */
    public function refreshGuarantorOnDocuments(LoanApplication $application): void
    {
        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors.guarantor']);

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
            $snapshot = $wasSigned
                ? $this->hydrateSnapshot($agreement, $application)
                : $this->snapshotFromApplication($application);
            $agreement->snapshot = $snapshot;

            $viewData = [
                'application' => $application,
                'snapshot' => $snapshot,
                'agreement' => $agreement,
            ];

            $fallbackView = $documentType === 'offer_letter' ? 'pdf.offer-letter' : 'pdf.loan-contract';
            $pdf = $this->renderAgreementPdf(null, $fallbackView, $viewData);
            $this->writeAgreementPdf($agreement, $pdf, $snapshot);

            if (! $wasSigned && $agreement->status !== 'signed') {
                $agreement->status = 'sent';
                $agreement->save();
            }
        }

        $loan = $application->loan;
        if ($loan?->disbursement_date) {
            $this->generateFinalLoanContract($loan->fresh(), regenerate: true);
        }
    }

    /**
     * Issue a fresh OTP for signing. Returns the agreement after persisting code + expiry.
     */
    public function issueSigningOtp(LoanAgreement $agreement): string
    {
        if ($agreement->document_type === 'offer_letter' && $agreement->isOfferExpired()) {
            throw ValidationException::withMessages([
                'otp' => 'This offer has expired. Please contact the lender for a new offer letter.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $agreement->update([
            'otp_code' => $code,
            'otp_sent_at' => now(),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
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
     * Verify the borrower's account PIN and mark the agreement as signed.
     *
     * @return array{0:bool,1:string}
     */
    public function signWithPin(LoanAgreement $agreement, string $pin, ?string $ip = null, ?string $ua = null): array
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
        $application?->loadMissing('customer.user');
        if ($agreement->document_type === 'offer_letter' && $application && ! $this->borrowerCanRespondToOffer($application, $agreement)) {
            return [false, __('borrower.agreement.already_declined')];
        }

        $user = $application?->customer?->user;
        $pins = app(PinService::class);
        if (! $user || ! $pins->hasPin($user)) {
            return [false, __('borrower.agreement.pin_not_set')];
        }
        if (! $pins->verify($pin, $user->pin_hash)) {
            return [false, __('borrower.agreement.pin_incorrect')];
        }

        $this->markSigned($agreement, 'pin', $ip, $ua);

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
            'label' => ($loan->product->repayment_cadence ?? 'weekly') === 'monthly'
                ? 'Month '.$row->installment_no
                : 'Week '.$row->installment_no,
            'due_date' => $row->due_date?->toDateString(),
            'principal_due' => (float) $row->principal_due,
            'interest_due' => (float) $row->interest_due,
            'total_due' => (float) $row->total_due,
        ])->all();

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'repayment_schedule',
            'reference' => 'RS-'.strtoupper(Str::random(8)),
        ]);

        $agreement->fill([
            'snapshot' => $snapshot,
            'status' => 'sent',
            'sent_at' => now(),
            'generated_by_user_id' => Auth::id(),
        ]);

        $viewData = [
            'application' => $application,
            'snapshot' => $snapshot,
            'agreement' => $agreement,
            'loan' => $loan,
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

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors.guarantor', 'loanGroup.members.customer']);
        $snapshot = $this->snapshotFromApplication($application);
        if ($signedContract?->isSigned()) {
            $snapshot = $this->hydrateSnapshot($signedContract, $application);
        } elseif ($existing?->isSigned()) {
            $snapshot = $this->hydrateSnapshot($existing, $application);
        }
        $snapshot['disbursement_date'] = $loan->disbursement_date?->toDateString();
        $snapshot['first_due_date'] = $schedules->first()?->due_date?->toDateString();
        $snapshot['last_due_date'] = $schedules->last()?->due_date?->toDateString();
        $snapshot['schedule_is_estimate'] = false;
        $runningBalance = (float) $loan->principal_amount;
        $snapshot['repayment_schedule'] = $schedules->map(function ($row) use ($loan, &$runningBalance) {
            $totalDue = (float) $row->total_due;
            $outstandingAfter = max(0, round($runningBalance - (float) $row->principal_due, 2));
            $entry = [
                'installment_no' => $row->installment_no,
                'label' => ($loan->product->repayment_cadence ?? 'weekly') === 'monthly'
                    ? 'Month '.$row->installment_no
                    : 'Week '.$row->installment_no,
                'due_date' => $row->due_date?->toDateString(),
                'principal_due' => (float) $row->principal_due,
                'interest_due' => (float) $row->interest_due,
                'total_due' => $totalDue,
                'outstanding_balance' => $outstandingAfter,
            ];
            $runningBalance = $outstandingAfter;

            return $entry;
        })->all();

        if ($signedContract?->acceptance_signature_data) {
            $customer = $application->customer;
            $snapshot['borrower_signature'] = (object) [
                'signature_data' => $signedContract->acceptance_signature_data,
                'signer_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Borrower',
                'signed_at' => $signedContract->signed_at,
            ];
        }

        $agreement = $existing ?: new LoanAgreement([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'final_loan_contract',
            'reference' => 'FLC-'.strtoupper(Str::random(8)),
        ]);

        $agreement->fill([
            'snapshot' => $snapshot,
            'status' => 'signed',
            'sent_at' => now(),
            'signed_at' => now(),
            'generated_by_user_id' => Auth::id(),
        ]);

        $viewData = [
            'application' => $application,
            'snapshot' => $snapshot,
            'agreement' => $agreement,
            'loan' => $loan,
            'signedContract' => $signedContract,
        ];

        $pdf = $this->renderAgreementPdf(null, 'pdf.final-loan-contract', $viewData);
        $this->writeAgreementPdf($agreement, $pdf, $snapshot);

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
            'status' => 'signed',
            'signed_at' => now(),
            'signed_ip' => $ip,
            'signed_user_agent' => $ua ? substr($ua, 0, 255) : null,
            'signature_method' => $method,
            'acceptance_signature_data' => $acceptanceSignature,
            'otp_code' => null,
        ]);

        if ($agreement->document_type === 'loan_contract' && $application) {
            app(DisbursementSlaService::class)->startClockOnContractSigned($application->fresh());
        }
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
            'offer_status' => 'accepted',
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
            'status' => $postApproval ? 'approved' : 'awaiting_offer',
            'offer_status' => 'pending_borrower',
            'offer_responded_at' => null,
            'offer_decline_reason' => null,
            'offer_issued_at' => now(),
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

        if (! app(PostApprovalNextActionService::class)->contractReadiness($application)['ready']) {
            return $existing;
        }

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

            app(GroupContractSignatureService::class)->notifyPendingMembers($application);
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
        $cadence = app(GroupLendingService::class)->effectiveRepaymentCadence($a->product);

        $schedule = app(RepaymentScheduleGenerator::class)->previewEstimate($amount, $monthlyRate, $tenure, $cadence);
        $instalment = $schedule[0]['total_due'] ?? 0.0;
        $totalInterest = round(collect($schedule)->sum('interest_due'), 2);
        $totalFees = (float) ($a->processing_fee ?? 0);
        $totalRepayable = round(collect($schedule)->sum('total_due') + $totalFees, 2);
        $offerSettings = app(OfferSettingsService::class);
        $profile = app(LoanAgreementProductProfile::class)->for($a);
        $isAssetLoan = (bool) ($profile['is_asset'] ?? false);
        $collateral = $a->collateralAsset;
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

        $activityLabel = $customer?->activity_type;

        $groupLending = app(GroupLendingService::class);
        $isGroupLoan = $groupLending->isGroupProduct($a->product);
        $groupMembers = [];
        $groupName = null;
        $totalGroupLiability = null;

        if ($isGroupLoan) {
            $a->loadMissing('loanGroup.members.customer', 'signatures');
            $group = $a->loanGroup
                ?? \App\Models\LoanGroup::query()->where('primary_application_id', $a->id)->first();
            if (! $group) {
                $memberRow = \App\Models\LoanGroupMember::query()
                    ->with('group.members.customer')
                    ->where('loan_application_id', $a->id)
                    ->first();
                $group = $memberRow?->group;
            }
            if ($group) {
                $group->loadMissing('members.customer');
                $groupName = $group->name;
                $groupMembers = $group->members
                    ->filter(fn ($member) => ($member->member_status ?? 'active') === 'active')
                    ->map(function ($member) use ($a) {
                        $memberCustomer = $member->customer;
                        $signature = $a->signatures
                            ->where('signer_type', 'group_member')
                            ->first(fn ($sig) => (int) ($sig->group_member_invitation_id ?? 0) === (int) ($member->group_member_invitation_id ?? 0)
                                || ($member->role === 'leader' && $sig->signer_type === 'borrower'));

                        if ($member->role === 'leader') {
                            $signature = $a->signatures->firstWhere('signer_type', 'borrower') ?: $signature;
                        }

                        $contractStatus = $member->contract_signature_status ?: 'pending';
                        if ($contractStatus === 'pending' && $signature) {
                            $contractStatus = 'signed';
                        }

                        return [
                            'name' => $memberCustomer?->full_name ?? '—',
                            'customer_number' => $memberCustomer?->customer_number,
                            'national_id' => $memberCustomer?->national_id,
                            'phone' => $memberCustomer?->phone,
                            'role' => $member->role,
                            'requested_amount' => (float) ($member->requested_amount ?? 0),
                            'signature' => $signature,
                            'signature_data' => $member->contract_signature_data
                                ?: ($signature->signature_data ?? null),
                            'signer_name' => $member->contract_signer_name
                                ?: ($memberCustomer?->full_name ?? null),
                            'signed_at' => $member->contract_signed_at,
                            'signature_status' => $contractStatus,
                        ];
                    })->values()->all();
                $totalGroupLiability = collect($groupMembers)->sum('requested_amount');
            }
        }

        $disclosure = app(LoanAgreementDisclosureService::class);
        $identity = $disclosure->companyIdentity();
        $penalty = $disclosure->penaltyDisclosure($a);
        $recovery = $disclosure->recoverySchedule($a);
        $gpsFee = $disclosure->gpsPostApprovalFee($a);
        $facilityCharges = $disclosure->facilityCharges($a);
        $workedExamples = $disclosure->workedExamples($penalty, (float) $amount, (float) $instalment, $schedule);

        $snapshot = [
            'application_number' => $a->application_number,
            'product_name' => $a->product->name ?? null,
            'product_code' => $a->product->code ?? null,
            'principal' => $amount,
            'interest_rate' => $monthlyRate,
            'bot_regulated_rate' => $rateBreakdown['bot_regulated_rate'],
            'internal_fee_rate' => $rateBreakdown['internal_fee_rate'],
            'displayed_monthly_rate' => $monthlyRate,
            'rate_breakdown' => $rateBreakdown,
            'hides_interest' => (bool) ($a->product?->hidesInterest() ?? false),
            'tenure_months' => $tenure,
            'repayment_cadence' => $cadence,
            'installment_count' => count($schedule),
            'estimated_emi' => $instalment,
            'installment_label' => app(RepaymentScheduleGenerator::class)->installmentLabel($cadence),
            'total_interest' => $totalInterest,
            'total_fees' => $totalFees,
            'total_repayable' => $totalRepayable,
            'repayment_schedule' => $schedule,
            'schedule_is_estimate' => true,
            // Pre-disbursement contracts must not invent an actual disbursement date.
            'disbursement_date' => null,
            'repayment_commencement_days' => $offerSettings->repaymentCommencementDays(),
            'customer_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            'customer_id' => $customer->national_id ?? null,
            'customer_phone' => $customer->phone ?? null,
            'customer_address' => $borrowerAddress ?: null,
            'customer_activity' => $activityLabel ?: ($customer?->business_name ?? $customer?->employment_type),
            'customer_income' => $customer->monthly_income
                ? format_money((float) $customer->monthly_income)
                : (income_range_label($customer->income_range ?? '') ?: $customer->income_range),
            'guarantor_name' => $guarantor ? trim(($guarantor->first_name ?? '').' '.($guarantor->last_name ?? '')) : null,
            'guarantor_nida' => $guarantor?->national_id,
            'guarantor_address' => $guarantor?->address,
            'guarantor_phone' => $guarantor?->phone,
            'guarantor_relationship' => $guarantor?->relationship,
            'purpose' => $a->purpose ?? data_get($a->screening_payload, 'purpose'),
            'purpose_other' => data_get($a->screening_payload, 'purpose_other'),
            'offer_expires_at' => ($existing = LoanAgreement::query()
                ->where('loan_application_id', $a->id)
                ->where('document_type', 'offer_letter')
                ->latest('id')
                ->first())?->expires_at?->toDateString()
                ?? now()->addDays($legal->offerValidityDays())->toDateString(),
            'offer_validity_days' => $legal->offerValidityDays(),
            'legal_clauses' => $legal->contractClauses(),
            'contract_sections' => $legal->contractSections(),
            'generated_at' => now()->toIso8601String(),
            'locale' => $this->borrowerContractLocale($a),
            'document_version' => $identity['document_version'],
            ...$identity,
            ...$penalty,
            'recovery_schedule' => $recovery,
            'gps_fee' => $gpsFee,
            'facility_charges' => $facilityCharges,
            'worked_examples' => $workedExamples,
            'is_salary_advance' => (bool) ($profile['is_salary_advance'] ?? false),
            'tenure_max_months' => (int) ($product?->tenure_max_months ?? $tenure),
            'contract_modules' => $profile,
            'borrower_signature' => $this->borrowerSignatureForPdf($a, 'loan_contract'),
            'guarantor_signature' => $a->signatures->firstWhere('signer_type', 'guarantor'),
            'offer_borrower_signature' => $this->borrowerSignatureForPdf($a, 'offer_letter'),
            'company_signatory' => $identity['company_legal_name'],
            ...$this->companySignatorySnapshot($legal),
            'is_asset_loan' => $isAssetLoan,
            'asset_title' => $reservation?->asset?->title ?? ($collateral?->description),
            'asset_supplier' => $reservation?->asset?->supplier_name,
            'asset_serial_number' => $reservation?->asset?->serial_number,
            'asset_chassis_number' => $reservation?->asset?->chassis_number,
            'asset_engine_number' => $reservation?->asset?->engine_number,
            'asset_insurance_policy' => $reservation?->asset?->insurance_policy_number,
            'asset_ownership_note' => $isAssetLoan ? config('asset_marketplace.ownership_note') : null,
            'collateral_asset_type' => $collateral?->asset_type,
            'collateral_description' => $collateral?->description,
            'collateral_market_value' => $collateral?->market_value,
            'collateral_forced_sale_value' => $collateral?->forced_sale_value,
            'collateral_gps_required' => (bool) ($collateral?->gps_required ?? false),
            'collateral_ltv_percent' => $collateral?->ltv_percent,
            'is_group_loan' => $isGroupLoan,
            'group_name' => $groupName,
            'group_members' => $groupMembers,
            'total_group_liability' => $totalGroupLiability,
            ...$this->offerApprovalFields($a),
        ];

        $offerRecord = LoanAgreement::query()
            ->where('loan_application_id', $a->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();
        $contractRecord = LoanAgreement::query()
            ->where('loan_application_id', $a->id)
            ->where('document_type', 'loan_contract')
            ->latest('id')
            ->first();

        $snapshot['offer_execution_method'] = $offerRecord?->signature_method;
        $snapshot['offer_signed_at'] = $offerRecord?->signed_at?->toIso8601String();
        $snapshot['contract_execution_method'] = $contractRecord?->signature_method;
        $snapshot['contract_signed_at'] = $contractRecord?->signed_at?->toIso8601String();
        $snapshot['contract_signed_ip'] = $contractRecord?->signed_ip;
        $snapshot['pin_verified'] = in_array($contractRecord?->signature_method, ['pin', 'otp'], true)
            || in_array($offerRecord?->signature_method, ['pin', 'otp'], true);
        $snapshot['terms_hash'] = $disclosure->termsHash($snapshot);

        return $snapshot;
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
                'signer_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Borrower',
                'signed_at' => $agreement->signed_at,
            ];
        }

        return $application->signatures->firstWhere('signer_type', 'borrower');
    }

    /**
     * Re-render the stored letter with the current Kopafasta branded Blade template.
     * Existing files were generated before this letterhead and would otherwise stay stale.
     */
    public function refreshBrandedPdf(LoanAgreement $agreement): \Barryvdh\DomPDF\PDF
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        $application = $agreement->loanApplication;
        $application?->loadMissing([
            'customer.user',
            'product',
            'loan.repaymentSchedules',
            'signatures',
            'customerGuarantors.guarantor',
            'loanGroup.members.customer',
        ]);

        $snapshot = $application
            ? $this->hydrateSnapshot($agreement, $application)
            : (is_array($agreement->snapshot) ? $agreement->snapshot : []);

        $view = match ($agreement->document_type) {
            'offer_letter' => 'pdf.offer-letter',
            'rejection_letter' => 'pdf.rejection-letter',
            'repayment_schedule' => 'pdf.repayment-schedule',
            'final_loan_contract' => $application?->loan ? 'pdf.final-loan-contract' : 'pdf.loan-contract',
            default => 'pdf.loan-contract',
        };

        $viewData = [
            'application' => $application,
            'snapshot' => $snapshot,
            'agreement' => $agreement,
            'loan' => $application?->loan,
            'signedContract' => $agreement,
            'includeScheduleAnnex' => $agreement->document_type === 'final_loan_contract',
        ];

        $pdf = $this->renderAgreementPdf(null, $view, $viewData);
        $this->writeAgreementPdf($agreement, $pdf, $snapshot);

        return $pdf;
    }

    public function ensureBrandedPdf(LoanAgreement $agreement): LoanAgreement
    {
        $needsRefresh = ! $this->usesCurrentBrandedTemplate($agreement)
            || ! $agreement->isSigned();

        if (! $needsRefresh) {
            return $agreement;
        }

        try {
            $this->refreshBrandedPdf($agreement);
        } catch (\Throwable $e) {
            report($e);
        }

        return $agreement->fresh() ?? $agreement;
    }

    private function usesCurrentBrandedTemplate(LoanAgreement $agreement): bool
    {
        return data_get($agreement->snapshot, 'document_version') === LoanAgreementDisclosureService::DOCUMENT_VERSION
            && filled($agreement->file_path)
            && Storage::disk('public')->exists($agreement->file_path);
    }

    /** @return array<string, string> */
    public function brandedPdfHeaders(LoanAgreement $agreement, bool $download): array
    {
        $disposition = $download ? 'attachment' : 'inline';

        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$agreement->reference.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];
    }

    /** @param array<string, mixed> $data */
    private function renderAgreementPdf(?DocumentTemplate $template, string $fallbackView, array $data): \Barryvdh\DomPDF\PDF
    {
        $application = $data['application'] ?? null;

        return $this->withBorrowerLocale($application, function () use ($template, $fallbackView, $data) {
            if ($template && filled($template->content)) {
                $html = Blade::render($template->content, $data);

                return $this->decoratePdf(Pdf::loadHTML($html)->setPaper('a4'));
            }

            return $this->decoratePdf(Pdf::loadView($fallbackView, $data)->setPaper('a4'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrateSnapshot(LoanAgreement $agreement, LoanApplication $application): array
    {
        $fresh = $this->snapshotFromApplication($application);
        $stored = is_array($agreement->snapshot) ? $agreement->snapshot : [];
        $kept = [];
        foreach ($stored as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $kept[$key] = $value;
        }

        $snapshot = array_replace($fresh, $kept);
        $liveFromSettings = [
            'customer_activity',
            'purpose',
            'purpose_other',
            'recovery_schedule',
            'gps_fee',
            'facility_charges',
            'worked_examples',
            'legal_clauses',
            'contract_sections',
            'locale',
            'jurisdiction',
            'penalty_formula_sw',
            'penalty_formula_en',
            'penalty_basis_label_sw',
            'penalty_rate',
            'grace_days',
            'penalty_cap_percent',
            'offer_validity_days',
            'tenure_max_months',
            'approval_reason_code',
            'approval_reason_label',
            'approval_reason_notes',
        ];
        $alwaysFresh = [
            'document_version',
            'is_group_loan',
            'is_asset_loan',
            'is_salary_advance',
            'contract_modules',
            'group_members',
            'group_name',
            'total_group_liability',
            'customer_name',
            'customer_id',
            'customer_phone',
            'customer_address',
            'guarantor_name',
            'guarantor_nida',
            'guarantor_address',
            'guarantor_phone',
            'guarantor_relationship',
            'company_stamp_path',
            'company_signature_path',
            'ceo_signature_path',
            'finance_signature_path',
            'ceo_signatory_name',
            'ceo_signatory_title',
            'finance_signatory_name',
            'finance_signatory_title',
        ];

        foreach ($alwaysFresh as $key) {
            if (array_key_exists($key, $fresh)) {
                $snapshot[$key] = $fresh[$key];
            }
        }

        if (! $agreement->isSigned()) {
            foreach ($liveFromSettings as $key) {
                if (array_key_exists($key, $fresh)) {
                    $snapshot[$key] = $fresh[$key];
                }
            }
        }

        if ($agreement->document_type === 'rejection_letter') {
            $snapshot = $this->withRejectionLetterFields($application, $snapshot);
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function offerApprovalFields(LoanApplication $application): array
    {
        $approval = data_get($application->credit_appraisal_payload, 'committee_approval', []);
        $code = $approval['reason_code'] ?? null;
        $reasons = config('credit_recommendation.approval_reasons', []);
        $label = $approval['reason_label'] ?? (is_string($code) ? ($reasons[$code] ?? null) : null);

        return [
            'approval_reason_code' => $code,
            'approval_reason_label' => $label && $code !== 'custom' ? $label : ($approval['notes'] ?? $label),
            'approval_reason_notes' => $approval['notes'] ?? null,
        ];
    }

    /**
     * Rejection reasons, advice and capacity figures from the application (platform source of truth).
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withRejectionLetterFields(LoanApplication $application, array $snapshot): array
    {
        $locale = $this->borrowerContractLocale($application);
        $reasons = app(LoanRejectionReasonService::class)->reasonsForLetter(
            $application->rejection_reason_codes,
            $application->rejection_reason_code,
            $application->rejection_reason,
            $locale,
        );

        $snapshot['rejection_codes'] = $reasons['codes'];
        $snapshot['rejection_reasons'] = $reasons['labels'];
        $snapshot['rejection_reason'] = $reasons['summary'];
        $snapshot['rejection_detail'] = $reasons['detail'];
        $snapshot['rejection_advice'] = app(LoanRejectionReasonService::class)->resolveBorrowerAdvice(
            $application->rejection_advice_code,
            $application->rejection_advice,
            $locale,
        );
        $snapshot['requested_amount'] = (float) ($application->requested_amount ?? $snapshot['principal'] ?? 0);
        $snapshot['rejected_at'] = $application->updated_at?->toDateString() ?? now()->toDateString();
        $snapshot['locale'] = $locale;
        $snapshot['letter_kind'] = 'decision';
        $snapshot['show_legal_stamp'] = false;

        $capacity = data_get($application->screening_payload, 'capacity_auto_reject');
        if (is_array($capacity)) {
            $snapshot['capacity_auto_reject'] = [
                'is_group' => (bool) ($capacity['is_group'] ?? false),
                'proposed_installment' => (float) ($capacity['proposed_installment'] ?? 0),
                'available_capacity' => (float) ($capacity['available_capacity'] ?? 0),
                'requested_amount' => (float) ($capacity['requested_amount'] ?? $application->requested_amount ?? 0),
                'failed_members' => $capacity['failed_members'] ?? [],
                'group_members' => $capacity['group_members'] ?? [],
                'repayment_ratio_pct' => (float) ($capacity['repayment_ratio_pct'] ?? 33.33),
            ];
            $snapshot['failed_members'] = $capacity['failed_members'] ?? [];
            $snapshot['is_group_rejection'] = (bool) ($capacity['is_group'] ?? false);
        }

        return $snapshot;
    }

    private function decoratePdf(\Barryvdh\DomPDF\PDF $pdf): \Barryvdh\DomPDF\PDF
    {
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isFontSubsettingEnabled', false);

        try {
            $pdf->render();
            $canvas = $pdf->getDomPDF()->getCanvas();
            if ($canvas && method_exists($canvas, 'page_text')) {
                $font = null;
                $metrics = $pdf->getDomPDF()->getFontMetrics();
                if (method_exists($metrics, 'getFont')) {
                    $font = $metrics->getFont('DejaVu Sans');
                }
                $canvas->page_text(42, 820, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.42, 0.49, 0.45]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $pdf;
    }

    /** @return array<string, mixed> */
    private function companySignatorySnapshot(LegalSettingsService $legal): array
    {
        $ceo = $legal->activeCeoSignatory();
        $finance = $legal->activeFinanceSignatory();

        $company = $ceo
            ? [
                'company_signatory_name' => $ceo->name,
                'company_signatory_title' => $ceo->position ?: 'Chief Executive Officer',
                'company_signature_path' => $ceo->signatureFilesystemPath(),
                'company_stamp_path' => $legal->stampFilesystemPath(),
                'ceo_signatory_name' => $ceo->name,
                'ceo_signatory_title' => $ceo->position ?: 'Chief Executive Officer',
                'ceo_signature_path' => $ceo->signatureFilesystemPath(),
            ]
            : [
                'company_signatory_name' => $legal->signatoryName() ?: brand('legal_name'),
                'company_signatory_title' => $legal->signatoryTitle() ?: 'Chief Executive Officer',
                'company_signature_path' => $legal->signatureFilesystemPath(),
                'company_stamp_path' => $legal->stampFilesystemPath(),
                'ceo_signatory_name' => $legal->signatoryName() ?: brand('legal_name'),
                'ceo_signatory_title' => $legal->signatoryTitle() ?: 'Chief Executive Officer',
                'ceo_signature_path' => $legal->signatureFilesystemPath(),
            ];

        $financeBlock = [
            'finance_signatory_name' => $finance?->name,
            'finance_signatory_title' => $finance?->position ?: 'Finance manager',
            'finance_signature_path' => $finance?->signatureFilesystemPath(),
        ];

        return array_merge($company, $financeBlock);
    }

    private function writeAgreementPdf(LoanAgreement $agreement, $pdf, array $snapshot): void
    {
        $path = $agreement->file_path ?: "agreements/{$agreement->reference}.pdf";
        $bytes = $pdf->output();
        Storage::disk('public')->put($path, $bytes);
        $snapshot['document_hash'] = hash('sha256', $bytes);
        $agreement->file_path = $path;
        $agreement->snapshot = $snapshot;
        $agreement->save();
    }

    private function borrowerContractLocale(?LoanApplication $application): string
    {
        $application?->loadMissing('customer.user');
        $user = $application?->customer?->user;
        $prefs = is_array($user?->preferences) ? $user->preferences : [];

        foreach (['preferred_locale', 'locale'] as $key) {
            $value = $prefs[$key] ?? null;
            if (is_string($value) && in_array($value, ['en', 'sw'], true)) {
                return $value;
            }
        }

        if ($user && Auth::id() && (int) Auth::id() === (int) $user->id) {
            $sessionLocale = session('locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, ['en', 'sw'], true)) {
                return $sessionLocale;
            }
        }

        $country = session('country', 'TZ');
        $contractLocale = app(CountrySettingsService::class)->forCode(is_string($country) ? $country : 'TZ')['contract_locale'] ?? 'sw';

        return in_array($contractLocale, ['en', 'sw'], true) ? $contractLocale : 'sw';
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
