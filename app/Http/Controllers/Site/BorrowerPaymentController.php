<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\BorrowerRefund;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Services\BorrowerPaymentLedgerService;
use App\Services\CustomerPaymentService;
use App\Services\PaymentAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BorrowerPaymentController extends Controller
{
    use AuditsActions;

    protected function customer(): Customer
    {
        return Customer::where('user_id', Auth::id())->firstOrFail();
    }

    public function index(BorrowerPaymentLedgerService $ledger): View
    {
        $customer = $this->customer();
        $entries = $ledger->entriesFor($customer);

        $loans = Loan::where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->with(['product', 'repaymentSchedules'])
            ->get();

        $servicing = app(\App\Services\ActiveLoanServicingService::class);
        $loanSnapshots = $loans
            ->map(fn (Loan $loan) => array_merge($servicing->forLoan($loan), ['loan' => $loan]))
            ->sortBy(function (array $snap) {
                $due = $snap['next_due_date'] ?? null;

                return $due ? $due->timestamp : PHP_INT_MAX;
            })
            ->values();

        $focusLoan = $loanSnapshots->first(
            fn (array $snap) => ($snap['in_arrears'] ?? false) || ($snap['next_due_amount'] ?? null) !== null
        ) ?? $loanSnapshots->first();

        return view('site.borrower.payments.index', compact('customer', 'entries', 'loans', 'loanSnapshots', 'focusLoan'));
    }

    public function showRefund(BorrowerRefund $borrowerRefund): View
    {
        $customer = $this->customer();
        abort_unless((int) $borrowerRefund->customer_id === (int) $customer->id, 403);

        return view('site.borrower.payments.refund-show', [
            'customer' => $customer,
            'refund'   => $borrowerRefund->load('loan'),
        ]);
    }

    public function create(Request $request): View
    {
        $customer = $this->customer();
        $loans = Loan::where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->with(['product', 'repaymentSchedules'])
            ->get();

        $loanId = $request->query('loan_id', $request->query('loan'));
        $selectedLoan = $loanId ? $loans->firstWhere('id', (int) $loanId) : $loans->first();

        $suggestedAmount = null;
        if ($selectedLoan) {
            $snap = app(\App\Services\ActiveLoanServicingService::class)->forLoan($selectedLoan);
            $suggestedAmount = $snap['in_arrears']
                ? (float) ($snap['amount_in_arrears'] ?: $snap['next_due_amount'])
                : ($snap['next_due_amount'] ?? null);
        }

        return view('site.borrower.payments.create', compact('customer', 'loans', 'selectedLoan', 'suggestedAmount'));
    }

    public function store(Request $request, CustomerPaymentService $payments, PaymentAccountService $accounts): RedirectResponse
    {
        $customer = $this->customer();
        $dummyGateway = payment_gateway_is_dummy();

        $data = $request->validate([
            'loan_id'        => ['required', 'exists:loans,id'],
            'payment_method' => ['required', 'in:bank_transfer,mobile_money'],
            'amount'         => ['required', 'numeric', 'min:100'],
            'mobile_number'  => [$dummyGateway ? 'nullable' : 'required_if:payment_method,mobile_money', 'nullable', 'string', 'max:20'],
            'payment_date'   => ['nullable', 'date'],
            'proof'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($data['payment_method'] === 'mobile_money' && ! empty($data['mobile_number'])) {
            if (! CustomerPaymentService::validateMobileNumber($data['mobile_number'])) {
                return back()->withInput()->withErrors([
                    'mobile_number' => 'Enter your number with country code, without a leading zero (e.g. 255712345678).',
                ]);
            }
        }

        $loan = Loan::where('id', $data['loan_id'])->where('customer_id', $customer->id)->firstOrFail();

        $channels = payment_channels_for_amount((float) $data['amount']);
        if ($data['payment_method'] === 'mobile_money') {
            $allowed = $channels['channels'];
            if (! collect($allowed)->contains(fn ($c) => str_contains(strtolower($c), 'pesa') || str_contains(strtolower($c), 'money'))) {
                return back()->withInput()->withErrors(['amount' => 'Mobile money is not available for this amount.']);
            }
        }

        $repayment = \App\Models\Repayment::create([
            'loan_id'   => $loan->id,
            'reference' => '',
            'channel'   => $data['payment_method'] === 'bank_transfer' ? 'bank' : 'mobile_money',
            'amount'    => $data['amount'],
            'status'    => 'pending',
            'paid_at'   => now(),
        ]);

        $payment = $payments->create([
            'customer'       => $customer,
            'payment_type'   => 'loan_repayment',
            'payment_method' => $data['payment_method'],
            'amount'         => $data['amount'],
            'loan'           => $loan,
            'mobile_number'  => $data['mobile_number'] ?? null,
            'payment_date'   => $data['payment_date'] ?? null,
            'proof'          => $request->file('proof'),
            'source'         => $repayment,
            'auto_verify'    => $dummyGateway && $data['payment_method'] === 'mobile_money',
        ]);

        $repayment->update(['reference' => $payment->reference]);

        $this->auditBorrower('payment.submitted', $payment, [
            'loan_id'   => $loan->id,
            'reference' => $payment->reference,
            'amount'    => $payment->amount,
        ]);

        $message = $data['payment_method'] === 'bank_transfer'
            ? "Bank transfer submitted. Reference: {$payment->reference}. Status: Pending verification."
            : ($payment->isVerified()
                ? 'Payment received and verified.'
                : "Payment submitted. Reference: {$payment->reference}.");

        return redirect()->route('site.borrower.payments.show', $payment)
            ->with('status', $message)
            ->when($payment->isVerified(), fn ($r) => $r->with(\App\Support\Celebration::SESSION_KEY, ['payment']));
    }

    public function status(CustomerPayment $payment, CustomerPaymentService $payments): \Illuminate\Http\JsonResponse
    {
        $customer = $this->customer();
        abort_unless($payment->customer_id === $customer->id, 403);

        if ($payment->status === 'processing' && $payment->provider === 'payin') {
            $payment = $payments->refreshFromProvider($payment);
        } else {
            $payment = $payment->fresh();
        }

        $state = match (true) {
            $payment->isVerified() || in_array($payment->status, ['paid', 'verified'], true) => 'paid',
            $payment->status === 'rejected' => 'failed',
            $payment->status === 'processing' => 'waiting',
            default => 'pending',
        };

        $redirect = null;
        if ($state === 'paid') {
            $redirect = $payments->successRedirectUrl($payment);
        }

        return response()->json([
            'ok' => true,
            'state' => $state,
            'status' => $payment->status,
            'reference' => $payment->reference,
            'message' => match ($state) {
                'paid' => __('borrower.payment_waiting.paid'),
                'failed' => __('borrower.payment_waiting.failed'),
                'waiting' => $payment->mobile_number
                    ? __('borrower.payment_waiting.waiting_phone', ['phone' => $payment->mobile_number])
                    : __('borrower.payment_waiting.waiting'),
                default => __('borrower.payment_waiting.pending'),
            },
            'redirect_url' => $redirect,
            'poll_after_ms' => $state === 'waiting' ? 5000 : null,
        ]);
    }

    public function show(CustomerPayment $payment): View
    {
        $customer = $this->customer();
        abort_unless($payment->customer_id === $customer->id, 403);

        $payment->load(['bankAccount', 'mobileMoneyAccount', 'loan']);

        $accounts = app(PaymentAccountService::class);
        $bankDetails = null;
        $mobileDetails = null;

        if ($payment->payment_method === 'bank_transfer' && $payment->bankAccount) {
            $bankDetails = $accounts->bankTransferDetails($payment->bankAccount, $payment->reference);
        }

        if ($payment->payment_method === 'mobile_money' && $payment->mobileMoneyAccount) {
            $mobileDetails = $accounts->mobileMoneyDetails($payment->mobileMoneyAccount, $payment->reference);
        }

        return view('site.borrower.payments.show', compact('payment', 'bankDetails', 'mobileDetails'));
    }

    public function uploadProof(Request $request, CustomerPayment $payment, CustomerPaymentService $service): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($payment->customer_id === $customer->id, 403);

        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        try {
            $service->uploadProof($payment, $data['proof']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Proof uploaded. Finance will review your payment.');
    }
}
