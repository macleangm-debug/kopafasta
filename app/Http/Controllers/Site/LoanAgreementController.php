<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Services\LoanAgreementService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LoanAgreementController extends Controller
{
    use AuditsActions;

    public function __construct(
        private readonly LoanAgreementService $service,
        private readonly NotificationService $notifier,
    ) {}

    /**
     * Borrower-facing agreement page: shows status, OTP request and signing form.
     */
    public function show(LoanApplication $application)
    {
        $customer = $this->customerOrFail($application);

        $agreement = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        return view('site.borrower.agreement', compact('application', 'agreement', 'customer'));
    }

    public function requestOtp(LoanApplication $application)
    {
        $this->customerOrFail($application);

        $agreement = $this->offerOrFail($application);
        if ($agreement->isSigned()) {
            return back()->with('status', 'This agreement is already signed.');
        }

        $code = $this->service->issueSigningOtp($agreement);

        $customer = $this->customerOrFail($application);
        if ($customer->phone) {
            $this->notifier->sendSms(
                $customer->phone,
                "Your Kopa Fasta loan-agreement signing code is {$code}. It expires in 10 minutes. Do not share.",
                $customer,
                'agreement_otp'
            );
        }

        $flash = 'We have sent a 6-digit code to your phone.';
        if (app()->environment('local', 'testing')) {
            $flash .= " (Dev code: {$code})";
        }

        $this->auditBorrower('agreement.otp_requested', $application, [
            'agreement_id' => $agreement->id,
        ]);

        return back()->with('otp_sent', $flash);
    }

    public function sign(Request $request, LoanApplication $application)
    {
        $this->customerOrFail($application);
        $agreement = $this->offerOrFail($application);

        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        [$ok, $message] = $this->service->signWithOtp(
            $agreement,
            $data['otp'],
            $request->ip(),
            (string) $request->userAgent()
        );

        // Regenerate PDF so the "signed" stamp is baked into the file.
        if ($ok) {
            $this->service->generateOfferLetter($application, regenerate: true);
            $this->auditBorrower('agreement.signed', $application, [
                'agreement_id' => $agreement->id,
                'reference'    => $agreement->reference,
            ]);

            $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
            if ($readiness->needsPostApprovalFees($application->fresh())) {
                $customer = $this->customerOrFail($application);
                app(NotificationService::class)->notifyInApp(
                    $customer,
                    __('borrower.post_approval_fees.notify_message', [
                        'reference' => $application->application_number,
                    ]),
                    'application',
                    'post_approval_fees_due',
                    __('borrower.post_approval_fees.notify_title'),
                    route('site.borrower.application.post-approval-fees', $application->id),
                    __('borrower.loan_profile.actions.pay_post_approval_fees'),
                );
            }
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }

    public function download(LoanAgreement $agreement)
    {
        $user = Auth::user();
        $customer = Customer::where('user_id', $user->id ?? 0)->first();

        // Borrowers may only download their own; admins (any user with admin guard) may download all.
        $isOwner = $customer && $agreement->customer_id === $customer->id;
        $isAdmin = Auth::guard('admin')->check();

        abort_unless($isOwner || $isAdmin, 403);
        abort_unless($agreement->file_path && Storage::disk('public')->exists($agreement->file_path), 404);

        return response()->file(Storage::disk('public')->path($agreement->file_path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$agreement->reference.'.pdf"',
        ]);
    }

    private function customerOrFail(LoanApplication $application): Customer
    {
        $customer = Customer::where('user_id', Auth::id())->firstOrFail();
        abort_unless($application->customer_id === $customer->id, 403);
        return $customer;
    }

    private function offerOrFail(LoanApplication $application): LoanAgreement
    {
        $agreement = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        abort_unless($agreement, 404, 'No offer letter has been issued yet.');
        return $agreement;
    }
}
