<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApplicationOfferService
{
    public const RECOMMEND_APPROVE = 'approve';

    public const RECOMMEND_COUNTER = 'counter';

    public const RECOMMEND_ASSET = 'asset_alternative';

    public function __construct(
        private readonly AffordabilityService $affordability,
        private readonly NotificationService $notifications,
        private readonly DisplayedRateService $rates,
    ) {}

    /** @return array{amount: float, tenure_months: int, installment: float} */
    public function maxCounterOffer(LoanApplication $application): array
    {
        $application->loadMissing(['customer', 'product']);
        $tenure = (int) ($application->requested_tenure_months ?? 12);
        $amount = $this->affordability->maxAffordablePrincipal($application, $tenure);

        $product = $application->product;
        if ($product) {
            $amount = min($amount, (float) $product->max_amount);
            $amount = max($amount, (float) $product->min_amount);
        }

        $amount = floor(max(0, $amount) / 1000) * 1000;
        $rate = $product
            ? $this->rates->displayedMonthlyRate($product, $amount)
            : 0.0;

        return [
            'amount'          => $amount,
            'tenure_months'   => $tenure,
            'installment'     => $this->affordability->estimateInstallment($amount, $rate, $tenure),
        ];
    }

    public function submitRecommendation(
        LoanApplication $application,
        User $user,
        string $type,
        ?float $recommendedAmount,
        ?int $tenureMonths,
        ?string $remarks,
        ?int $alternativeProductId = null,
    ): LoanApplication {
        if (! in_array($type, [self::RECOMMEND_APPROVE, self::RECOMMEND_COUNTER, self::RECOMMEND_ASSET], true)) {
            throw ValidationException::withMessages(['recommendation_type' => 'Invalid recommendation type.']);
        }

        $application->loadMissing(['customer', 'product']);

        if ($type === self::RECOMMEND_ASSET) {
            if (! app(UnderwritingSettingsService::class)->assetBackedAlternativeEnabled()) {
                throw ValidationException::withMessages([
                    'recommendation_type' => 'Asset-backed alternatives are disabled in underwriting settings.',
                ]);
            }

            $product = $alternativeProductId
                ? LoanProduct::find($alternativeProductId)
                : LoanProduct::where('code', 'AB')->where('is_active', true)->first();

            abort_unless($product, 422, 'Asset-backed product not configured.');

            $application->update([
                'recommendation_type'         => self::RECOMMEND_ASSET,
                'alternative_loan_product_id' => $product->id,
                'offer_status'                => 'pending_asset_conversion',
                'committee_recommendation'    => $remarks,
                'recommended_by'              => $user->id,
                'recommended_at'              => now(),
            ]);

            $this->notifyAssetAlternative($application->fresh(['customer', 'product']), $product);

            return $application->fresh();
        }

        $affordability = $this->affordability->evaluate($application);

        if ($type === self::RECOMMEND_APPROVE) {
            if ($affordability['verdict'] === 'fail') {
                $settings = app(UnderwritingSettingsService::class);
                $message = $settings->automaticRejectionEnabled()
                    ? 'Affordability failed — reject the application or return for documents.'
                    : 'Affordability failed — recommend a counter-offer or asset-backed alternative instead.';

                throw ValidationException::withMessages([
                    'recommendation_type' => $message,
                ]);
            }
            $recommendedAmount = (float) $application->requested_amount;
            $tenureMonths = (int) $application->requested_tenure_months;
        }

        if ($type === self::RECOMMEND_COUNTER) {
            if (! app(UnderwritingSettingsService::class)->counterOffersEnabled()) {
                throw ValidationException::withMessages([
                    'recommendation_type' => 'Counter-offers are disabled in underwriting settings.',
                ]);
            }
            $counter = $this->maxCounterOffer($application);
            $recommendedAmount = $recommendedAmount > 0 ? $recommendedAmount : $counter['amount'];
            $tenureMonths = $tenureMonths ?: $counter['tenure_months'];

            if ($recommendedAmount <= 0) {
                throw ValidationException::withMessages([
                    'recommended_amount' => 'Could not calculate a counter-offer amount from income data.',
                ]);
            }
        }

        $application->update([
            'recommendation_type'      => $type,
            'recommended_amount'       => $recommendedAmount,
            'committee_recommendation' => $remarks,
            'recommended_by'           => $user->id,
            'recommended_at'           => now(),
            'offer_status'             => null,
            'offered_amount'           => null,
            'offered_tenure_months'    => null,
        ]);

        return $application->fresh();
    }

    public function issueOffer(
        LoanApplication $application,
        User $user,
        float $offeredAmount,
        int $offeredTenure,
        ?string $remarks = null,
    ): LoanApplication {
        $application->loadMissing(['customer', 'product']);

        if (! in_array($application->recommendation_type, [self::RECOMMEND_COUNTER, self::RECOMMEND_APPROVE], true)) {
            throw ValidationException::withMessages(['action' => 'Issue a counter-offer only after a credit recommendation.']);
        }

        $product = $application->product;
        if ($product) {
            if ($offeredAmount < (float) $product->min_amount || $offeredAmount > (float) $product->max_amount) {
                throw ValidationException::withMessages([
                    'offered_amount' => 'Amount must be between '.format_money($product->min_amount).' and '.format_money($product->max_amount).'.',
                ]);
            }
        }

        $application->update([
            'offered_amount'           => $offeredAmount,
            'offered_tenure_months'    => $offeredTenure,
            'recommended_amount'       => $offeredAmount,
            'offer_status'             => 'pending_borrower',
            'offer_issued_at'          => now(),
            'committee_recommendation' => $remarks ?: $application->committee_recommendation,
            'status'                   => 'awaiting_offer',
        ]);

        $this->notifyOfferIssued($application->fresh(['customer', 'product']));

        return $application->fresh();
    }

    public function acceptOffer(LoanApplication $application, Customer $customer): LoanApplication
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless($application->offer_status === 'pending_borrower', 422);

        $application->update([
            'offer_status'        => 'accepted',
            'offer_responded_at'  => now(),
            'recommended_amount'  => $application->offered_amount ?? $application->recommended_amount,
            'requested_tenure_months' => $application->offered_tenure_months ?? $application->requested_tenure_months,
            'status'              => 'pre_approved',
        ]);

        return $application->fresh();
    }

    public function declineOffer(LoanApplication $application, Customer $customer): LoanApplication
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless($application->offer_status === 'pending_borrower', 422);

        $application->update([
            'offer_status'       => 'declined',
            'offer_responded_at' => now(),
            'status'             => 'withdrawn',
        ]);

        return $application->fresh();
    }

    public function effectiveAmount(LoanApplication $application): float
    {
        return (float) (
            $application->offered_amount
            ?? $application->recommended_amount
            ?? $application->requested_amount
        );
    }

    public function canFinalApprove(LoanApplication $application): bool
    {
        if ($application->offer_status === 'pending_borrower') {
            return false;
        }

        if ($this->pendingAssetConversion($application) || $application->offer_status === 'asset_conversion_fee_due') {
            return false;
        }

        if ($application->recommendation_type === self::RECOMMEND_COUNTER && $application->offer_status !== 'accepted') {
            return false;
        }

        if ($application->recommendation_type === self::RECOMMEND_ASSET) {
            return false;
        }

        return ($application->current_stage ?? '') === 'pre_approval';
    }

    public function pendingAssetConversion(LoanApplication $application): bool
    {
        return $application->recommendation_type === self::RECOMMEND_ASSET
            && $application->alternative_loan_product_id
            && in_array($application->offer_status, [null, 'pending_asset_conversion'], true);
    }

    public function needsConversionFee(LoanApplication $application): bool
    {
        return $application->offer_status === 'asset_conversion_fee_due'
            && $application->alternative_loan_product_id;
    }

    /** @return array{status: string, quote: array<string, mixed>} */
    public function acceptAssetConversion(LoanApplication $application, Customer $customer): array
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless($this->pendingAssetConversion($application), 422);

        $application->loadMissing(['alternativeProduct', 'product']);
        $quote = app(ApplicationFeeCreditService::class)->conversionQuote(
            $application,
            $application->alternativeProduct,
        );

        if (($quote['due'] ?? 0) > 0) {
            $application->update(['offer_status' => 'asset_conversion_fee_due']);

            return ['status' => 'fee_due', 'quote' => $quote];
        }

        $this->completeAssetConversion($application);

        return ['status' => 'converted', 'quote' => $quote];
    }

    public function completeAssetConversion(LoanApplication $application): LoanApplication
    {
        $application->loadMissing(['customer', 'alternativeProduct']);
        $newProduct = $application->alternativeProduct;
        abort_unless($newProduct, 422, 'Asset-backed product not configured.');

        $newFee = app(ApplicationFeeCreditService::class)->quotedFee($application->customer, $newProduct);
        $credit = app(ApplicationFeeCreditService::class)->paidCredit($application->customer, $application);
        $feeStatus = $credit >= $newFee ? 'paid' : ($application->application_fee_status ?? 'unpaid');

        $application->update([
            'loan_product_id'             => $newProduct->id,
            'application_fee_amount'      => $newFee,
            'application_fee_status'      => $feeStatus,
            'recommendation_type'       => null,
            'alternative_loan_product_id' => null,
            'offer_status'                => 'asset_conversion_accepted',
            'offer_responded_at'          => now(),
        ]);

        return $application->fresh(['product']);
    }

    public function declineAssetConversion(LoanApplication $application, Customer $customer): LoanApplication
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless(
            $this->pendingAssetConversion($application) || $this->needsConversionFee($application),
            422,
        );

        $application->update([
            'recommendation_type'       => null,
            'alternative_loan_product_id' => null,
            'offer_status'              => 'declined',
            'offer_responded_at'        => now(),
            'status'                    => 'withdrawn',
        ]);

        return $application->fresh();
    }

    private function notifyOfferIssued(LoanApplication $application): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $this->notifications->notifyInApp(
            $customer,
            __('borrower.offer.notify_message', [
                'reference' => $application->application_number,
                'requested' => format_money((float) $application->requested_amount),
                'offered'   => format_money((float) ($application->offered_amount ?? 0)),
            ]),
            'application',
            'loan_counter_offer',
            __('borrower.offer.notify_title'),
            route('site.borrower.application.offer', $application->id),
            __('borrower.offer.review_cta'),
        );
    }

    private function notifyAssetAlternative(LoanApplication $application, LoanProduct $product): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $applyUrl = route('site.borrower.application.asset-conversion', $application->id);

        $this->notifications->notifyInApp(
            $customer,
            __('borrower.offer.asset_alternative_message', [
                'reference' => $application->application_number,
                'product'   => $product->name,
            ]),
            'application',
            'asset_alternative_offer',
            __('borrower.offer.asset_alternative_title'),
            $applyUrl,
            __('borrower.offer.asset_alternative_cta'),
        );
    }
}
