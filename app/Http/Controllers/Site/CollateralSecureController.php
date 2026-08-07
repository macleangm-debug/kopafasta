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

            if (in_array($payment->status, ['processing', 'awaiting_payment'], true)) {
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
        if (($state['status'] ?? '') !== CollateralSecureService::STATUS_AWAITING_INSURANCE) {
            return back()->with('error', __('borrower.collateral_secure.saved'));
        }

        $asset = CustomerAsset::query()->findOrFail((int) ($state['customer_asset_id'] ?? 0));
        abort_unless((int) $asset->customer_id === (int) $customer->id, 403);

        $data = $request->validate([
            'insured_value' => ['required'],
        ]);
        $insuredValue = \App\Support\MoneyFormat::toInteger($data['insured_value']);
        if ($insuredValue <= 0) {
            return back()
                ->withInput()
                ->with('error', __('borrower.collateral_secure.insurance_value_required'));
        }

        try {
            return $this->resumeOrStartInsurancePremiumPayment(
                $customer,
                $application,
                $asset,
                $state,
                $insuredValue,
                route('site.borrower.application', $application),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: __('borrower.payments.aggregator_required'));
        }
    }

    public function guarantorBuyInsurance(Request $request, CustomerGuarantor $customerGuarantor, CollateralSecureService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless(app(\App\Services\GuarantorAccessService::class)->canViewGuarantee($customer, $customerGuarantor), 404);

        $application = $customerGuarantor->application
            ?? LoanApplication::query()->find($customerGuarantor->loan_application_id);
        abort_unless($application, 404);

        $state = $service->state($application);
        if (($state['status'] ?? '') !== CollateralSecureService::STATUS_AWAITING_INSURANCE) {
            return back()->with('error', __('borrower.collateral_secure.saved'));
        }
        abort_unless(($state['source'] ?? '') === 'guarantor', 403);

        $asset = CustomerAsset::query()->findOrFail((int) ($state['customer_asset_id'] ?? 0));
        abort_unless((int) $asset->customer_id === (int) $customer->id, 403);

        $data = $request->validate([
            'insured_value' => ['required'],
        ]);
        $insuredValue = \App\Support\MoneyFormat::toInteger($data['insured_value']);
        if ($insuredValue <= 0) {
            return back()
                ->withInput()
                ->with('error', __('borrower.collateral_secure.insurance_value_required'));
        }

        try {
            return $this->resumeOrStartInsurancePremiumPayment(
                $customer,
                $application,
                $asset,
                $state,
                $insuredValue,
                route('site.borrower.guaranteed.show', $customerGuarantor),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', $e->getMessage() ?: __('borrower.payments.aggregator_required'));
        }
    }

    /**
     * Resume only when the pending premium matches the cover amount just entered.
     * Otherwise supersede the old payment so the gate shows 3.5% of the new value.
     */
    private function resumeOrStartInsurancePremiumPayment(
        $customer,
        LoanApplication $application,
        CustomerAsset $asset,
        array $state,
        int $insuredValue,
        string $returnUrl,
    ): RedirectResponse {
        $quote = app(\App\Services\CollateralInsurancePartnerService::class)->quote($insuredValue);
        if ($quote['premium'] <= 0) {
            return back()
                ->withInput()
                ->with('error', __('borrower.collateral_secure.insurance_value_required'));
        }

        $existingPaymentId = (int) data_get($state, 'insurance_purchase.payment_id', 0);
        if ($existingPaymentId > 0 && data_get($state, 'insurance_purchase.status') === 'payment_pending') {
            $existing = \App\Models\CustomerPayment::query()
                ->whereKey($existingPaymentId)
                ->where('customer_id', $customer->id)
                ->where('payment_type', 'insurance_premium')
                ->first();

            if ($existing && ! $existing->isVerified() && in_array($existing->status, ['awaiting_payment', 'processing'], true)) {
                $storedInsured = (int) data_get($existing->provider_meta, 'collateral_insurance.insured_value')
                    ?: (int) data_get($state, 'insurance_purchase.insured_value', 0);
                $sameQuote = $storedInsured === $insuredValue
                    && (int) $existing->amount === (int) $quote['premium'];

                if ($sameQuote) {
                    return $this->redirectToInsurancePaymentGate($existing, $customer->phone);
                }

                try {
                    app(CustomerPaymentService::class)->reject(
                        $existing,
                        null,
                        'Superseded by updated insurance cover amount'
                    );
                } catch (\Throwable) {
                    $existing->update(['status' => 'rejected']);
                }
            }
        }

        return $this->startInsurancePremiumPayment(
            $customer,
            $application,
            $asset,
            $insuredValue,
            $returnUrl,
        );
    }

    private function startInsurancePremiumPayment(
        $customer,
        LoanApplication $application,
        CustomerAsset $asset,
        int $insuredValue,
        string $returnUrl,
    ): RedirectResponse {
        $insurance = app(\App\Services\CollateralInsurancePartnerService::class);
        $quote = $insurance->quote($insuredValue);
        if ($quote['premium'] <= 0) {
            return back()->with('error', __('borrower.collateral_secure.insurance_value_required'));
        }

        $phone = $customer->phone;
        if (! filled($phone)) {
            return back()->with('error', __('borrower.payments.mobile_number_required'));
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => $quote['premium'],
            'reference' => 'INS-'.strtoupper(uniqid()),
            'source' => $application,
            'mobile_number' => $phone,
            'auto_verify' => false,
            'description' => 'Collateral insurance premium',
        ]);

        $meta = (array) ($payment->provider_meta ?? []);
        $meta['collateral_insurance'] = [
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'insured_value' => $quote['insured_value'],
            'premium' => $quote['premium'],
            'rate_percent' => $quote['rate_percent'],
            'markup_percent' => $quote['markup_percent'],
            'payer_customer_id' => $customer->id,
            'return_url' => $returnUrl,
        ];
        $payment->update(['provider_meta' => $meta]);

        app(\App\Services\CollateralSecureService::class)->recordInsurancePurchase($application, [
            'insured_value' => $quote['insured_value'],
            'premium' => $quote['premium'],
            'rate_percent' => $quote['rate_percent'],
            'markup_percent' => $quote['markup_percent'],
            'payment_id' => $payment->id,
            'payment_reference' => $payment->reference,
            'status' => 'payment_pending',
        ]);

        // Open shared PSP gate (step 1); Pay now starts collection.
        return $this->redirectToInsurancePaymentGate($payment->fresh(), $phone);
    }

    /**
     * Insurance: always open shared payments.show on the PSP gate (step 1).
     * If a push is already in flight, reset to awaiting so Insure It does not skip to waiting.
     * Pay now on the gate starts collection → waiting (step 2).
     */
    private function redirectToInsurancePaymentGate(
        \App\Models\CustomerPayment $payment,
        ?string $phone,
    ): RedirectResponse {
        $payments = app(CustomerPaymentService::class);

        if ($payment->isPayInWaiting() || $payment->status === 'processing') {
            try {
                $payment = $payments->returnToPaymentGate($payment);
            } catch (\Throwable) {
                // Fall through to show whichever state we have.
            }
        }

        // Gate default is always the member's registered phone.
        if (filled($phone) && $payment->awaitsCollection()) {
            $normalized = \App\Support\PhoneNumber::normalizeForCountry(
                (string) $phone,
                $payment->customer?->country_code ?? null,
            );
            if (filled($normalized)) {
                $payment->update(['mobile_number' => $normalized]);
            }
        }

        return redirect()->route('site.borrower.payments.show', $payment->fresh());
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
