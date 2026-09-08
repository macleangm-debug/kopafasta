<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use Illuminate\Http\Request;

/**
 * Canonical post-payment / resume destination for the apply wizard.
 *
 * Separates fee satisfaction from wizard position. One resolver feeds
 * return_url, back_url, ApplyController::show, and client restore.
 */
class ApplyFeeResumeService
{
    public const INTENT_PAID = 'paid';

    public const INTENT_CANCEL = 'cancel';

    public const INTENT_EDIT = 'edit';

    public const INTENT_PLAIN = 'plain';

    public const SETUP_KEYS = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'group_members', 'application_fee'];

    /**
     * @param  array<string, mixed>|null  $draftPayload
     * @return array{
     *     intent: string,
     *     fee_satisfied: bool,
     *     step_key: string,
     *     asset_substep: int|null,
     *     reason: string
     * }
     */
    public function resolve(
        Customer $customer,
        LoanProduct $product,
        ?array $draftPayload = null,
        ?string $intent = null,
        ?string $requestedStepKey = null,
        bool $editHop = false,
    ): array {
        $fees = app(ApplicationFeePaymentService::class);
        $drafts = app(LoanApplicationDraftService::class);
        $payload = $fees->canonicalDraftPayloadForFee($customer, $product, $draftPayload);
        $feeSatisfied = $fees->isSatisfiedFor($customer, $product, $payload);
        $draftStepKey = (string) ($payload['step_key'] ?? '');
        $explicitPaid = $intent === self::INTENT_PAID;

        $intent = $this->normalizeIntent($intent, $editHop, $feeSatisfied, $requestedStepKey, $draftStepKey);

        if ($intent === self::INTENT_PAID) {
            $next = $fees->nextStepAfterApplicationFee($customer, $product, $payload);
            if (in_array($next, self::SETUP_KEYS, true)) {
                $next = 'review';
            }

            // Explicit fee_return=paid always advances to the configured next step.
            // Plain resume keeps an already-saved post-fee step if present.
            if (! $explicitPaid
                && $draftStepKey !== ''
                && ! in_array($draftStepKey, self::SETUP_KEYS, true)) {
                $next = $draftStepKey;
            }

            return [
                'intent' => self::INTENT_PAID,
                'fee_satisfied' => true,
                'step_key' => $next,
                'asset_substep' => $this->feeOriginAssetSubstep($product, $payload),
                'reason' => 'verified_fee_next_step',
            ];
        }

        if ($intent === self::INTENT_EDIT && filled($requestedStepKey)) {
            return [
                'intent' => self::INTENT_EDIT,
                'fee_satisfied' => $feeSatisfied,
                'step_key' => (string) $requestedStepKey,
                'asset_substep' => $this->feeOriginAssetSubstep($product, $payload),
                'reason' => 'edit_hop',
            ];
        }

        // Cancel / unpaid / Back-to-Quote: fee-origin surface with completed pre-Quote preserved.
        $origin = $drafts->feeOriginStepKeyForProduct($product);
        if ($feeSatisfied && in_array($draftStepKey, ['quote', 'asset_details', 'asset_tenure'], true)) {
            $origin = $draftStepKey;
        }

        return [
            'intent' => $intent === self::INTENT_CANCEL ? self::INTENT_CANCEL : self::INTENT_PLAIN,
            'fee_satisfied' => $feeSatisfied,
            'step_key' => $origin,
            'asset_substep' => $this->feeOriginAssetSubstep($product, $payload),
            'reason' => $feeSatisfied ? 'cancel_or_back_to_quote' : 'fee_origin',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $draftPayload
     */
    public function resolveFromRequest(
        Request $request,
        Customer $customer,
        LoanProduct $product,
        ?array $draftPayload = null,
    ): array {
        $feeReturn = strtolower(trim((string) $request->query('fee_return', '')));
        $intent = match ($feeReturn) {
            'paid', 'success' => self::INTENT_PAID,
            'cancel', 'failed', 'back' => self::INTENT_CANCEL,
            default => null,
        };

        $editHop = $request->filled('return_to')
            || in_array((string) $request->query('return_to'), ['profile', 'quote', 'asset_details', 'guarantor'], true);

        return $this->resolve(
            $customer,
            $product,
            $draftPayload,
            $intent,
            $request->filled('step_key') ? (string) $request->query('step_key') : null,
            $editHop,
        );
    }

    /**
     * @param  array<string, mixed>|null  $draftPayload
     */
    public function resumeUrlAfterFee(
        Customer $customer,
        LoanProduct $product,
        ?array $draftPayload = null,
        ?string $stepKey = null,
        ?int $reservationId = null,
    ): string {
        $resolved = $this->resolve($customer, $product, $draftPayload, self::INTENT_PAID, $stepKey);
        $params = [
            'product' => $product->id,
            'resume' => 1,
            'step_key' => $resolved['step_key'],
            'fee_return' => self::INTENT_PAID,
        ];
        if ($reservationId) {
            $params['reservation'] = $reservationId;
        }

        return route('site.borrower.apply', $params);
    }

    /**
     * Cancel / failed payment return — fee-origin step, never rewind completed pre-Quote.
     *
     * @param  array<string, mixed>|null  $draftPayload
     */
    public function cancelResumeUrl(
        Customer $customer,
        LoanProduct $product,
        ?array $draftPayload = null,
        ?int $reservationId = null,
    ): string {
        $resolved = $this->resolve($customer, $product, $draftPayload, self::INTENT_CANCEL);
        $params = [
            'product' => $product->id,
            'resume' => 1,
            'step_key' => $resolved['step_key'],
            'fee_return' => self::INTENT_CANCEL,
        ];
        if ($reservationId) {
            $params['reservation'] = $reservationId;
        }
        if ($resolved['asset_substep']) {
            $params['asset_substep'] = $resolved['asset_substep'];
        }

        return route('site.borrower.apply', $params);
    }

    /**
     * Apply resolved destination onto wizard savedDraft payload (same step for progress + body).
     *
     * @param  array<string, mixed>  $savedDraft
     * @param  array{step_key: string, asset_substep: int|null, fee_satisfied: bool, intent: string, reason: string}  $resolved
     * @return array<string, mixed>
     */
    public function applyToSavedDraft(array $savedDraft, array $resolved, Customer $customer, LoanProduct $product): array
    {
        $stepKey = (string) $resolved['step_key'];
        $amount = (float) (
            $savedDraft['form']['requested_amount']
            ?? $savedDraft['form']['amount']
            ?? 0
        );
        $plan = collect(app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product, $amount))
            ->reject(fn (array $step) => ($step['key'] ?? '') === 'product')
            ->values();
        $index = $plan->search(fn (array $step) => ($step['key'] ?? null) === $stepKey);
        if ($index === false) {
            $index = 0;
        }

        $savedDraft['step_key'] = $stepKey;
        $savedDraft['step'] = (int) $index;
        $savedDraft['furthest_step'] = (int) $index;
        $savedDraft['resume_target'] = [
            'phase' => 'application',
            'step_key' => $stepKey,
            'step' => (int) $index,
            'furthest_step' => (int) $index,
            'asset_substep' => $resolved['asset_substep'] ?? ($savedDraft['asset_substep'] ?? null),
            'draft_reference' => $savedDraft['draft_reference'] ?? null,
            'reason' => $resolved['reason'] ?? null,
            'fee_satisfied' => (bool) ($resolved['fee_satisfied'] ?? false),
            'intent' => $resolved['intent'] ?? null,
            // Single object progress + body must consume — never diverge.
            'progress' => [
                'step_key' => $stepKey,
                'step' => (int) $index,
                'furthest_step' => (int) $index,
            ],
        ];
        if (! empty($resolved['asset_substep'])) {
            $savedDraft['asset_substep'] = (int) $resolved['asset_substep'];
            $form = is_array($savedDraft['form'] ?? null) ? $savedDraft['form'] : [];
            $form['asset_substep'] = (int) $resolved['asset_substep'];
            $savedDraft['form'] = $form;
        }

        return $savedDraft;
    }

    public function reservationIdFromDraft(?LoanApplicationDraft $draft): ?int
    {
        if (! $draft) {
            return null;
        }
        $id = (int) ($draft->asset_reservation_id
            ?? data_get($draft->payload, 'asset_reservation_id')
            ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function feeOriginAssetSubstep(LoanProduct $product, array $payload): ?int
    {
        if (strtoupper((string) $product->code) !== 'AB') {
            return null;
        }

        $saved = (int) (
            $payload['asset_substep']
            ?? data_get($payload, 'form.asset_substep')
            ?? 0
        );
        if ($saved >= 1 && $saved <= 3) {
            return $saved;
        }

        // Collateral + amount already completed → stay on the Quote-like substep.
        $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
        $assetIds = $form['customer_asset_ids'] ?? null;
        if (! is_array($assetIds) || $assetIds === []) {
            $single = (int) ($form['customer_asset_id'] ?? 0);
            $assetIds = $single > 0 ? [$single] : [];
        }
        $amount = (float) ($form['requested_amount'] ?? 0);
        $tenure = (int) ($form['requested_tenure_months'] ?? 0);
        if ($assetIds !== [] && $amount >= 1000 && $tenure >= 1) {
            return 3;
        }
        if ($assetIds !== [] && $amount >= 1000) {
            return 2;
        }
        if ($assetIds !== []) {
            return 1;
        }

        return 1;
    }

    private function normalizeIntent(
        ?string $intent,
        bool $editHop,
        bool $feeSatisfied,
        ?string $requestedStepKey,
        string $draftStepKey = '',
    ): string {
        $intent = strtolower(trim((string) $intent));
        if (in_array($intent, [self::INTENT_PAID, self::INTENT_CANCEL, self::INTENT_EDIT, self::INTENT_PLAIN], true)) {
            return $intent;
        }
        if ($editHop) {
            return self::INTENT_EDIT;
        }
        if (! $feeSatisfied) {
            return self::INTENT_PLAIN;
        }

        // Fee paid + requested/setup URL without fee_return=paid → Back to Quote stays.
        if (filled($requestedStepKey) && in_array($requestedStepKey, self::SETUP_KEYS, true)) {
            return self::INTENT_CANCEL;
        }
        if (filled($requestedStepKey) && ! in_array($requestedStepKey, self::SETUP_KEYS, true)) {
            return self::INTENT_PAID;
        }
        // Plain resume: keep Back-to-Quote if draft still on setup; else resume post-fee.
        if ($draftStepKey !== '' && in_array($draftStepKey, self::SETUP_KEYS, true)) {
            return self::INTENT_CANCEL;
        }

        return self::INTENT_PAID;
    }
}
