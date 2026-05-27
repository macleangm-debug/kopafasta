<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
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

        $application->loadMissing(['customer', 'product']);

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
        $pdf = Pdf::loadView('pdf.offer-letter', [
            'application' => $application,
            'snapshot'    => $snapshot,
            'agreement'   => $agreement,
        ])->setPaper('a4');

        $path = "agreements/{$agreement->reference}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $agreement->file_path = $path;
        $agreement->save();

        return $agreement;
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
            'otp_code'          => null, // invalidate
        ]);

        return [true, 'Signed successfully.'];
    }

    private function snapshotFromApplication(LoanApplication $a): array
    {
        $amount  = (float) ($a->approved_amount ?? $a->requested_amount ?? 0);
        $rate    = (float) ($a->product->interest_rate ?? 0);
        $tenure  = (int)   ($a->approved_tenure_months ?? $a->requested_tenure_months ?? $a->product->default_tenure_months ?? 0);

        // Reducing-balance EMI estimate (informational; final schedule built at disbursement)
        $emi = 0.0;
        if ($amount > 0 && $tenure > 0) {
            if ($rate > 0) {
                $pow = pow(1 + $rate, $tenure);
                $emi = round($amount * $rate * $pow / ($pow - 1), 2);
            } else {
                $emi = round($amount / $tenure, 2);
            }
        }

        return [
            'application_number' => $a->application_number,
            'product_name'       => $a->product->name ?? null,
            'product_code'       => $a->product->code ?? null,
            'principal'          => $amount,
            'interest_rate'      => $rate,
            'tenure_months'      => $tenure,
            'estimated_emi'      => $emi,
            'customer_name'      => trim(($a->customer->first_name ?? '').' '.($a->customer->last_name ?? '')),
            'customer_id'        => $a->customer->national_id ?? null,
            'customer_phone'     => $a->customer->phone ?? null,
            'purpose'            => $a->purpose ?? null,
            'generated_at'       => now()->toIso8601String(),
        ];
    }
}
