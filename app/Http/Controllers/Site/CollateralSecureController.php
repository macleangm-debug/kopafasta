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
            $phone = $customer->phone;
            abort_unless(filled($phone) || ! app(\App\Services\PayInService::class)->isLiveCollectionEnabled(), 422);

            $payment = app(CustomerPaymentService::class)->create([
                'customer'       => $customer,
                'payment_type'   => 'application_fee',
                'payment_method' => 'mobile_money',
                'amount'         => $due,
                'loan_product'   => $ab,
                'reference'      => 'CS-'.strtoupper(uniqid()),
                'source'         => $application,
                'mobile_number'  => $phone,
                'auto_verify'    => ! app(\App\Services\PayInService::class)->isLiveCollectionEnabled(),
            ]);

            if ($payment->status === 'processing') {
                return redirect()
                    ->route('site.borrower.payments.show', $payment);
            }
        }

        // When PayIn is live, fee is confirmed via webhook — only mark paid in dummy/instant mode.
        if (! app(\App\Services\PayInService::class)->isLiveCollectionEnabled() || $due <= 0) {
            $service->markFeePaid($application->fresh());
        }

        $state = $service->state($application->fresh());
        $flash = match ($state['status'] ?? '') {
            CollateralSecureService::STATUS_AWAITING_INSURANCE => [
                'title' => __('borrower.collateral_secure.linked_insurance_title'),
                'message' => __('borrower.collateral_secure.linked_insurance_body', [
                    'date' => data_get($state, 'insurance.expiry') ?: '—',
                ]),
                'tone' => 'warning',
            ],
            CollateralSecureService::STATUS_SECURED => [
                'title' => __('borrower.collateral_secure.linked_secured_title'),
                'message' => __('borrower.collateral_secure.linked_secured_body'),
                'tone' => 'success',
            ],
            default => null,
        };

        $redirect = redirect()
            ->route('site.borrower.application', $application)
            ->with('status', __('borrower.collateral_secure.fee_paid'));

        return $flash
            ? $redirect->with('collateral_secure_flash', array_merge($flash, [
                'confirm' => __('borrower.feedback.ok'),
            ]))
            : $redirect;
    }

    public function buyInsurance(Request $request, LoanApplication $application, CollateralSecureService $service): RedirectResponse
    {
        $customer = $this->customer();
        $state = $service->state($application);
        abort_unless(($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_INSURANCE, 422);

        $asset = CustomerAsset::query()->findOrFail((int) ($state['customer_asset_id'] ?? 0));
        abort_unless((int) $asset->customer_id === (int) $customer->id, 403);

        $data = $request->validate([
            'insured_value' => ['required', 'integer', 'min:100000'],
        ]);

        $insurance = app(\App\Services\CollateralInsurancePartnerService::class);
        $quote = $insurance->quote((int) $data['insured_value']);
        abort_unless($quote['premium'] > 0, 422);

        $payInLive = app(\App\Services\PayInService::class)->isLiveCollectionEnabled();
        app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => $quote['premium'],
            'reference' => 'INS-'.strtoupper(uniqid()),
            'source' => $application,
            'mobile_number' => $customer->phone,
            'auto_verify' => ! $payInLive,
        ]);

        $opened = $insurance->openCoverCase($application, $asset, (int) $data['insured_value'], $customer);
        if (! $payInLive) {
            $service->recordInsurancePurchase($application, [
                'insured_value' => $quote['insured_value'],
                'premium' => $quote['premium'],
                'rate_percent' => $quote['rate_percent'],
                'markup_percent' => $quote['markup_percent'],
                'partner_task_id' => $opened['task']?->id,
                'partner_id' => $opened['partner']?->id,
                'paid_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('site.borrower.application', $application)
            ->with('collateral_secure_flash', [
                'title' => __('borrower.collateral_secure.insurance_paid_title'),
                'message' => $payInLive
                    ? 'Confirm the insurance payment on your phone. We will open the insurer case after payment.'
                    : ($opened['task']
                        ? __('borrower.collateral_secure.insurance_paid_body_partner')
                        : __('borrower.collateral_secure.insurance_paid_body_manual')),
                'confirm' => __('borrower.feedback.ok'),
                'tone' => 'success',
            ]);
    }

    public function guarantorBuyInsurance(Request $request, CustomerGuarantor $customerGuarantor, CollateralSecureService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless(app(\App\Services\GuarantorAccessService::class)->canViewGuarantee($customer, $customerGuarantor), 404);

        $application = $customerGuarantor->application
            ?? LoanApplication::query()->find($customerGuarantor->loan_application_id);
        abort_unless($application, 404);

        $state = $service->state($application);
        abort_unless(($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_INSURANCE, 422);
        abort_unless(($state['source'] ?? '') === 'guarantor', 403);

        $asset = CustomerAsset::query()->findOrFail((int) ($state['customer_asset_id'] ?? 0));
        abort_unless((int) $asset->customer_id === (int) $customer->id, 403);

        $data = $request->validate([
            'insured_value' => ['required', 'integer', 'min:100000'],
        ]);

        $insurance = app(\App\Services\CollateralInsurancePartnerService::class);
        $quote = $insurance->quote((int) $data['insured_value']);
        abort_unless($quote['premium'] > 0, 422);

        app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => $quote['premium'],
            'reference' => 'INS-'.strtoupper(uniqid()),
            'source' => $application,
            'mobile_number' => $customer->phone,
            'auto_verify' => ! app(\App\Services\PayInService::class)->isLiveCollectionEnabled(),
        ]);

        $opened = $insurance->openCoverCase($application, $asset, (int) $data['insured_value'], $customer);
        if (! app(\App\Services\PayInService::class)->isLiveCollectionEnabled()) {
            $service->recordInsurancePurchase($application, [
                'insured_value' => $quote['insured_value'],
                'premium' => $quote['premium'],
                'rate_percent' => $quote['rate_percent'],
                'markup_percent' => $quote['markup_percent'],
                'partner_task_id' => $opened['task']?->id,
                'partner_id' => $opened['partner']?->id,
                'paid_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('site.borrower.guaranteed.show', $customerGuarantor)
            ->with('collateral_secure_flash', [
                'title' => __('borrower.collateral_secure.insurance_paid_title'),
                'message' => $opened['task']
                    ? __('borrower.collateral_secure.insurance_paid_body_partner')
                    : __('borrower.collateral_secure.insurance_paid_body_manual'),
                'confirm' => __('borrower.feedback.ok'),
                'tone' => 'success',
            ]);
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
