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
        $products = LoanProduct::where('is_active', true)->orderBy('id')->get();
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        $preselect = $request->query('product');
        $registrationFee = (int) config('site.registration_fee', 10000);
        $applicationFee  = (int) (optional(ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first())->amount ?? 0);
        $payChannels = config('site.fee_channels', [
            ['name' => 'M-Pesa',  'till' => '123456', 'note' => 'Lipa na M-Pesa → Pay Bill → Business no.'],
            ['name' => 'Tigo Pesa','till' => '654321', 'note' => 'Lipa kwa Tigo Pesa'],
            ['name' => 'Airtel Money', 'till' => '987654', 'note' => 'Pay merchant'],
            ['name' => 'Bank (CRDB)', 'till' => '0150-XXXXX-00', 'note' => 'Kopafasta Microfinance Ltd'],
        ]);
        return view('site.apply.wizard', compact('products', 'customer', 'preselect', 'registrationFee', 'applicationFee', 'payChannels'));
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Step 1 - registration fee
            'registration_fee_channel'   => ['required', 'string', 'max:30'],
            'registration_fee_reference' => ['required', 'string', 'max:60'],

            // Step 2 - product
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'requested_amount'        => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose'                 => ['required', 'string', 'max:500'],

            // Step 3 - personal (may already exist on customer)
            'first_name'   => ['required', 'string', 'max:60'],
            'last_name'    => ['required', 'string', 'max:60'],
            'date_of_birth'=> ['nullable', 'date'],
            'national_id'  => ['required', 'string', 'max:30'],
            'address'      => ['required', 'string', 'max:255'],

            // Step 4 - employment / income
            'employment_type' => ['required', 'string', 'max:30'],
            'business_name'   => ['nullable', 'string', 'max:120'],
            'monthly_income'  => ['required', 'numeric', 'min:0'],

            // Step 5 - review (consent)
            'consent' => ['accepted'],
        ]);

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
            'customer_id'             => $customer->id,
            'loan_product_id'         => $data['loan_product_id'],
            'application_number'      => 'APP-'.strtoupper(Str::random(8)),
            'requested_amount'        => $data['requested_amount'],
            'requested_tenure_months' => $data['requested_tenure_months'],
            'status'                  => 'submitted',
            'current_stage'           => 'submitted',
            'purpose'                 => $data['purpose'],
            'registration_fee_amount'    => (int) config('site.registration_fee', 10000),
            'registration_fee_status'    => 'pending',
            'registration_fee_channel'   => $data['registration_fee_channel'],
            'registration_fee_reference' => $data['registration_fee_reference'],
            'registration_fee_paid_at'   => now(),
            'application_fee_amount'     => (int) (optional(ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first())->amount ?? 0),
            'application_fee_status'     => 'unpaid',
            'submitted_at'            => now(),
        ]);

        return redirect()->route('site.apply.success', $app)->with('status', 'Application received.');
    }

    public function success(LoanApplication $application): View
    {
        abort_unless($application->customer && $application->customer->user_id === Auth::id(), 403);
        $application->load('product');
        return view('site.apply.success', compact('application'));
    }
}
