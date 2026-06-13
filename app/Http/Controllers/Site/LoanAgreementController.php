<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Services\ApplicationDisbursementReadinessService;
use App\Services\LoanAgreementService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LoanAgreementController extends Controller
{
    use AuditsActions;

    public function __construct(
        private readonly LoanAgreementService $service,
        private readonly NotificationService $notifier,
        private readonly ApplicationDisbursementReadinessService $readiness,
    ) {}

    /**
     * Borrower-facing offer letter page: shows status, OTP request and signing form.
     */
    public function show(LoanApplication $application): View
    {
        $customer = $this->customerOrFail($application);

        $agreement = LoanAgreement::where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        if ($agreement) {
            $agreement = app(\App\Services\OfferLetterExpiryService::class)->expireIfStale($agreement);
        }

        return view('site.borrower.agreement', compact('application', 'agreement', 'customer'))
            ->with([
                'requireAcceptanceCode' => app(\App\Services\OfferSettingsService::class)->requireOfferAcceptanceCode(),
            ]);
    }

    public function showContract(LoanApplication $application): View|RedirectResponse
    {
        $customer = $this->customerOrFail($application);

        if ($this->readiness->needsBorrowerSignature($application)) {
            return redirect()
                ->route('site.borrower.application.agreement', $application)
                ->with('status', __('borrower.contract.sign_offer_first'));
        }

        if ($this->readiness->needsPostApprovalFees($application)) {
            return redirect()
                ->route('site.borrower.application.post-approval-fees', $application)
                ->with('status', __('borrower.contract.pay_fees_first'));
        }

        if ($this->readiness->needsDisbursementDetailsConfirmation($application)) {
            return redirect()
                ->route('site.borrower.application.disbursement-details', $application)
                ->with('status', __('borrower.disbursement_details.required_before_contract'));
        }

        $contract = $this->readiness->loanContract($application);
        if (! $contract) {
            $contract = $this->service->ensureLoanContractAfterFees($application->fresh());
        }

        abort_unless($contract, 404, __('borrower.contract.not_ready'));

        $application->loadMissing(['customer', 'product', 'signatures', 'customerGuarantors.guarantor']);
        $checklist = $this->readiness->disbursementChecklist($application);
        $disbursementDetails = app(\App\Services\CustomerDisbursementDetailsService::class)
            ->snapshotForApplication($application);
        $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
        $snap = $contract->snapshot ?? [];
        $needsGuarantor = $this->readiness->requiresGuarantorSignature($application);
        $guarantorSigned = $this->readiness->guarantorSigned($application);

        return view('site.borrower.contract', compact(
            'application',
            'contract',
            'customer',
            'checklist',
            'disbursementDetails',
            'detailsService',
            'snap',
            'needsGuarantor',
            'guarantorSigned',
        ))->with([
            'requireAcceptanceCode' => app(\App\Services\OfferSettingsService::class)->requireContractAcceptanceCode(),
        ]);
    }

    public function requestOtp(LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);

        $agreement = $this->offerOrFail($application);
        app(\App\Services\OfferLetterExpiryService::class)->expireIfStale($agreement);

        if ($agreement->fresh()->isOfferExpired()) {
            return back()->with('error', 'This offer has expired. Please contact the lender for a new offer letter.');
        }

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

    public function requestContractOtp(LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);
        $contract = $this->contractOrFail($application);

        if ($contract->isSigned()) {
            return back()->with('status', __('borrower.contract.already_signed'));
        }

        $code = $this->service->issueSigningOtp($contract);

        $customer = $this->customerOrFail($application);
        if ($customer->phone) {
            $this->notifier->sendSms(
                $customer->phone,
                "Your Kopa Fasta loan contract signing code is {$code}. It expires in 10 minutes. Do not share.",
                $customer,
                'contract_otp'
            );
        }

        $flash = __('borrower.contract.otp_sent');
        if (app()->environment('local', 'testing')) {
            $flash .= " (Dev code: {$code})";
        }

        $this->auditBorrower('contract.otp_requested', $application, [
            'agreement_id' => $contract->id,
        ]);

        return back()->with('otp_sent', $flash);
    }

    public function sign(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);
        $agreement = $this->offerOrFail($application);
        app(\App\Services\OfferLetterExpiryService::class)->expireIfStale($agreement);

        if ($agreement->fresh()->isOfferExpired()) {
            return back()->with('error', 'This offer has expired. Please contact the lender for a new offer letter.');
        }

        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        [$ok, $message] = $this->service->signWithOtp(
            $agreement,
            $data['otp'],
            $request->ip(),
            (string) $request->userAgent()
        );

        if ($ok) {
            $this->service->generateOfferLetter($application, regenerate: true);
            $this->auditBorrower('agreement.signed', $application, [
                'agreement_id' => $agreement->id,
                'reference'    => $agreement->reference,
            ]);

            if ($this->readiness->needsPostApprovalFees($application->fresh())) {
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

    public function acceptOffer(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);
        $agreement = $this->offerOrFail($application);
        app(\App\Services\OfferLetterExpiryService::class)->expireIfStale($agreement);

        if ($agreement->fresh()->isOfferExpired()) {
            return back()->with('error', 'This offer has expired. Please contact the lender for a new offer letter.');
        }

        if (app(\App\Services\OfferSettingsService::class)->requireOfferAcceptanceCode()) {
            return back()->with('error', 'Acceptance code is required. Request a code and enter it to accept.');
        }

        [$ok, $message] = $this->service->acceptDirectly(
            $agreement,
            $request->ip(),
            (string) $request->userAgent()
        );

        if ($ok) {
            $this->service->generateOfferLetter($application, regenerate: true);
            $this->auditBorrower('agreement.accepted', $application, [
                'agreement_id' => $agreement->id,
                'reference'    => $agreement->reference,
            ]);

            if ($this->readiness->needsPostApprovalFees($application->fresh())) {
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

    public function signContract(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);
        $contract = $this->contractOrFail($application);

        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        [$ok, $message] = $this->service->signWithOtp(
            $contract,
            $data['otp'],
            $request->ip(),
            (string) $request->userAgent()
        );

        if ($ok) {
            $this->service->generateLoanContract($application, regenerate: true);
            $this->auditBorrower('contract.signed', $application, [
                'agreement_id' => $contract->id,
                'reference'    => $contract->reference,
            ]);
        }

        return redirect()
            ->route('site.borrower.application.contract', $application)
            ->with($ok ? 'status' : 'error', $ok ? __('borrower.contract.signed_success') : $message);
    }

    public function acceptContract(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);
        $contract = $this->contractOrFail($application);

        if (app(\App\Services\OfferSettingsService::class)->requireContractAcceptanceCode()) {
            return back()->with('error', __('borrower.contract.code_required'));
        }

        [$ok, $message] = $this->service->acceptDirectly(
            $contract,
            $request->ip(),
            (string) $request->userAgent()
        );

        if ($ok) {
            $this->service->generateLoanContract($application, regenerate: true);
            $this->auditBorrower('contract.accepted', $application, [
                'agreement_id' => $contract->id,
                'reference'    => $contract->reference,
            ]);
        }

        return redirect()
            ->route('site.borrower.application.contract', $application)
            ->with($ok ? 'status' : 'error', $ok ? __('borrower.contract.signed_success') : $message);
    }

    public function declineContract(Request $request, LoanApplication $application): RedirectResponse
    {
        $this->customerOrFail($application);
        $contract = $this->contractOrFail($application);

        abort_if($contract->isSigned(), 422, __('borrower.contract.already_signed'));

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $contract->update([
            'status' => 'cancelled',
        ]);

        $this->auditBorrower('contract.declined', $application, [
            'agreement_id' => $contract->id,
            'reason'       => $request->input('reason'),
        ]);

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('status', __('borrower.contract.declined'));
    }

    public function download(LoanAgreement $agreement): BinaryFileResponse
    {
        $user = Auth::user();
        $customer = Customer::where('user_id', $user->id ?? 0)->first();

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

    private function contractOrFail(LoanApplication $application): LoanAgreement
    {
        $contract = $this->readiness->loanContract($application);
        abort_unless($contract, 404, __('borrower.contract.not_ready'));

        return $contract;
    }
}
