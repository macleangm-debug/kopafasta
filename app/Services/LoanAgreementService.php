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

        $agreement->fill([
            'snapshot'             => $snapshot,
            'status'               => 'sent',
            'sent_at'              => now(),
            'generated_by_user_id' => Auth::id(),
        ]);

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

        $agreement->update([
            'status'            => 'signed',
            'signed_at'         => now(),
            'signed_ip'         => $ip,
            'signed_user_agent' => $ua ? substr($ua, 0, 255) : null,
            'signature_method'  => 'otp',
            'otp_code'          => null,
        ]);

        $application = $agreement->loanApplication;
        if ($application && $agreement->document_type === 'offer_letter') {
            $this->generateLoanContract($application, regenerate: true);
        }

        return [true, 'Signed successfully.'];
    }

    private function snapshotFromApplication(LoanApplication $a): array
    {
        $amount = app(ApplicationOfferService::class)->effectiveAmount($a);
        $product = $a->product;
        $rateBreakdown = app(DisplayedRateService::class)->breakdown($product, $amount);
        $monthlyRate = $rateBreakdown['displayed_monthly_rate'];
        $tenure = (int) ($a->offered_tenure_months ?? $a->approved_tenure_months ?? $a->requested_tenure_months ?? $product->default_tenure_months ?? 0);
        $cadence = $a->product->repayment_cadence ?? 'weekly';

        $schedule = app(RepaymentScheduleGenerator::class)->preview($amount, $monthlyRate, $tenure, $cadence);
        $instalment = $schedule[0]['total_due'] ?? 0.0;
        $totalInterest = round(collect($schedule)->sum('interest_due'), 2);
        $totalFees = (float) ($a->processing_fee ?? 0);
        $isAssetLoan = ($a->product->code ?? '') === config('asset_marketplace.asset_loan_product_code', 'AL');
        $reservation = AssetReservation::query()
            ->with('asset')
            ->where('loan_application_id', $a->id)
            ->first();

        $signaturePath = setting('company.signature_path');
        $companySignaturePath = $signaturePath
            ? storage_path('app/public/'.ltrim($signaturePath, '/'))
            : null;

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
            'repayment_schedule'   => $schedule,
            'customer_name'        => trim(($a->customer->first_name ?? '').' '.($a->customer->last_name ?? '')),
            'customer_id'          => $a->customer->national_id ?? null,
            'customer_phone'       => $a->customer->phone ?? null,
            'purpose'              => $a->purpose ?? null,
            'generated_at'         => now()->toIso8601String(),
            'borrower_signature'   => $a->signatures->firstWhere('signer_type', 'borrower'),
            'guarantor_signature'  => $a->signatures->firstWhere('signer_type', 'guarantor'),
            'company_signatory'    => brand('legal_name'),
            'company_signatory_name' => setting('company.signatory_name') ?: brand('legal_name'),
            'company_signatory_title' => setting('company.signatory_title'),
            'company_signature_path' => ($companySignaturePath && is_file($companySignaturePath)) ? $companySignaturePath : null,
            'is_asset_loan'        => $isAssetLoan,
            'asset_title'          => $reservation?->asset?->title,
            'asset_ownership_note' => $isAssetLoan ? config('asset_marketplace.ownership_note') : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function renderAgreementPdf(?DocumentTemplate $template, string $fallbackView, array $data): \Barryvdh\DomPDF\PDF
    {
        if ($template && filled($template->content)) {
            $html = Blade::render($template->content, $data);

            return Pdf::loadHTML($html)->setPaper('a4');
        }

        return Pdf::loadView($fallbackView, $data)->setPaper('a4');
    }
}
