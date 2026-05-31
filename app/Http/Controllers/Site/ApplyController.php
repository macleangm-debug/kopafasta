<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSignature;
use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Rules\MinimumAge;
use App\Services\FaceVerificationService;
use App\Services\GuarantorInvitationService;
use App\Services\KycFreshnessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApplyController extends Controller
{
    public function show(
        Request $request,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
    ): View|RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();

        if ($customer && ! $faces->canApply($customer)) {
            return redirect()
                ->route('site.borrower.face-verification')
                ->with('error', 'Complete face verification before starting a loan application.');
        }

        if ($customer && ! $freshness->canApply($customer)) {
            return redirect()
                ->route('site.borrower.kyc-reconfirm')
                ->with('error', 'Please reconfirm your residence and activity information before applying.');
        }

        $products = LoanProduct::where('is_active', true)->orderBy('name')->get();
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

    public function submit(
        Request $request,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
        GuarantorInvitationService $guarantors,
    ): RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();

        if ($customer && ! $faces->canApply($customer)) {
            return redirect()
                ->route('site.borrower.face-verification')
                ->with('error', 'Face verification must be approved before you can submit an application.');
        }

        if ($customer && ! $freshness->canApply($customer)) {
            return redirect()
                ->route('site.borrower.kyc-reconfirm')
                ->with('error', 'Please reconfirm your KYC details before submitting.');
        }

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
            'guarantor_mode'          => ['nullable', 'in:none,internal,external'],
            'internal_member_no'      => ['nullable', 'string', 'max:40'],
            'external_name'           => ['nullable', 'string', 'max:120'],
            'external_phone'          => ['nullable', 'string', 'max:20'],
            'external_email'          => ['nullable', 'email', 'max:120'],
            'external_channel'        => ['nullable', 'in:sms,whatsapp,email'],
            'signer_name'             => ['required', 'string', 'max:120'],
            'signature_data'          => ['required', 'string', 'starts_with:data:image/png;base64,'],
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

        if ($loanProduct->requires_guarantor) {
            $mode = $data['guarantor_mode'] ?? 'none';
            if ($mode === 'none') {
                return back()->withInput()->withErrors(['guarantor_mode' => 'This product requires a guarantor.']);
            }
            if ($mode === 'internal' && empty($data['internal_member_no'])) {
                return back()->withInput()->withErrors(['internal_member_no' => 'Enter the guarantor membership number.']);
            }
            if ($mode === 'external' && (empty($data['external_name']) || empty($data['external_phone']) || empty($data['external_channel']))) {
                return back()->withInput()->withErrors(['external_name' => 'Provide external guarantor name, phone and invite channel.']);
            }
        }

        $user = Auth::user();
        $customer = Customer::firstOrNew(['user_id' => $user->id]);
        $addressLine = trim(collect([$data['street'], $data['ward'] ?? null, $data['district'], $data['region']])->filter()->implode(', '));
        $purposeLabel = config('loan_purposes.'.$data['purpose']) ?? $data['purpose'];

        if (! $customer->identity_locked) {
            $customer->fill([
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender'        => $data['gender'] ?? null,
                'national_id'   => $data['national_id'],
            ]);
        }

        $customer->fill([
            'customer_number' => $customer->customer_number ?: 'C-'.strtoupper(Str::random(6)),
            'type'            => 'individual',
            'status'          => $customer->status ?: 'active',
            'email'           => $customer->email ?: $user->email,
            'phone'           => $customer->phone ?: $user->phone,
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

        $status = 'submitted';
        $submittedAt = now();

        $app = LoanApplication::create([
            'customer_id'                => $customer->id,
            'loan_product_id'            => $data['loan_product_id'],
            'application_number'         => 'APP-'.strtoupper(Str::random(8)),
            'requested_amount'           => $data['requested_amount'],
            'requested_tenure_months'    => $data['requested_tenure_months'],
            'status'                     => $status,
            'current_stage'              => $status,
            'purpose'                    => $purposeLabel,
            'registration_fee_amount'    => 0,
            'registration_fee_status'    => 'waived',
            'registration_fee_channel'   => null,
            'registration_fee_reference' => null,
            'registration_fee_paid_at'   => null,
            'application_fee_amount'     => (int) (optional(ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first())->amount ?? 0),
            'application_fee_status'     => 'unpaid',
            'submitted_at'               => $submittedAt,
        ]);

        ApplicationSignature::create([
            'loan_application_id' => $app->id,
            'signer_type'         => 'borrower',
            'signer_name'         => $data['signer_name'],
            'signature_data'      => $data['signature_data'],
            'signed_at'           => now(),
        ]);

        if ($loanProduct->requires_guarantor) {
            try {
                if (($data['guarantor_mode'] ?? '') === 'internal') {
                    $guarantors->attachInternal($customer, $app, $data['internal_member_no']);
                } elseif (($data['guarantor_mode'] ?? '') === 'external') {
                    $guarantors->attachExternal(
                        $customer,
                        $app,
                        $data['external_name'],
                        $data['external_phone'],
                        $data['external_email'] ?? null,
                        $data['external_channel'],
                    );
                }
            } catch (\InvalidArgumentException $e) {
                $app->delete();

                return back()->withInput()->withErrors(['internal_member_no' => $e->getMessage()]);
            }

            if (! $guarantors->hasApprovedGuarantor($app)) {
                $app->update([
                    'status'        => 'awaiting_guarantor',
                    'current_stage' => 'awaiting_guarantor',
                    'submitted_at'  => null,
                ]);
            }
        }

        $message = $app->status === 'awaiting_guarantor'
            ? 'Application saved. Waiting for guarantor approval before submission.'
            : 'Application received.';

        return redirect()->route('site.borrower.apply.success', $app)->with('status', $message);
    }

    public function success(LoanApplication $application): View
    {
        abort_unless($application->customer && $application->customer->user_id === Auth::id(), 403);
        $application->load('product');

        return view('site.apply.success', compact('application'));
    }
}
