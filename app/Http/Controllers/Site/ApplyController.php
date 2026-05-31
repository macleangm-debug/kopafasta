<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApplyController extends Controller
{
    public function show(Request $request): View
    {
        $products = LoanProduct::where('is_active', true)->orderBy('name')->get();
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        $preselect = $request->query('product');

        if ($preselect) {
            $selected = LoanProduct::where('is_active', true)
                ->where(function ($query) use ($preselect) {
                    $query->where('id', $preselect)
                          ->orWhere('code', $preselect);
                })
                ->first();

            $preselect = $selected?->id;
        }

        $applicationFee = (int) (optional(ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first())->amount ?? 0);

        return view('site.apply.wizard', compact('products', 'customer', 'preselect', 'applicationFee'));
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'requested_amount'        => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose'                 => ['required', 'string', 'max:500'],
            'first_name'              => ['required', 'string', 'max:60'],
            'last_name'               => ['required', 'string', 'max:60'],
            'date_of_birth'           => ['nullable', 'date'],
            'national_id'             => ['required', 'string', 'max:30'],
            'address'                 => ['required', 'string', 'max:255'],
            'employment_type'         => ['required', 'string', 'max:30'],
            'business_name'           => ['nullable', 'string', 'max:120'],
            'monthly_income'          => ['required', 'numeric', 'min:0'],
            'consent'                 => ['accepted'],
        ]);

        $loanProduct = LoanProduct::where('id', $data['loan_product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $amount = (float) $data['requested_amount'];
        $tenure = (int) $data['requested_tenure_months'];

        if ($amount < $loanProduct->min_amount || $amount > $loanProduct->max_amount) {
            return back()->withInput()->withErrors(['requested_amount' => 'Requested amount must be between '.number_format($loanProduct->min_amount).' and '.number_format($loanProduct->max_amount).'.']);
        }

        if ($tenure < $loanProduct->tenure_min_months || $tenure > $loanProduct->tenure_max_months) {
            return back()->withInput()->withErrors(['requested_tenure_months' => 'Tenure must be between '.$loanProduct->tenure_min_months.' and '.$loanProduct->tenure_max_months.' months.']);
        }

        $user = Auth::user();
        $customer = Customer::firstOrNew(['user_id' => $user->id]);
        $customer->fill([
            'customer_number' => $customer->customer_number ?: 'C-'.strtoupper(Str::random(6)),
            'type'            => 'individual',
            'status'          => $customer->status ?: 'active',
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $customer->email ?: $user->email,
            'phone'           => $customer->phone ?: $user->phone,
            'date_of_birth'   => $data['date_of_birth'] ?? null,
            'national_id'     => $data['national_id'],
            'address'         => $data['address'],
            'employment_type' => $data['employment_type'],
            'business_name'   => $data['business_name'] ?? null,
            'monthly_income'  => $data['monthly_income'],
            'onboarded_at'    => $customer->onboarded_at ?: now(),
        ])->save();

        $app = LoanApplication::create([
            'customer_id'                => $customer->id,
            'loan_product_id'            => $data['loan_product_id'],
            'application_number'         => 'APP-'.strtoupper(Str::random(8)),
            'requested_amount'           => $data['requested_amount'],
            'requested_tenure_months'    => $data['requested_tenure_months'],
            'status'                     => 'submitted',
            'current_stage'              => 'submitted',
            'purpose'                    => $data['purpose'],
            'registration_fee_amount'    => 0,
            'registration_fee_status'    => 'waived',
            'registration_fee_channel'   => null,
            'registration_fee_reference' => null,
            'registration_fee_paid_at'   => null,
            'application_fee_amount'     => (int) (optional(ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first())->amount ?? 0),
            'application_fee_status'     => 'unpaid',
            'submitted_at'               => now(),
        ]);

        return redirect()->route('site.borrower.apply.success', $app)->with('status', 'Application received.');
    }

    public function success(LoanApplication $application): View
    {
        abort_unless($application->customer && $application->customer->user_id === Auth::id(), 403);
        $application->load('product');

        return view('site.apply.success', compact('application'));
    }
}
