<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CustomerAsset;
use App\Models\CustomerGuarantor;
use App\Models\LoanApplication;
use App\Services\CollateralSecureService;
use App\Services\CustomerPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CollateralSecureController extends Controller
{
    private function customer()
    {
        $user = auth()->user();
        abort_unless($user?->customer, 403);

        return $user->customer;
    }

    public function borrowerHasCollateral(Request $request, LoanApplication $application, CollateralSecureService $service): RedirectResponse
    {
        $data = $request->validate([
            'has_collateral' => ['required', 'boolean'],
        ]);

        $service->borrowerHasCollateral($application, $this->customer(), (bool) $data['has_collateral']);

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('status', __('borrower.collateral_secure.saved'));
    }

    public function borrowerAskGuarantor(Request $request, LoanApplication $application, CollateralSecureService $service): RedirectResponse
    {
        $data = $request->validate([
            'ask_guarantor' => ['required', 'boolean'],
        ]);

        $service->borrowerAskGuarantor($application, $this->customer(), (bool) $data['ask_guarantor']);

        if ($data['ask_guarantor']) {
            return redirect()
                ->route('site.borrower.application', $application)
                ->with('collateral_secure_flash', [
                    'title' => __('borrower.collateral_secure.sent_title'),
                    'message' => __('borrower.collateral_secure.sent_body'),
                    'confirm' => __('borrower.feedback.ok'),
                    'tone' => 'success',
                ]);
        }

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('status', __('borrower.collateral_secure.saved'));
    }

    public function linkAsset(Request $request, LoanApplication $application, CollateralSecureService $service): RedirectResponse
    {
        $data = $request->validate([
            'customer_asset_id' => ['required', 'integer', 'exists:customer_assets,id'],
        ]);

        $asset = CustomerAsset::query()->findOrFail($data['customer_asset_id']);
        $state = $service->linkAsset($application, $this->customer(), $asset);

        $flash = match ($state['status'] ?? '') {
            \App\Services\CollateralSecureService::STATUS_AWAITING_FEE => [
                'title' => __('borrower.collateral_secure.linked_fee_title'),
                'message' => __('borrower.collateral_secure.linked_fee_body'),
                'tone' => 'success',
            ],
            \App\Services\CollateralSecureService::STATUS_AWAITING_INSURANCE => [
                'title' => __('borrower.collateral_secure.linked_insurance_title'),
                'message' => __('borrower.collateral_secure.linked_insurance_body', [
                    'date' => data_get($state, 'insurance.expiry') ?: '—',
                ]),
                'tone' => 'warning',
            ],
            \App\Services\CollateralSecureService::STATUS_SECURED => [
                'title' => __('borrower.collateral_secure.linked_secured_title'),
                'message' => __('borrower.collateral_secure.linked_secured_body'),
                'tone' => 'success',
            ],
            default => null,
        };

        $redirect = redirect()->route('site.borrower.application', $application)
            ->with('status', __('borrower.collateral_secure.asset_linked'));

        return $flash
            ? $redirect->with('collateral_secure_flash', array_merge($flash, [
                'confirm' => __('borrower.feedback.ok'),
            ]))
            : $redirect;
    }

    public function payFee(Request $request, LoanApplication $application, CollateralSecureService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);

        $state = $service->state($application);
        abort_unless(($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_FEE, 422);

        $quote = $service->feeQuote($application);
        $due = (int) ($quote['due'] ?? 0);
        $ab = $service->assetBackedFeeProduct();
        abort_unless($ab, 422);

        if ($due > 0) {
            app(CustomerPaymentService::class)->create([
                'customer'       => $customer,
                'payment_type'   => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount'         => $due,
                'loan_product'   => $ab,
                'reference'      => 'CS-'.strtoupper(uniqid()),
                'source'         => $application,
                'auto_verify'    => true,
            ]);
        }

        $service->markFeePaid($application->fresh());

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('status', __('borrower.collateral_secure.fee_paid'));
    }

    public function guarantorRespond(Request $request, CustomerGuarantor $customerGuarantor, CollateralSecureService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless(app(\App\Services\GuarantorAccessService::class)->canViewGuarantee($customer, $customerGuarantor), 404);

        $application = $customerGuarantor->application
            ?? LoanApplication::query()->find($customerGuarantor->loan_application_id);
        abort_unless($application, 404);

        $data = $request->validate([
            'accept' => ['required', 'boolean'],
        ]);

        $service->guarantorRespond($application, $customer, (bool) $data['accept']);

        return redirect()
            ->route('site.borrower.guaranteed.show', $customerGuarantor)
            ->with('status', __('borrower.collateral_secure.saved'));
    }

    public function guarantorLinkAsset(Request $request, CustomerGuarantor $customerGuarantor, CollateralSecureService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless(app(\App\Services\GuarantorAccessService::class)->canViewGuarantee($customer, $customerGuarantor), 404);

        $application = $customerGuarantor->application
            ?? LoanApplication::query()->find($customerGuarantor->loan_application_id);
        abort_unless($application, 404);

        $data = $request->validate([
            'customer_asset_id' => ['required', 'integer', 'exists:customer_assets,id'],
        ]);

        $asset = CustomerAsset::query()->findOrFail($data['customer_asset_id']);
        $state = $service->linkAsset($application, $customer, $asset);

        $flash = match ($state['status'] ?? '') {
            \App\Services\CollateralSecureService::STATUS_AWAITING_FEE,
            \App\Services\CollateralSecureService::STATUS_SECURED => [
                'title' => __('borrower.collateral_secure.guarantor_linked_title'),
                'message' => __('borrower.collateral_secure.guarantor_linked_body'),
                'tone' => 'success',
            ],
            \App\Services\CollateralSecureService::STATUS_AWAITING_INSURANCE => [
                'title' => __('borrower.collateral_secure.linked_insurance_title'),
                'message' => __('borrower.collateral_secure.linked_insurance_body', [
                    'date' => data_get($state, 'insurance.expiry') ?: '—',
                ]),
                'tone' => 'warning',
            ],
            default => null,
        };

        $redirect = redirect()
            ->route('site.borrower.guaranteed.show', $customerGuarantor)
            ->with('status', __('borrower.collateral_secure.asset_linked'));

        return $flash
            ? $redirect->with('collateral_secure_flash', array_merge($flash, [
                'confirm' => __('borrower.feedback.ok'),
            ]))
            : $redirect;
    }
}
