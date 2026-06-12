<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\Setting;
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

            $resolved = $this->accounts->resolve($type, $method, $product);
            $reference = $data['reference'] ?? $this->generateReference();
            $autoVerify = (bool) ($data['auto_verify'] ?? false);
            $isBank = $method === 'bank_transfer';

            $status = $autoVerify ? 'verified' : ($isBank ? 'pending_verification' : 'paid');

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
                'paid_at'                 => $autoVerify || ! $isBank ? now() : null,
                'payment_date'            => $data['payment_date'] ?? ($isBank ? now()->toDateString() : null),
                'source_type'             => isset($data['source']) ? $data['source']::class : null,
                'source_id'               => ($data['source'] ?? null)?->getKey(),
                'loan_id'                 => $loan?->id,
                'loan_product_id'         => $product?->id,
                'created_by'              => auth()->id(),
            ]);

            if ($autoVerify || ! $isBank) {
                $this->finalizePayment($payment);
            }

            return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
        });
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

            return $payment->fresh(['customer', 'journalEntry']);
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

        $typeConfig = config("payment_types.types.{$payment->payment_type}");
        $creditAccountId = $this->resolveGlAccount(
            $typeConfig['credit_gl'] ?? 'fee_income_gl_account_id',
            $typeConfig['fallback_gl'] ?? 'fee_income_gl_account_id',
        );

        if (! $debitAccountId || ! $creditAccountId) {
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

    public function finalizePayment(CustomerPayment $payment): void
    {
        if ($payment->payment_type === 'loan_repayment') {
            $this->finalizeLoanRepayment($payment);

            return;
        }

        if (in_array($payment->payment_type, ['asset_reservation_fee', 'asset_deposit'], true)) {
            app(AssetReservationPaymentService::class)->applyVerifiedPayment($payment);
        }

        if ($payment->payment_type === 'post_approval_fee' && $payment->source instanceof LoanApplication) {
            app(PostApprovalFeeService::class)->markAllPaid($payment->source->fresh(), $payment->customer);
        }

        if ($payment->payment_type === 'application_fee' && $payment->source instanceof LoanApplication) {
            $application = $payment->source->fresh();
            if (in_array($application->offer_status, ['asset_conversion_fee_due', 'pending_asset_conversion'], true)
                && $application->alternative_loan_product_id) {
                app(ApplicationOfferService::class)->completeAssetConversion($application);
            }
        }

        $this->postLedger($payment);
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

    public static function validateMobileNumber(string $number): bool
    {
        $normalized = preg_replace('/\s+/', '', $number);

        return (bool) preg_match('/^[1-9]\d{8,14}$/', $normalized);
    }
}
