<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Models\ApplicationSignature;
use App\Models\AssetReservation;
use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Rules\MinimumAge;
use App\Services\AffiliateService;
use App\Services\ApplicationRequirementsService;
use App\Services\AssetReservationService;
use App\Services\FaceVerificationService;
use App\Services\GuarantorInvitationService;
use App\Services\KycFreshnessService;
use App\Services\LoanProductReadinessService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApplyController extends Controller
{
    use AuditsActions;

    public function show(
        Request $request,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
        ApplicationRequirementsService $requirements,
        SmartLoanApplicationWizardService $wizard,
    ): View|RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();

        if (! $customer) {
            return redirect()->route('site.borrower.dashboard')->with('error', 'Complete your profile before applying.');
        }

        $eligibility = $requirements->checklist($customer);
        $profileSections = $wizard->profileSections($customer);

        $products = LoanProduct::where('is_active', true)->orderBy('name')->get()
            ->reject(fn (LoanProduct $p) => is_marketplace_loan_product($p->code))
            ->values();
        $preselect = $request->query('product');
        $preselectedProduct = null;

        if ($preselect) {
            $preselectedProduct = LoanProduct::where('is_active', true)
                ->where(function ($query) use ($preselect) {
                    $query->where('id', $preselect)
                          ->orWhere('code', $preselect);
                })
                ->first();

            $preselect = $preselectedProduct?->id;
        }

        $selectedProduct = $preselect ? $products->firstWhere('id', (int) $preselect) : null;
        $reservation = null;
        $assetApplication = null;
        if ($request->filled('reservation') && $customer) {
            $reservation = AssetReservation::query()
                ->where('customer_id', $customer->id)
                ->with('asset')
                ->find($request->query('reservation'));
        }
        if ($preselectedProduct && is_marketplace_loan_product($preselectedProduct->code) && ! $reservation) {
            return redirect()
                ->route('site.borrower.marketplace')
                ->with('status', __('borrower.marketplace.subtitle'));
        }

        if ($reservation?->asset) {
            $assetLoanProduct = LoanProduct::where('is_active', true)
                ->where('code', config('asset_marketplace.asset_loan_product_code', 'AL'))
                ->first();

            if ($assetLoanProduct) {
                $selectedProduct = $assetLoanProduct;
                $preselect = $assetLoanProduct->id;

                $asset = $reservation->asset;
                $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
                $assetValue = (float) ($asset->asset_value ?: max($deposit * 1.4, $deposit));
                $remainingLoan = max(0, round($assetValue - $deposit, 2));
                $tenure = max(1, (int) ($asset->max_tenure_months ?: $assetLoanProduct->tenure_max_months));

                $assetApplication = [
                    'asset_title'        => $asset->title,
                    'asset_value'        => $assetValue,
                    'deposit'            => $deposit,
                    'remaining_loan'     => $remainingLoan,
                    'weekly_installment' => (float) $asset->weekly_installment,
                    'max_tenure_months'  => $tenure,
                    'purpose'            => 'asset_financing',
                ];
            }
        }

        $stepPlan = collect($wizard->borrowerStepPlan($customer, $selectedProduct))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values()
            ->all();
        $incomeVerification = $wizard->incomeVerification($customer);
        $applicationFee = quoted_application_fee($customer);
        $productQuestions = config('loan_product_questions', []);
        $readinessUrl = route('site.borrower.apply.product-readiness', ['product' => '__ID__']);

        return view('site.apply.wizard', compact(
            'products',
            'customer',
            'preselect',
            'applicationFee',
            'eligibility',
            'profileSections',
            'stepPlan',
            'incomeVerification',
            'productQuestions',
            'readinessUrl',
            'reservation',
            'assetApplication',
            'selectedProduct',
        ))->with('loanPurposes', loan_purpose_options())
            ->with('marketplaceOnlyCodes', marketplace_only_loan_codes())
            ->with('marketplaceUrl', route('site.borrower.marketplace'))
            ->with('incomeRanges', config('income_ranges'))
            ->with('activityTypes', activity_type_options());
    }

    public function productReadiness(LoanProduct $product, LoanProductReadinessService $readiness): \Illuminate\Http\JsonResponse
    {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();
        abort_unless($customer, 403);

        $product = LoanProduct::where('id', $product->id)->where('is_active', true)->firstOrFail();

        return response()->json($readiness->assess($customer, $product));
    }

    public function submit(
        Request $request,
        FaceVerificationService $faces,
        KycFreshnessService $freshness,
        GuarantorInvitationService $guarantors,
        ApplicationRequirementsService $requirements,
    ): RedirectResponse {
        $customer = Auth::user()->customer ?? Customer::where('user_id', Auth::id())->first();

        if ($customer) {
            $checklist = $requirements->checklist($customer);
            if (! $checklist['can_apply']) {
                return redirect()
                    ->route('site.borrower.dashboard')
                    ->with('error', 'You must complete all loan application requirements before submitting.');
            }
        }

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

        $loanProduct = LoanProduct::where('id', $request->input('loan_product_id'))
            ->where('is_active', true)
            ->firstOrFail();
        $isMarketplaceProduct = is_marketplace_loan_product($loanProduct->code);

        $data = $request->validate([
            'loan_product_id'         => ['required', 'exists:loan_products,id'],
            'requested_amount'        => ['required', 'numeric', 'min:1000'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose'                 => [$isMarketplaceProduct ? 'nullable' : 'required', 'string', 'max:100'],
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
            'product_question'        => ['nullable', 'array'],
            'income_document'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'income_document_type'    => ['nullable', 'in:bank,mobile_money'],
            'asset_reservation_id'    => ['nullable', 'integer', 'exists:asset_reservations,id'],
        ]);

        if ($isMarketplaceProduct && blank($data['purpose'] ?? null)) {
            $data['purpose'] = 'asset_financing';
        }

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
        $purposeLabel = loan_purpose_label($data['purpose']) ?? $data['purpose'];

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
            'activity_details'=> filled($data['activity_details'] ?? null)
                ? $data['activity_details']
                : ($customer->activity_details ?? []),
            'employment_type' => $data['activity_type'],
            'income_range'    => $data['income_range'],
            'monthly_income'  => config('income_ranges.'.$data['income_range'].'.midpoint'),
            'onboarded_at'    => $customer->onboarded_at ?: now(),
        ])->save();

        $status = 'submitted';
        $submittedAt = now();

        $appFee = quoted_application_fee($customer);

        $app = LoanApplication::create([
            'customer_id'                => $customer->id,
            'loan_product_id'            => $data['loan_product_id'],
            'application_number'         => 'LN-'.now()->format('Y').'-'.str_pad((string) (LoanApplication::max('id') + 1), 6, '0', STR_PAD_LEFT),
            'requested_amount'           => $data['requested_amount'],
            'requested_tenure_months'    => $data['requested_tenure_months'],
            'status'                     => $status,
            'current_stage'              => $status,
            'purpose'                    => $purposeLabel,
            'screening_payload'          => [
                'product_code'      => $loanProduct->code,
                'product_questions' => array_filter($data['product_question'] ?? []),
            ],
            'registration_fee_amount'    => 0,
            'registration_fee_status'    => 'waived',
            'registration_fee_channel'   => null,
            'registration_fee_reference' => null,
            'registration_fee_paid_at'   => null,
            'application_fee_amount'     => $appFee,
            'application_fee_status'     => 'unpaid',
            'submitted_at'               => $submittedAt,
        ]);

        if ($request->filled('asset_reservation_id')) {
            $reservation = AssetReservation::query()
                ->where('customer_id', $customer->id)
                ->find($request->input('asset_reservation_id'));
            if ($reservation) {
                app(AssetReservationService::class)->linkApplication($reservation, $app);
            }
        }

        app(AffiliateService::class)->trackApplication($app);

        ApplicationSignature::create([
            'loan_application_id' => $app->id,
            'signer_type'         => 'borrower',
            'signer_name'         => $data['signer_name'],
            'signature_data'      => $data['signature_data'],
            'signed_at'           => now(),
        ]);

        if ($request->hasFile('income_document')) {
            $incomeType = DocumentType::firstOrCreate(
                ['code' => 'income_statement'],
                [
                    'name'       => 'Income statement (6 months)',
                    'category'   => 'kyc',
                    'applies_to' => 'individual',
                    'is_active'  => true,
                ]
            );

            $path = $request->file('income_document')->store(
                "borrower/{$customer->id}/documents",
                'public'
            );

            CustomerDocument::create([
                'customer_id'         => $customer->id,
                'loan_application_id'   => $app->id,
                'document_type_id'      => $incomeType->id,
                'file_path'             => $path,
                'status'                => 'pending',
            ]);
        }

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

        $this->auditBorrower('application.submitted', $app, [
            'product_id' => $loanProduct->id,
            'amount'     => $app->requested_amount,
            'status'     => $app->status,
        ]);

        return redirect()->route('site.borrower.apply.success', $app)->with('status', $message);
    }

    public function success(LoanApplication $application): View
    {
        abort_unless($application->customer && $application->customer->user_id === Auth::id(), 403);
        $application->load('product');
        $underwritingStages = app(SmartLoanApplicationWizardService::class)
            ->underwritingStages($application->current_stage ?? 'submitted');

        return view('site.apply.success', compact('application', 'underwritingStages'));
    }
}
