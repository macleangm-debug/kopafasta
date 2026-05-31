<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Rules\MinimumAge;
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

        return view('site.apply.wizard', compact('products', 'customer', 'preselect', 'applicationFee'))
            ->with('loanPurposes', config('loan_purposes'))
            ->with('incomeRanges', config('income_ranges'))
            ->with('activityTypes', config('activity_profiles.types'));
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'requested_amount'        => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose'                 => ['required', 'string', 'max:100'],
            'first_name'              => ['required', 'string', 'max:60'],
            'last_name'               => ['required', 'string', 'max:60'],
            'date_of_birth'           => ['required', 'date', new MinimumAge],
            'gender'                  => ['nullable', 'string', 'in:male,female,other'],
            'national_id'             => ['required', 'string', 'max:30'],
            'region'                  => ['required', 'string', 'max:100'],
            'district'                => ['required', 'string', 'max:100'],
            'ward'                    => ['nullable', 'string', 'max:100'],
            'street'                  => ['required', 'string', 'max:255'],
            'nok_name'                => ['required', 'string', 'max:120'],
            'nok_relationship'        => ['required', 'string', 'max:40'],
            'nok_phone'               => ['required', 'string', 'max:20'],
            'nok_region'              => ['required', 'string', 'max:100'],
            'nok_district'            => ['required', 'string', 'max:100'],
            'activity_type'           => ['required', 'string', 'max:40'],
            'activity_details'        => ['nullable', 'array'],
            'income_range'            => ['required', 'string', 'in:'.implode(',', array_keys(config('income_ranges')))],
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
        $addressLine = trim(collect([$data['street'], $data['ward'] ?? null, $data['district'], $data['region']])->filter()->implode(', '));
        $purposeLabel = config('loan_purposes.'.$data['purpose']) ?? $data['purpose'];

        $customer->fill([
            'customer_number' => $customer->customer_number ?: 'C-'.strtoupper(Str::random(6)),
            'type'            => 'individual',
            'status'          => $customer->status ?: 'active',
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $customer->email ?: $user->email,
            'phone'           => $customer->phone ?: $user->phone,
            'date_of_birth'   => $data['date_of_birth'],
            'gender'          => $data['gender'] ?? null,
            'national_id'     => $data['national_id'],
            'region'          => $data['region'],
            'district'        => $data['district'],
            'ward'            => $data['ward'] ?? null,
            'street'          => $data['street'],
            'address'         => $addressLine,
            'nok_name'        => $data['nok_name'],
            'nok_relationship'=> $data['nok_relationship'],
            'nok_phone'       => $data['nok_phone'],
            'nok_region'      => $data['nok_region'],
            'nok_district'    => $data['nok_district'],
            'activity_type'   => $data['activity_type'],
            'activity_details'=> $data['activity_details'] ?? [],
            'employment_type' => $data['activity_type'],
            'income_range'    => $data['income_range'],
            'monthly_income'  => config('income_ranges.'.$data['income_range'].'.midpoint'),
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
            'purpose'                    => $purposeLabel,
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
