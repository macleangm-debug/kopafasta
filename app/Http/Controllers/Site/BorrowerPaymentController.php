<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\BorrowerRefund;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Loan;
use App\Services\CustomerPaymentService;
use App\Services\PaymentAccountService;
use App\Support\PhoneNumber;
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

    public function index(): RedirectResponse
    {
        // Fee history stays in admin. Open repay from an active loan (or loan list).
        $customer = $this->customer();
        $loan = Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->orderByDesc('id')
            ->first();

        if ($loan) {
            return redirect()->route('site.borrower.payments.create', ['loan' => $loan->id]);
        }

        return redirect()->route('site.borrower.loans');
    }

    public function create(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();
        $loans = Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->with(['product', 'repaymentSchedules'])
            ->get();

        if ($loans->isEmpty()) {
            return redirect()->route('site.borrower.loans')
                ->with('error', __('borrower.payments_page.create.no_loans_desc'));
        }

        $loanId = $request->query('loan_id', $request->query('loan'));
        $selectedLoan = $loanId
            ? $loans->firstWhere('id', (int) $loanId)
            : $loans->first();

        if (! $selectedLoan) {
            $selectedLoan = $loans->first();
        }

        $suggestedAmount = null;
        $servicing = app(\App\Services\ActiveLoanServicingService::class)->forLoan($selectedLoan);
        if ($servicing['in_arrears'] ?? false) {
            $suggestedAmount = (float) ($servicing['amount_in_arrears'] ?: $servicing['next_due_amount'] ?? 0);
        } else {
            $suggestedAmount = isset($servicing['next_due_amount']) ? (float) $servicing['next_due_amount'] : null;
        }

        $paymentReference = $request->session()->get('repayment_payment_ref');
        if (! filled($paymentReference)) {
            $paymentReference = app(CustomerPaymentService::class)->generateReference();
            $request->session()->put('repayment_payment_ref', $paymentReference);
        }

        return view('site.borrower.payments.create', compact(
            'customer',
            'loans',
            'selectedLoan',
            'suggestedAmount',
            'servicing',
            'paymentReference',
        ));
    }

    public function store(Request $request, CustomerPaymentService $payments): RedirectResponse
    {
        $customer = $this->customer();
        $dummyGateway = payment_gateway_is_dummy();

        $data = $request->validate([
            'loan_id'             => ['required', 'exists:loans,id'],
            'payment_method'      => ['required', 'in:bank_transfer,mobile_money'],
            'amount'              => ['required', 'numeric', 'min:100'],
            'mobile_number'       => [$dummyGateway ? 'nullable' : 'required_if:payment_method,mobile_money', 'nullable', 'string', 'max:20'],
            'mobile_number_local' => ['nullable', 'string', 'max:20'],
            'payment_date'        => ['nullable', 'date'],
            'proof'               => [
                $request->input('payment_method') === 'bank_transfer' ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $mobileNumber = PhoneNumber::fromRequest($request, 'mobile_number', $customer->country_code ?? null);

        if ($data['payment_method'] === 'mobile_money' && filled($mobileNumber)) {
            if (! CustomerPaymentService::validateMobileNumber($mobileNumber)) {
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

        $paymentReference = $request->session()->pull('repayment_payment_ref')
            ?? $payments->generateReference();

        $payment = $payments->create([
            'customer'       => $customer,
            'payment_type'   => 'loan_repayment',
            'payment_method' => $data['payment_method'],
            'amount'         => $data['amount'],
            'loan'           => $loan,
            'mobile_number'  => $mobileNumber,
            'payment_date'   => $data['payment_date'] ?? null,
            'proof'          => $request->file('proof'),
            'source'         => $repayment,
            'reference'      => $paymentReference,
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
            ->when($payment->isVerified(), function ($r) {
                $existing = session(\App\Support\Celebration::SESSION_KEY, []);
                if (! is_array($existing)) {
                    $existing = [];
                }

                return $r->with(
                    \App\Support\Celebration::SESSION_KEY,
                    array_values(array_unique(array_merge($existing, ['payment'])))
                );
            });
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

    public function pay(Request $request, CustomerPayment $payment, CustomerPaymentService $payments): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($payment->customer_id === $customer->id, 403);

        $data = $request->validate([
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'mobile_number_local' => ['nullable', 'string', 'max:20'],
        ]);

        $mobileNumber = PhoneNumber::fromRequest($request, 'mobile_number', $customer->country_code ?? null)
            ?: ($data['mobile_number'] ?? null);

        try {
            $payment = $payments->initiateCollection($payment, $mobileNumber);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = CustomerPaymentService::localizeProviderMessage(
                collect($e->errors())->flatten()->first()
            );

            return redirect()
                ->route('site.borrower.payments.show', $payment)
                ->with('collect_error', $message)
                ->with('show_collect_failed', true)
                ->withErrors(['payment_phone' => $message]);
        }

        return redirect()->route('site.borrower.payments.show', $payment);
    }

    public function updatePhone(Request $request, CustomerPayment $payment, CustomerPaymentService $payments): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($payment->customer_id === $customer->id, 403);
        abort_unless($payment->awaitsCollection(), 422);

        $request->validate([
            'mobile_number' => ['required', 'string', 'max:20'],
            'mobile_number_local' => ['nullable', 'string', 'max:20'],
        ]);

        $mobileNumber = PhoneNumber::fromRequest($request, 'mobile_number', $customer->country_code ?? null);
        if (! filled($mobileNumber) || ! CustomerPaymentService::validateMobileNumber($mobileNumber)) {
            return back()->withInput()->withErrors([
                'mobile_number' => __('borrower.payments.mobile_number_required'),
            ]);
        }

        $meta = (array) ($payment->provider_meta ?? []);
        unset($meta['last_collect_error'], $meta['last_collect_error_at']);
        $payment->update([
            'mobile_number' => $mobileNumber,
            'provider_meta' => $meta,
        ]);

        return redirect()
            ->route('site.borrower.payments.show', $payment)
            ->with('status', __('borrower.payment_waiting.phone_updated'));
    }

    public function switchBank(CustomerPayment $payment, CustomerPaymentService $payments): RedirectResponse
    {
        $customer = $this->customer();
        abort_unless($payment->customer_id === $customer->id, 403);

        try {
            $payment = $payments->switchToBankTransfer($payment);
        } catch (\Throwable $e) {
            return redirect()
                ->route('site.borrower.payments.show', $payment)
                ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.bank_unavailable'));
        }

        return redirect()
            ->route('site.borrower.payments.show', $payment)
            ->with('status', __('borrower.payment_waiting.switched_to_bank'));
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
            $payment->awaitsCollection() => 'ready',
            default => 'pending',
        };

        $redirect = null;
        $celebration = null;
        if ($state === 'paid') {
            $redirect = $payments->successRedirectUrl($payment);
            $celebration = $payments->celebrationCopy($payment);
        }

        return response()->json([
            'ok' => true,
            'state' => $state,
            'status' => $payment->status,
            'reference' => $payment->reference,
            'title' => $celebration['title'] ?? null,
            'message' => match ($state) {
                'paid' => $celebration['message'] ?? __('borrower.payment_waiting.paid'),
                'failed' => __('borrower.payment_waiting.failed'),
                'waiting' => $payment->mobile_number
                    ? __('borrower.payment_waiting.waiting_phone', ['phone' => $payment->mobile_number])
                    : __('borrower.payment_waiting.waiting'),
                'ready' => __('borrower.payment_waiting.ready'),
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

        $payment->load(['bankAccount', 'mobileMoneyAccount', 'loan', 'loanProduct', 'customer']);

        $accounts = app(PaymentAccountService::class);
        $bankDetails = null;
        $mobileDetails = null;
        $canSwitchToBank = false;

        if ($payment->payment_method === 'bank_transfer' && $payment->bankAccount) {
            $bankDetails = $accounts->bankTransferDetails($payment->bankAccount, $payment->reference);
        }

        if ($payment->payment_method === 'mobile_money' && $payment->mobileMoneyAccount) {
            $mobileDetails = $accounts->mobileMoneyDetails($payment->mobileMoneyAccount, $payment->reference);
        }

        if ($payment->awaitsCollection()) {
            $product = $payment->loanProduct
                ?? ($payment->loan_id ? $payment->loan?->product : null);
            $bankResolved = $accounts->resolve($payment->payment_type, 'bank_transfer', $product);
            $canSwitchToBank = (bool) ($bankResolved['bank_account'] ?? null);
        }

        return view('site.borrower.payments.show', compact(
            'payment',
            'bankDetails',
            'mobileDetails',
            'canSwitchToBank',
        ));
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
