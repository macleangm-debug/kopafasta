<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\Setting;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPaymentService
{
    public function __construct(
        private PaymentAccountService $accounts,
        private LedgerService $ledger,
    ) {}

    /**
     * Fee types that may accept promo / affiliate / referral discounts at initiate.
     *
     * @return list<string>
     */
    public static function discountablePaymentTypes(): array
    {
        return [
            'registration_fee',
            'application_fee',
            'post_approval_fee',
            'valuation_fee',
            'asset_reservation_fee',
        ];
    }

    public static function supportsCodeDiscounts(string $paymentType): bool
    {
        return in_array($paymentType, self::discountablePaymentTypes(), true);
    }

    /**
     * Map provider / PayIn English messages to the active locale.
     */
    public static function localizeProviderMessage(?string $message, ?string $phone = null): string
    {
        $raw = trim((string) $message);
        $phone = filled($phone) ? preg_replace('/\D+/', '', (string) $phone) : null;

        if ($raw === '') {
            return filled($phone)
                ? __('borrower.payments.aggregator_rejected_phone', ['phone' => $phone])
                : __('borrower.payments.aggregator_rejected');
        }

        $hay = mb_strtolower($raw);
        if (str_contains($hay, 'detect operator')
            || str_contains($hay, 'operator from phone')
            || str_contains($hay, 'kutambua mtandao')) {
            return filled($phone)
                ? __('borrower.payment_waiting.operator_error_phone', ['phone' => $phone])
                : __('borrower.payment_waiting.operator_error');
        }

        if (str_contains($hay, 'not configured')
            || str_contains($hay, 'aggregator must be')
            || str_contains($hay, 'add api keys')) {
            return __('borrower.payments.aggregator_required');
        }

        if (str_contains($hay, 'mobile number') && str_contains($hay, 'required')) {
            return __('borrower.payments.mobile_number_required');
        }

        // Avoid showing raw English API copy when the borrower locale is not English.
        if (app()->getLocale() !== 'en' && preg_match('/[A-Za-z]{6,}/', $raw)) {
            return filled($phone)
                ? __('borrower.payments.aggregator_rejected_phone', ['phone' => $phone])
                : __('borrower.payments.aggregator_rejected');
        }

        if (filled($phone) && ! str_contains($raw, $phone)) {
            return trim($raw.' ('.$phone.')');
        }

        return $raw;
    }

    public function generateReference(): string
    {
        do {
            $ref = 'PAY-'.strtoupper(Str::random(6));
        } while (CustomerPayment::where('reference', $ref)->exists());

        return $ref;
    }

    /**
     * @param  array{
     *   payment_type: string,
     *   payment_method: string,
     *   amount: float|int,
     *   customer: Customer,
     *   loan?: ?Loan,
     *   loan_product?: ?LoanProduct,
     *   mobile_number?: ?string,
     *   payment_date?: ?string,
     *   proof?: ?UploadedFile,
     *   source?: ?Model,
     *   currency?: string,
     *   auto_verify?: bool,
     *   reference?: string,
     * }  $data
     */
    public function create(array $data): CustomerPayment
    {
        return DB::transaction(function () use ($data) {
            $customer = $data['customer'];
            $loan = $data['loan'] ?? null;
            $product = $data['loan_product'] ?? $loan?->product ?? null;
            $method = $data['payment_method'];
            $type = $data['payment_type'];
            $amount = round((float) $data['amount'], 2);

            if (filled($data['mobile_number'] ?? null)) {
                $data['mobile_number'] = PhoneNumber::normalizeForCountry(
                    (string) $data['mobile_number'],
                    $customer->country_code ?? null,
                );
            }

            $resolved = $this->accounts->resolve($type, $method, $product);
            $reference = $data['reference'] ?? $this->generateReference();
            $autoVerify = (bool) ($data['auto_verify'] ?? false);
            $isBank = $method === 'bank_transfer';
            $payIn = app(PayInService::class);
            $liveGateway = ! payment_gateway_is_dummy();
            $usePayIn = false;

            if ($method === 'mobile_money') {
                if ($liveGateway) {
                    // Live mode: never accept mobile money without the assigned aggregator.
                    if (! $payIn->isLiveCollectionEnabled()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'payment_method' => [__('borrower.payments.aggregator_required')],
                        ]);
                    }
                    if (! filled($data['mobile_number'] ?? null)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'mobile_number' => [__('borrower.payments.mobile_number_required')],
                        ]);
                    }
                    $usePayIn = true;
                    $autoVerify = false;
                } elseif ($payIn->isConfigured() && filled($data['mobile_number'] ?? null)) {
                    // Dummy / sandbox: still push through PayIn so the borrower sees the payment gate.
                    // Insurance premiums always require aggregator confirmation before the partner case opens.
                    $usePayIn = true;
                    $autoVerify = false;
                }
            }

            // Collateral insurance must never skip the aggregator payment gate.
            if ($type === 'insurance_premium') {
                $autoVerify = false;
                if ($method === 'mobile_money' && ! $usePayIn) {
                    if (! filled($data['mobile_number'] ?? null)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'mobile_number' => [__('borrower.payments.mobile_number_required')],
                        ]);
                    }
                    if (! $payIn->isConfigured()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'payment_method' => [__('borrower.payments.aggregator_required')],
                        ]);
                    }
                    $usePayIn = true;
                }
            }

            if ($usePayIn) {
                $autoVerify = false;
            }

            // Never mark live mobile-money as paid/verified without aggregator confirmation.
            // PayIn collections start only when the borrower taps Pay now on the gate.
            $status = $autoVerify
                ? 'verified'
                : ($usePayIn ? 'awaiting_payment' : ($isBank ? 'pending_verification' : ($liveGateway && $method === 'mobile_money' ? 'awaiting_payment' : 'paid')));

            $proofPath = null;
            $proofName = null;
            if (! empty($data['proof']) && $data['proof'] instanceof UploadedFile) {
                $proofPath = $data['proof']->store('payment-proofs/'.$customer->id, 'public');
                $proofName = $data['proof']->getClientOriginalName();
            }

            $instructions = $resolved['instructions'];
            if ($isBank && $resolved['bank_account']) {
                $details = $this->accounts->bankTransferDetails($resolved['bank_account'], $reference);
                $instructions = trim(($instructions ?? '')."\n".'Use reference: '.$reference);
            }
            if ($usePayIn) {
                $instructions = trim(($instructions ?? '')."\n".__('borrower.payment_waiting.gate_instructions'));
            }

            $payment = CustomerPayment::create([
                'reference'               => $reference,
                'customer_id'             => $customer->id,
                'payment_type'            => $type,
                'payment_method'          => $method,
                'amount'                  => $amount,
                'currency'                => $data['currency'] ?? 'TZS',
                'status'                  => $status,
                'bank_account_id'         => $resolved['bank_account']?->id,
                'mobile_money_account_id' => $resolved['mobile_money_account']?->id,
                'mobile_number'           => $data['mobile_number'] ?? null,
                'payment_instructions'    => $instructions,
                'proof_path'              => $proofPath,
                'proof_original_name'     => $proofName,
                'paid_at'                 => $autoVerify || (! $isBank && ! $usePayIn && ! $liveGateway) ? now() : null,
                'payment_date'            => $data['payment_date'] ?? ($isBank ? now()->toDateString() : null),
                'source_type'             => isset($data['source']) ? $data['source']::class : null,
                'source_id'               => ($data['source'] ?? null)?->getKey(),
                'loan_id'                 => $loan?->id,
                'loan_product_id'         => $product?->id,
                'created_by'              => auth()->id(),
                'provider_meta'           => $usePayIn ? [
                    'awaiting_collection' => true,
                    'description' => $data['description'] ?? null,
                    'operator' => $data['operator'] ?? null,
                ] : null,
            ]);

            $this->attachMarkupFeeSplitMeta($payment);

            // Never instant-finalize insurance — partner case opens only after verified payment.
            if ($type === 'insurance_premium') {
                return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
            }

            if ($autoVerify || (! $isBank && ! $usePayIn && ! $liveGateway)) {
                $this->finalizePayment($payment);
            } elseif ($isBank) {
                $this->notifyBankStatus($payment->fresh(['customer']), 'bank_payment_pending');
            }

            return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
        });
    }

    /**
     * Start the PayIn USSD / STK push after the borrower taps Pay now on the payment gate.
     */
    public function initiateCollection(CustomerPayment $payment, ?string $mobileNumber = null, ?string $operator = null): CustomerPayment
    {
        $payment = $payment->fresh(['customer']);

        if ($payment->payment_method !== 'mobile_money') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        if (! in_array($payment->status, ['awaiting_payment', 'processing'], true)
            || ($payment->status === 'processing' && filled($payment->provider_ref))) {
            if ($payment->status === 'processing' && filled($payment->provider_ref)) {
                return $payment;
            }
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => [__('borrower.payment_waiting.cannot_retry')],
            ]);
        }

        $phone = filled($mobileNumber) ? $mobileNumber : null;
        if (filled($phone)) {
            $phone = PhoneNumber::normalizeForCountry(
                (string) $phone,
                $payment->customer?->country_code ?? null,
            );
        }

        if (! filled($phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mobile_number' => [__('borrower.payments.mobile_number_required')],
            ]);
        }

        $payIn = app(PayInService::class);
        if (! $payIn->isConfigured()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        // Persist and send only the number entered on the gate (not a stale account/meta value).
        $pspPhone = $payIn->normalizePhone((string) $phone);
        $meta = (array) ($payment->provider_meta ?? []);
        $description = $meta['description'] ?? null;
        unset($meta['operator'], $meta['last_collect_error'], $meta['last_collect_error_at']);
        $meta['phone'] = $pspPhone;
        $meta['attempted_phone'] = $pspPhone;
        $payment->update([
            'mobile_number' => $pspPhone,
            'provider_meta' => $meta,
        ]);

        try {
            // Never reuse a previous operator — PayIn detects from this MSISDN.
            $collection = $payIn->collect(
                $pspPhone,
                (float) $payment->amount,
                (string) $payment->reference,
                $this->payInDescription($payment->payment_type, (string) $payment->reference, is_string($description) ? $description : null),
                null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?: __('borrower.payments.aggregator_rejected');
            $localized = self::localizeProviderMessage($message, $pspPhone);
            $meta['awaiting_collection'] = true;
            $meta['phone'] = $pspPhone;
            $meta['attempted_phone'] = $pspPhone;
            $meta['last_collect_error'] = $localized;
            $meta['last_collect_error_at'] = now()->toIso8601String();
            $payment->update([
                'status' => 'awaiting_payment',
                'mobile_number' => $pspPhone,
                'provider' => null,
                'provider_ref' => null,
                'provider_meta' => $meta,
            ]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_phone' => [$localized],
            ]);
        }

        $remoteStatus = strtolower((string) ($collection['status'] ?? ''));
        $meta = array_merge($meta, [
            'awaiting_collection' => false,
            'initiated_at' => now()->toIso8601String(),
            'operator' => $collection['operator'],
            'message' => $collection['message'],
            'phone' => $pspPhone,
            'attempted_phone' => $pspPhone,
            'raw' => $collection['raw'],
        ]);
        unset($meta['last_collect_error'], $meta['last_collect_error_at']);

        if (in_array($remoteStatus, ['failed', 'cancelled', 'canceled', 'expired', 'rejected'], true)) {
            $message = self::localizeProviderMessage(
                $collection['message'] ?: __('borrower.payments.aggregator_rejected'),
                $pspPhone,
            );
            $meta['awaiting_collection'] = true;
            $meta['last_collect_error'] = $message;
            $meta['last_collect_error_at'] = now()->toIso8601String();
            $payment->update([
                'status' => 'awaiting_payment',
                'mobile_number' => $pspPhone,
                'provider' => 'payin',
                'provider_ref' => $collection['request_ref'],
                'provider_meta' => $meta,
                'verification_notes' => trim(($payment->verification_notes ?? '')."\nPayIn collection status: {$remoteStatus}"),
                'payment_instructions' => trim(($payment->payment_instructions ?? '')."\n".$message),
            ]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_phone' => [$message],
            ]);
        }

        $payment->update([
            'status' => 'processing',
            'provider' => 'payin',
            'provider_ref' => $collection['request_ref'],
            'provider_meta' => $meta,
            'payment_instructions' => trim(($payment->payment_instructions ?? '')."\n".$collection['message']),
        ]);

        return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    /**
     * Send the borrower back to the shared PSP gate (MM | bank | Pay now)
     * after they abandon or retry from the waiting / help card.
     */
    public function returnToPaymentGate(CustomerPayment $payment): CustomerPayment
    {
        $payment = $payment->fresh(['customer']);

        abort_unless($payment->payment_method === 'mobile_money', 422);
        abort_unless(in_array($payment->status, ['awaiting_payment', 'processing'], true), 422);

        if ($payment->isVerified()) {
            return $payment;
        }

        $meta = (array) ($payment->provider_meta ?? []);
        $meta['awaiting_collection'] = true;
        $meta['returned_to_gate_at'] = now()->toIso8601String();
        unset($meta['last_collect_error'], $meta['last_collect_error_at'], $meta['initiated_at']);

        // Keep the last entered / attempted MSISDN so Insure It can push that number again.
        $payment->update([
            'status' => 'awaiting_payment',
            'provider' => null,
            'provider_ref' => null,
            'provider_meta' => $meta,
        ]);

        return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    /**
     * Switch a mobile-money gate payment to bank transfer before collection starts.
     */
    public function switchToBankTransfer(CustomerPayment $payment): CustomerPayment
    {
        $payment = $payment->fresh(['customer']);
        abort_unless($payment->status === 'awaiting_payment', 422);
        abort_unless($payment->payment_method === 'mobile_money', 422);

        $product = $payment->loan_product_id
            ? \App\Models\LoanProduct::query()->find($payment->loan_product_id)
            : ($payment->loan_id ? $payment->loan?->product : null);
        $resolved = $this->accounts->resolve($payment->payment_type, 'bank_transfer', $product);
        $bankAccount = $resolved['bank_account'] ?? $this->accounts->fallbackBankAccount();
        abort_unless($bankAccount, 422, __('borrower.payment_waiting.bank_unavailable'));

        $details = $this->accounts->bankTransferDetails($bankAccount, $payment->reference);
        $instructions = trim(($resolved['instructions'] ?? '')."\n".'Use reference: '.$payment->reference);

        $payment->update([
            'payment_method' => 'bank_transfer',
            'status' => 'pending_verification',
            'bank_account_id' => $bankAccount->id,
            'mobile_money_account_id' => null,
            'provider' => null,
            'provider_ref' => null,
            'provider_meta' => array_merge((array) ($payment->provider_meta ?? []), [
                'switched_from' => 'mobile_money',
                'switched_at' => now()->toIso8601String(),
            ]),
            'payment_instructions' => $instructions,
            'payment_date' => now()->toDateString(),
        ]);

        $this->notifyBankStatus($payment->fresh(['customer']), 'bank_payment_pending');

        return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    /**
     * Sync a processing PayIn payment from the provider status API (webhook backup).
     */
    public function refreshFromProvider(CustomerPayment $payment): CustomerPayment
    {
        $payment = $payment->fresh();
        if ($payment->status !== 'processing'
            || $payment->provider !== 'payin'
            || ! filled($payment->provider_ref)
        ) {
            return $payment;
        }

        $result = app(PayInService::class)->status((string) $payment->provider_ref);
        $remote = strtolower((string) ($result['status'] ?? ''));

        $meta = array_merge((array) ($payment->provider_meta ?? []), [
            'last_poll_at' => now()->toIso8601String(),
            'last_poll_status' => $remote ?: null,
            'last_poll_message' => $result['message'] ?? null,
        ]);
        $payment->update(['provider_meta' => $meta]);
        $payment = $payment->fresh();

        if (in_array($remote, ['completed', 'success', 'paid'], true)) {
            try {
                return $this->verify($payment, null, 'PayIn status poll: '.$remote);
            } catch (\InvalidArgumentException) {
                return $payment->fresh();
            }
        }

        if (in_array($remote, ['failed', 'cancelled', 'canceled', 'expired', 'rejected'], true)) {
            try {
                return $this->reject($payment, null, 'PayIn status poll: '.$remote);
            } catch (\InvalidArgumentException) {
                return $payment->fresh();
            }
        }

        return $payment;
    }

    /** Where the borrower should go after a successful live payment. */
    public function successRedirectUrl(CustomerPayment $payment, ?string $fallback = null): string
    {
        if ($payment->payment_type === 'insurance_premium') {
            $return = data_get($payment->provider_meta, 'collateral_insurance.return_url');
            if (filled($return)) {
                return (string) $return;
            }
        }

        return match ($payment->payment_type) {
            'registration_fee' => route('site.borrower.dashboard'),
            'application_fee', 'valuation_fee' => $this->resolveLoanApplicationSource($payment)
                ? route('site.borrower.application', $this->resolveLoanApplicationSource($payment))
                : route('site.borrower.apply'),
            'loan_repayment' => $payment->loan_id
                ? route('site.borrower.loans.show', $payment->loan_id)
                : route('site.borrower.loans'),
            default => $fallback ?: route('site.borrower.payments.show', $payment),
        };
    }

    /**
     * Success celebration copy for the waiting / confirmation UI — keyed by payment type.
     *
     * @return array{title: string, message: string}
     */
    public function celebrationCopy(CustomerPayment $payment): array
    {
        return match ($payment->payment_type) {
            'registration_fee' => [
                'title' => __('borrower.celebration.membership_title'),
                'message' => __('borrower.celebration.membership'),
            ],
            'application_fee', 'asset_reservation_fee', 'valuation_fee' => [
                'title' => __('borrower.celebration.application_fee_title'),
                'message' => __('borrower.celebration.application_fee'),
            ],
            'post_approval_fee' => [
                'title' => __('borrower.celebration.post_approval_fee_title'),
                'message' => __('borrower.celebration.post_approval_fee'),
            ],
            'loan_repayment' => $this->repaymentCelebrationCopy($payment),
            'insurance_premium' => [
                'title' => __('borrower.collateral_secure.insurance_paid_title'),
                'message' => __('borrower.collateral_secure.insurance_paid_body_partner'),
            ],
            'asset_deposit' => [
                'title' => __('borrower.celebration.deposit_title'),
                'message' => __('borrower.celebration.deposit'),
            ],
            'penalty_payment' => [
                'title' => __('borrower.celebration.penalty_title'),
                'message' => __('borrower.celebration.penalty'),
            ],
            default => [
                'title' => __('borrower.celebration.payment_title'),
                'message' => __('borrower.celebration.payment'),
            ],
        };
    }

    /** @return array{title: string, message: string} */
    private function repaymentCelebrationCopy(CustomerPayment $payment): array
    {
        $loan = $payment->loan_id
            ? ($payment->relationLoaded('loan') ? $payment->loan : $payment->loan()->first())
            : null;
        $remaining = $loan ? (float) ($loan->outstanding_balance ?? 0) : null;

        $streakCopy = $this->onTimeStreakCelebrationCopy($loan?->customer);
        if ($streakCopy) {
            return $streakCopy;
        }

        if ($remaining !== null && $remaining <= 0.009) {
            return [
                'title' => __('borrower.celebration.repayment_cleared_title'),
                'message' => __('borrower.celebration.repayment_cleared'),
            ];
        }

        if ($remaining !== null) {
            return [
                'title' => __('borrower.celebration.repayment_title'),
                'message' => __('borrower.celebration.repayment', [
                    'remaining' => format_money($remaining),
                ]),
            ];
        }

        return [
            'title' => __('borrower.celebration.repayment_title'),
            'message' => __('borrower.celebration.payment'),
        ];
    }

    /** @return array{title: string, message: string}|null */
    private function onTimeStreakCelebrationCopy(?\App\Models\Customer $customer): ?array
    {
        if (! $customer) {
            return null;
        }

        $status = app(RepaymentStreakRewardService::class)->status($customer);
        if (! ($status['enabled'] ?? true)) {
            return null;
        }

        $count = (int) ($status['count'] ?? 0);
        if ($count <= 0) {
            return null;
        }

        $next = collect($status['milestones'] ?? [])->first(fn ($m) => ! ($m['reached'] ?? false));
        if (! $next) {
            return [
                'title' => __('borrower.celebration.repayment_on_time_title'),
                'message' => __('borrower.celebration.repayment_on_time_done', ['count' => $count]),
            ];
        }

        $remaining = max(0, (int) $next['count'] - $count);

        return [
            'title' => __('borrower.celebration.repayment_on_time_title'),
            'message' => __('borrower.celebration.repayment_on_time', [
                'count' => $count,
                'remaining' => $remaining,
                'points' => number_format((int) ($next['points'] ?? 0)),
            ]),
        ];
    }

    public function uploadProof(CustomerPayment $payment, UploadedFile $file): CustomerPayment
    {
        if ($payment->customer_id !== auth()->user()?->customer?->id) {
            throw new \InvalidArgumentException('You cannot upload proof for this payment.');
        }

        $path = $file->store('payment-proofs/'.$payment->customer_id, 'public');

        $payment->update([
            'proof_path'          => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'status'              => $payment->status === 'clarification_requested'
                ? 'pending_verification'
                : $payment->status,
        ]);

        return $payment->fresh();
    }

    public function verify(CustomerPayment $payment, ?int $actorUserId = null, ?string $notes = null): CustomerPayment
    {
        if (! $payment->isPending()) {
            throw new \InvalidArgumentException('This payment is not awaiting verification.');
        }

        return DB::transaction(function () use ($payment, $actorUserId, $notes) {
            $payment->update([
                'status'             => 'verified',
                'verified_by'        => $actorUserId,
                'verified_at'        => now(),
                'paid_at'            => $payment->paid_at ?? now(),
                'verification_notes' => $notes ? trim(($payment->verification_notes ?? '')."\nVerified: ".$notes) : $payment->verification_notes,
            ]);

            $this->finalizePayment($payment->fresh());

            $verified = $payment->fresh(['customer']);
            if ($verified->payment_method === 'bank_transfer' && $verified->payment_type !== 'loan_repayment') {
                $this->notifyBankStatus($verified, 'bank_payment_verified');
            }

            return $verified->load(['customer', 'journalEntry']);
        });
    }

    public function reject(CustomerPayment $payment, ?int $actorUserId = null, ?string $notes = null): CustomerPayment
    {
        if (! $payment->isPending()) {
            throw new \InvalidArgumentException('This payment is not awaiting verification.');
        }

        $payment->update([
            'status'             => 'rejected',
            'verified_by'        => $actorUserId,
            'verified_at'        => now(),
            'verification_notes' => $notes ? trim(($payment->verification_notes ?? '')."\nRejected: ".$notes) : $payment->verification_notes,
        ]);

        return $payment->fresh();
    }

    public function requestClarification(CustomerPayment $payment, ?int $actorUserId = null, ?string $notes = null): CustomerPayment
    {
        if (! $payment->isPending()) {
            throw new \InvalidArgumentException('This payment is not awaiting verification.');
        }

        $payment->update([
            'status'             => 'clarification_requested',
            'verified_by'        => $actorUserId,
            'verification_notes' => $notes ? trim(($payment->verification_notes ?? '')."\nClarification: ".$notes) : $payment->verification_notes,
        ]);

        return $payment->fresh();
    }

    public function postLedger(CustomerPayment $payment): ?\App\Models\JournalEntry
    {
        if ($payment->journal_entry_id) {
            return $payment->journalEntry;
        }

        $debitAccountId = $this->resolveGlAccount(
            config('payment_types.debit_gl'),
            config('payment_types.debit_gl_fallback'),
        );

        if (! $debitAccountId) {
            return null;
        }

        if (in_array($payment->payment_type, ['valuation_fee', 'insurance_premium', 'post_approval_fee'], true)) {
            $splitEntry = app(PartnerMarkupPaymentLedgerService::class)->post($payment, $debitAccountId);
            if ($splitEntry) {
                $payment->update(['journal_entry_id' => $splitEntry->id]);

                return $splitEntry;
            }
        }

        $typeConfig = config("payment_types.types.{$payment->payment_type}");
        $creditAccountId = $this->resolveGlAccount(
            $typeConfig['credit_gl'] ?? 'fee_income_gl_account_id',
            $typeConfig['fallback_gl'] ?? 'fee_income_gl_account_id',
        );

        if (! $creditAccountId) {
            return null;
        }

        $entry = $this->ledger->post(
            [
                ['account_id' => $debitAccountId, 'debit' => (float) $payment->amount, 'credit' => 0, 'description' => 'Customer payment'],
                ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => (float) $payment->amount, 'description' => $payment->typeLabel()],
            ],
            "Payment {$payment->reference} — {$payment->typeLabel()}",
            $payment,
            optional($payment->paid_at)->toDateString(),
            "Customer: {$payment->customer_id}, Method: {$payment->methodLabel()}",
        );

        if ($entry) {
            $payment->update(['journal_entry_id' => $entry->id]);
        }

        return $entry;
    }

    private function resolveGlAccount(string $primaryKey, string $fallbackKey): ?int
    {
        $id = (int) (Setting::get("finance.{$primaryKey}") ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        $fallback = (int) (Setting::get("finance.{$fallbackKey}") ?? 0);
        if ($fallback > 0 && ChartOfAccount::whereKey($fallback)->exists()) {
            return $fallback;
        }

        return null;
    }

    /**
     * Snapshot partner vs platform markup on the payment for ledger posting / admin visibility.
     */
    private function attachMarkupFeeSplitMeta(CustomerPayment $payment): void
    {
        $meta = (array) ($payment->provider_meta ?? []);

        if ($payment->payment_type === 'valuation_fee') {
            $quote = app(ValuationPricingService::class)->quote();
            $meta['fee_split'] = [
                'partner_share' => $quote['partner_share'],
                'markup_amount' => $quote['markup_amount'],
                'base_cost' => $quote['base_cost'],
                'markup_percent' => $quote['markup_percent'],
                'borrower_amount' => $quote['borrower_amount'],
            ];
            $payment->update(['provider_meta' => $meta]);

            return;
        }

        if ($payment->payment_type === 'post_approval_fee') {
            $breakdown = app(PartnerMarkupPaymentLedgerService::class)->computePostApprovalBreakdown($payment);
            if ($breakdown) {
                $meta['fee_split'] = [
                    'gps_markup' => $breakdown['gpsMarkup'],
                    'gps_partner_share' => $breakdown['gpsPartner'],
                    'other_markup' => $breakdown['otherMarkup'],
                    'other_partner_share' => $breakdown['otherPartner'],
                    'plain_fees' => $breakdown['plainFees'],
                ];
                $payment->update(['provider_meta' => $meta]);
            }
        }
    }

    public function finalizePayment(CustomerPayment $payment): void
    {
        if ($payment->payment_type === 'loan_repayment') {
            $this->finalizeLoanRepayment($payment);

            return;
        }

        if (in_array($payment->payment_type, ['asset_reservation_fee', 'asset_deposit'], true)) {
            app(AssetReservationPaymentService::class)->applyVerifiedPayment($payment);
        }

        $application = $this->resolveLoanApplicationSource($payment);

        if ($payment->payment_type === 'post_approval_fee' && $application) {
            app(PostApprovalFeeService::class)->markAllPaid($application, $payment->customer);
        }

        if ($payment->payment_type === 'application_fee' && $application) {
            if (in_array($application->offer_status, ['asset_conversion_fee_due', 'pending_asset_conversion'], true)
                && $application->alternative_loan_product_id) {
                app(ApplicationOfferService::class)->completeAssetConversion($application);
            }

            $secure = app(CollateralSecureService::class);
            $state = $secure->state($application);
            if (($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_FEE) {
                $secure->markFeePaid($application);
            }
        }

        if ($payment->payment_type === 'valuation_fee' && $application) {
            $secure = app(CollateralSecureService::class);
            $state = $secure->state($application);
            if (($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_VALUATION_FEE) {
                $secure->markValuationFeePaid($application);
            }
        }

        if ($payment->payment_type === 'insurance_premium') {
            try {
                app(CollateralInsurancePartnerService::class)->fulfillPremiumPayment($payment->fresh());
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($payment->payment_type === 'registration_fee' && $payment->customer) {
            $alreadyApplied = \App\Models\MembershipHistory::query()
                ->where('payment_reference', $payment->reference)
                ->whereIn('event', ['issued', 'renewed'])
                ->exists();

            if (! $alreadyApplied) {
                $customer = $payment->customer->fresh();
                $membership = app(MembershipService::class);
                $channel = $payment->payment_method === 'mobile_money' ? 'mobile_money' : 'bank';
                if (! $customer->hasMembership()) {
                    $membership->issue(
                        $customer,
                        null,
                        $payment->reference,
                        $payment->verified_by,
                        (float) $payment->amount,
                        $channel,
                    );
                } else {
                    $membership->renew(
                        $customer,
                        $payment->reference,
                        $channel,
                        $payment->verified_by,
                    );
                }
            }
        }

        $this->postLedger($payment);
    }

    private function resolveLoanApplicationSource(CustomerPayment $payment): ?LoanApplication
    {
        if ($payment->source_type === LoanApplication::class && $payment->source_id) {
            return LoanApplication::find($payment->source_id);
        }

        $source = $payment->source;

        return $source instanceof LoanApplication ? $source : null;
    }

    private function finalizeLoanRepayment(CustomerPayment $payment): void
    {
        if (! $payment->source instanceof Repayment) {
            $this->postLedger($payment);

            return;
        }

        $repayment = $payment->source;
        if ($repayment->status !== 'pending') {
            return;
        }

        $loan = $repayment->loan ?? Loan::find($repayment->loan_id);
        if (! $loan) {
            return;
        }

        $alloc = app(RepaymentPostingService::class)->allocate($loan, (float) $repayment->amount);
        $repayment->update([
            'principal_component' => $alloc['principal'],
            'interest_component'  => $alloc['interest'],
            'penalty_component'   => $alloc['penalty'],
            'status'              => 'received',
        ]);

        $entry = app(RepaymentPostingService::class)->post($repayment->fresh());
        if ($entry) {
            $payment->update(['journal_entry_id' => $entry->id]);
        }
    }

    protected function notifyBankStatus(CustomerPayment $payment, string $templateCode): void
    {
        $customer = $payment->customer;
        if (! $customer) {
            return;
        }

        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer';

        try {
            app(NotificationService::class)->notifyCustomer($customer, $templateCode, [
                'name' => $name,
                'reference' => $payment->reference,
                'payment_type' => $payment->typeLabel(),
                'amount' => format_money((float) $payment->amount),
                '_fallback_subject' => $templateCode === 'bank_payment_verified' ? 'Bank payment verified' : 'Bank payment received',
                '_fallback_body' => $templateCode === 'bank_payment_verified'
                    ? "Hi {$name}, your bank payment {$payment->reference} for {$payment->typeLabel()} (".format_money((float) $payment->amount).') has been verified. — '.brand_name()
                    : "Hi {$name}, we received your bank payment {$payment->reference} for {$payment->typeLabel()} (".format_money((float) $payment->amount).'). We will verify shortly. — '.brand_name(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function validateMobileNumber(string $number): bool
    {
        $normalized = preg_replace('/\s+/', '', $number);

        return (bool) preg_match('/^[1-9]\d{8,14}$/', $normalized);
    }

    /**
     * Human-readable PayIn description — never send raw payment_type keys (underscores are rejected).
     */
    public function payInDescription(string $type, string $reference, ?string $custom = null): string
    {
        $label = filled($custom)
            ? trim((string) $custom)
            : (string) (config("payment_types.types.{$type}.label")
                ?: ucwords(str_replace('_', ' ', $type)));

        return trim($label.' '.$reference);
    }
}
