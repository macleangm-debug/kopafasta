<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.settings.company');
    }

    // ---------------- Company profile ----------------
    public function company()
    {
        return view('admin.settings.company', [
            'values' => Setting::group('company'),
        ]);
    }

    public function saveCompany(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'legal_name'  => ['nullable', 'string', 'max:200'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'tin'         => ['nullable', 'string', 'max:50'],
            'bot_licence' => ['nullable', 'string', 'max:100'],
            'tier'        => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:150'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'website'     => ['nullable', 'string', 'max:200'],
            'app_base_url' => ['nullable', 'url', 'max:255'],
            'address'     => ['nullable', 'string', 'max:500'],
            'currency'    => ['required', 'string', 'size:3'],
            'timezone'    => ['required', 'string', 'max:50'],
            'fiscal_year_start' => ['nullable', 'string', 'max:5'],   // MM-DD
        ]);

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["company.$k" => $v])->all());
        return back()->with('status', 'Company profile saved.');
    }

    public function legal()
    {
        return view('admin.settings.legal', [
            'values' => Setting::group('legal'),
        ]);
    }

    public function saveLegal(Request $request)
    {
        $data = $request->validate([
            'signatory_name'      => ['nullable', 'string', 'max:120'],
            'signatory_title'     => ['nullable', 'string', 'max:120'],
            'offer_validity_days' => ['required', 'integer', 'min:1', 'max:90'],
            'late_fee_amount'     => ['required', 'numeric', 'min:0'],
            'jurisdiction'        => ['required', 'string', 'max:200'],
            'collection_fee_text' => ['nullable', 'string', 'max:500'],
            'legal_recovery_text' => ['nullable', 'string', 'max:500'],
            'default_clause'      => ['nullable', 'string', 'max:2000'],
            'collection_clause'   => ['nullable', 'string', 'max:2000'],
            'recovery_clause'     => ['nullable', 'string', 'max:2000'],
            'penalty_clause'      => ['nullable', 'string', 'max:2000'],
            'legal_cost_clause'   => ['nullable', 'string', 'max:2000'],
            'guarantor_clause'    => ['nullable', 'string', 'max:2000'],
            'asset_recovery_clause' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($request->hasFile('signature_image')) {
            $data['signature_path'] = $request->file('signature_image')->store('legal', 'public');
        }

        if ($request->hasFile('stamp_image')) {
            $data['stamp_path'] = $request->file('stamp_image')->store('legal', 'public');
        }

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["legal.$k" => $v])->all());

        return back()->with('status', 'Legal settings saved.');
    }

    // ---------------- SMS / Email gateway ----------------
    public function gateways()
    {
        return view('admin.settings.gateways', [
            'values' => Setting::group('gateway'),
        ]);
    }

    public function saveGateways(Request $request)
    {
        $data = $request->validate([
            'sms_provider'  => ['nullable', 'string', 'max:50'],
            'sms_sender_id' => ['nullable', 'string', 'max:20'],
            'sms_api_key'   => ['nullable', 'string', 'max:255'],
            'sms_api_secret'=> ['nullable', 'string', 'max:255'],
            'sms_endpoint'  => ['nullable', 'url', 'max:255'],
            'staff_sms_alerts' => ['nullable', 'boolean'],

            'email_provider' => ['nullable', 'string', 'max:50'],
            'email_from_address' => ['nullable', 'email', 'max:150'],
            'email_from_name'    => ['nullable', 'string', 'max:150'],
            'email_smtp_host' => ['nullable', 'string', 'max:200'],
            'email_smtp_port' => ['nullable', 'integer'],
            'email_smtp_user' => ['nullable', 'string', 'max:200'],
            'email_smtp_pass' => ['nullable', 'string', 'max:255'],
            'email_encryption'=> ['nullable', 'string', 'max:10'],
        ]);

        $data['staff_sms_alerts'] = (bool) ($data['staff_sms_alerts'] ?? false);

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["gateway.$k" => $v])->all());
        return back()->with('status', 'Gateway settings saved.');
    }

    // ---------------- KYC requirements ----------------
    public function kyc()
    {
        return view('admin.settings.kyc', [
            'values' => Setting::group('kyc'),
        ]);
    }

    public function saveKyc(Request $request)
    {
        $data = $request->validate([
            'require_nida'      => ['nullable', 'boolean'],
            'require_tin'       => ['nullable', 'boolean'],
            'require_selfie'    => ['nullable', 'boolean'],
            'require_address_proof' => ['nullable', 'boolean'],
            'require_income_proof'  => ['nullable', 'boolean'],
            'min_age'  => ['required', 'integer', 'min:18', 'max:100'],
            'max_age'  => ['required', 'integer', 'min:18', 'max:120'],
            'auto_approve_low_risk' => ['nullable', 'boolean'],
            'crb_check_required'    => ['nullable', 'boolean'],
            'crb_sandbox'           => ['nullable', 'boolean'],
            'crb_endpoint'          => ['nullable', 'url', 'max:255'],
            'crb_email'             => ['nullable', 'string', 'max:150'],
            'crb_freshness_days'    => ['nullable', 'integer', 'min:30', 'max:365'],
            'freshness_section_days' => ['nullable', 'array'],
            'freshness_section_days.*' => ['nullable'],
        ]);

        foreach (['require_nida','require_tin','require_selfie','require_address_proof','require_income_proof','auto_approve_low_risk','crb_check_required','crb_sandbox'] as $k) {
            $data[$k] = (bool) ($data[$k] ?? false);
        }

        $sectionDays = [];
        foreach ($data['freshness_section_days'] ?? [] as $section => $value) {
            if ($value === null || $value === '' || strtolower((string) $value) === 'never') {
                $sectionDays[$section] = 'never';

                continue;
            }

            $sectionDays[$section] = max(30, min(3650, (int) $value));
        }

        $data['freshness_section_days'] = $sectionDays;
        $data['freshness_days'] = (int) ($sectionDays['activity'] ?? $sectionDays['residence'] ?? 90);
        $data['crb_freshness_days'] = (int) ($data['crb_freshness_days'] ?? 90);
        $data['require_residence_letter'] = (bool) ($data['require_address_proof'] ?? false);

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["kyc.$k" => $v])->all());
        return back()->with('status', 'KYC settings saved.');
    }

    public function identityVerification()
    {
        return view('admin.settings.identity', [
            'values' => Setting::group('identity_verification'),
        ]);
    }

    public function saveIdentityVerification(Request $request)
    {
        $data = $request->validate([
            'max_mismatch_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'lock_days'             => ['required', 'integer', 'min:1', 'max:365'],
            'require_dob'           => ['nullable', 'boolean'],
        ]);
        $data['require_dob'] = (bool) ($data['require_dob'] ?? false);

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["identity_verification.$k" => $v])->all());

        return back()->with('status', 'Identity verification settings saved.');
    }

    // ---------------- Loan rules ----------------
    public function loanRules()
    {
        return view('admin.settings.loan-rules', [
            'values' => Setting::group('loan'),
        ]);
    }

    public function offer()
    {
        return view('admin.settings.offer', [
            'values' => Setting::group('offer'),
        ]);
    }

    public function saveOffer(Request $request)
    {
        $data = $request->validate([
            'require_offer_acceptance_code'    => ['nullable', 'boolean'],
            'require_contract_acceptance_code' => ['nullable', 'boolean'],
            'repayment_commencement_days'      => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        foreach (['require_offer_acceptance_code', 'require_contract_acceptance_code'] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["offer.$k" => $v])->all());

        return back()->with('status', 'Offer settings saved.');
    }

    public function loanProducts()
    {
        return redirect()->route('admin.loan-products.index');
    }

    public function saveLoanRules(Request $request)
    {
        $data = $request->validate([
            'default_grace_days'   => ['required', 'integer', 'min:0', 'max:90'],
            'default_penalty_rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'penalty_basis'        => ['required', 'in:per_day,per_month,one_time'],
            'penalty_cap_percent'  => ['required', 'numeric', 'min:0', 'max:30'],
            'max_tenure_months'    => ['required', 'integer', 'min:1', 'max:120'],
            'min_tenure_months'    => ['required', 'integer', 'min:1'],
            'max_loan_amount'      => ['required', 'numeric', 'min:0'],
            'min_loan_amount'      => ['required', 'numeric', 'min:0'],
            'guarantor_required_above' => ['nullable', 'numeric', 'min:0'],
            'collateral_required_above' => ['nullable', 'numeric', 'min:0'],
            'min_guarantors' => ['required', 'integer', 'min:0', 'max:10'],
            'allow_restructure' => ['nullable', 'boolean'],
            'max_restructures'  => ['required', 'integer', 'min:0', 'max:10'],
            'restructure_cooldown_days' => ['required', 'integer', 'min:0'],
            'qualification_income_multiplier'       => ['nullable', 'numeric', 'min:0', 'max:20'],
            'qualification_max_cap'                 => ['nullable', 'integer', 'min:0'],
            'qualification_good_history_multiplier'   => ['nullable', 'numeric', 'min:1', 'max:5'],
            'qualification_good_history_cap'        => ['nullable', 'integer', 'min:0'],
            'qualification_membership_inactive_factor'=> ['nullable', 'numeric', 'min:0', 'max:1'],
            'qualification_kyc_incomplete_factor'   => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qualification_min_profile_percent'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_active_applications_per_product'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_active_guarantees'                 => ['nullable', 'integer', 'min:1', 'max:20'],
            'allow_asset_reuse'                     => ['nullable', 'boolean'],
            'top_up_min_successful_repayments'      => ['nullable', 'integer', 'min:0', 'max:60'],
            'payment_holiday_accrue_interest'       => ['nullable', 'boolean'],
            'payment_holiday_max_months'            => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $data['allow_asset_reuse'] = (bool) ($data['allow_asset_reuse'] ?? false);
        $data['payment_holiday_accrue_interest'] = (bool) ($data['payment_holiday_accrue_interest'] ?? false);
        $data['payment_holiday_max_months'] = (int) ($data['payment_holiday_max_months'] ?? 3);
        $data['max_active_applications_per_product'] = (int) ($data['max_active_applications_per_product'] ?? 1);
        $data['max_active_guarantees'] = (int) ($data['max_active_guarantees'] ?? 5);
        $data['top_up_min_successful_repayments'] = (int) ($data['top_up_min_successful_repayments'] ?? 6);

        $data['allow_restructure'] = (bool) ($data['allow_restructure'] ?? false);
        $data['qualification_income_multiplier'] = (float) ($data['qualification_income_multiplier'] ?? 4);
        $data['qualification_max_cap'] = (int) ($data['qualification_max_cap'] ?? 5_000_000);
        $data['qualification_good_history_multiplier'] = (float) ($data['qualification_good_history_multiplier'] ?? 1.5);
        $data['qualification_good_history_cap'] = (int) ($data['qualification_good_history_cap'] ?? 7_500_000);
        $data['qualification_membership_inactive_factor'] = (float) ($data['qualification_membership_inactive_factor'] ?? 0);
        $data['qualification_kyc_incomplete_factor'] = (float) ($data['qualification_kyc_incomplete_factor'] ?? 0.5);
        $data['qualification_min_profile_percent'] = (int) ($data['qualification_min_profile_percent'] ?? 60);

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["loan.$k" => $v])->all());
        return back()->with('status', 'Loan rules saved.');
    }

    public function underwriting()
    {
        return view('admin.settings.underwriting', [
            'values' => Setting::group('underwriting'),
        ]);
    }

    public function saveUnderwriting(Request $request)
    {
        $data = $request->validate([
            'guarantor_invitation_expiry_days'       => ['required', 'integer', 'min:1', 'max:90'],
            'document_request_default_due_days'      => ['required', 'integer', 'min:1', 'max:60'],
            'stage_sla_days'                         => ['required', 'integer', 'min:1', 'max:60'],
            'default_rate_tier_count'                => ['required', 'integer', 'min:2', 'max:8'],
            'default_rate_discount_fraction'         => ['required', 'numeric', 'min:0', 'max:0.85'],
            'hold_applications_until_guarantor_approved' => ['nullable', 'boolean'],
            'block_acknowledge_without_guarantor'    => ['nullable', 'boolean'],
            'enable_counter_offers'                  => ['nullable', 'boolean'],
            'enable_asset_backed_alternative'        => ['nullable', 'boolean'],
            'enable_automatic_rejection'             => ['nullable', 'boolean'],
        ]);

        foreach ([
            'hold_applications_until_guarantor_approved',
            'block_acknowledge_without_guarantor',
            'enable_counter_offers',
            'enable_asset_backed_alternative',
            'enable_automatic_rejection',
        ] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["underwriting.$k" => $v])->all());

        return back()->with('status', 'Underwriting settings saved.');
    }

    // ---------------- AML thresholds ----------------
    public function amlSettings()
    {
        return view('admin.settings.aml', [
            'values' => Setting::group('aml'),
            'users'  => \App\Models\User::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function saveAmlSettings(Request $request)
    {
        $data = $request->validate([
            'large_txn_threshold_tzs' => ['required', 'numeric', 'min:0'],
            'large_txn_threshold_usd' => ['required', 'numeric', 'min:0'],
            'velocity_threshold_count'=> ['required', 'integer', 'min:1'],
            'velocity_window_days'    => ['required', 'integer', 'min:1'],
            'auto_report_to_fiu'      => ['nullable', 'boolean'],
            'fiu_email'   => ['nullable', 'email', 'max:150'],
            'mlro_user_id'=> ['nullable', 'exists:users,id'],
        ]);
        $data['auto_report_to_fiu'] = (bool) ($data['auto_report_to_fiu'] ?? false);

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["aml.$k" => $v])->all());
        return back()->with('status', 'AML settings saved.');
    }

    public function finance()
    {
        return view('admin.settings.finance', [
            'values'   => Setting::group('finance'),
            'accounts' => \App\Models\ChartOfAccount::orderBy('code')->get(['id','code','name','type']),
        ]);
    }

    public function saveFinance(Request $request)
    {
        $data = $request->validate([
            'cash_gl_account_id'                      => ['nullable', 'exists:chart_of_accounts,id'],
            'customer_gl_account_id'                  => ['nullable', 'exists:chart_of_accounts,id'],
            'loan_receivable_gl_account_id'           => ['nullable', 'exists:chart_of_accounts,id'],
            'fee_income_gl_account_id'                => ['nullable', 'exists:chart_of_accounts,id'],
            'registration_fee_income_gl_account_id'   => ['nullable', 'exists:chart_of_accounts,id'],
            'application_fee_income_gl_account_id'    => ['nullable', 'exists:chart_of_accounts,id'],
            'interest_income_gl_account_id'           => ['nullable', 'exists:chart_of_accounts,id'],
            'penalty_income_gl_account_id'            => ['nullable', 'exists:chart_of_accounts,id'],
            'bad_debt_expense_gl_account_id'          => ['nullable', 'exists:chart_of_accounts,id'],
            'default_expense_gl_account_id'           => ['nullable', 'exists:chart_of_accounts,id'],
            'capital_partner_pool_gl_account_id'      => ['nullable', 'exists:chart_of_accounts,id'],
            'capital_partner_interest_share_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'write_off_approval_required'             => ['nullable', 'boolean'],
        ]);
        $data['write_off_approval_required'] = $request->boolean('write_off_approval_required');
        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["finance.$k" => $v])->all());
        return back()->with('status', 'Finance defaults saved.');
    }

    // ---------------- Membership ----------------
    public function membership()
    {
        return view('admin.settings.membership', [
            'values' => Setting::group('membership'),
        ]);
    }

    public function saveMembership(Request $request)
    {
        $data = $request->validate([
            'duration_days'     => ['required', 'integer', 'min:30', 'max:3650'],
            'renewal_fee'       => ['required', 'numeric', 'min:0'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:90'],
            'max_expiry_years'  => ['required', 'integer', 'min:1', 'max:10'],
            'currency'          => ['required', 'string', 'size:3'],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => ['in:sms,email,push,whatsapp'],
        ]);

        $data['reminder_channels'] = $data['reminder_channels'] ?? [];

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["membership.$k" => $v])->all());
        return back()->with('status', 'Membership settings saved.');
    }

    public function referrals()
    {
        return view('admin.settings.referrals', [
            'values' => Setting::group('referrals'),
        ]);
    }

    public function saveReferrals(Request $request)
    {
        $data = $request->validate([
            'code_prefix'            => ['required', 'string', 'max:10'],
            'discount_percent'       => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_percent'     => ['required', 'numeric', 'min:0', 'max:100'],
            'wallet_max_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["referrals.$k" => $v])->all());

        return back()->with('status', 'Referral settings saved.');
    }

    public function creditPolicy()
    {
        $countryService = app(\App\Services\CountryCreditSettingsService::class);
        $reasonService = app(\App\Services\LoanRejectionReasonService::class);
        $configured = Setting::get('rejection.reasons');
        $enabledCodes = is_array($configured) && $configured !== []
            ? collect($configured)->pluck('code')->all()
            : collect($reasonService->defaults())->pluck('code')->all();

        return view('admin.settings.credit-policy', [
            'country'           => $countryService->summary(),
            'rejectionReasons'  => $reasonService->grouped(),
            'enabledCodes'      => $enabledCodes,
        ]);
    }

    public function saveCreditPolicy(Request $request)
    {
        $data = $request->validate([
            'default_code'          => ['required', 'string', 'size:2'],
            'repayment_ratio_pct'   => ['required', 'numeric', 'min:1', 'max:100'],
            'crb_freshness_days'    => ['required', 'integer', 'min:30', 'max:365'],
            'kyc_freshness_days'    => ['required', 'integer', 'min:30', 'max:365'],
            'guarantor_required'    => ['nullable', 'boolean'],
            'enabled_reasons'       => ['nullable', 'array'],
            'enabled_reasons.*'     => ['string', 'max:80'],
        ]);

        $code = strtolower($data['default_code']);
        $ratio = round((float) $data['repayment_ratio_pct'] / 100, 4);

        Setting::setMany([
            'country.default_code'              => strtoupper($data['default_code']),
            "country.{$code}.repayment_ratio"   => $ratio,
            'credit.repayment_ratio'            => $ratio,
            "country.{$code}.crb_freshness_days"=> (int) $data['crb_freshness_days'],
            "country.{$code}.kyc_freshness_days"=> (int) $data['kyc_freshness_days'],
            "country.{$code}.guarantor_required" => (bool) ($data['guarantor_required'] ?? false),
        ]);

        $defaults = collect(app(\App\Services\LoanRejectionReasonService::class)->defaults());
        $enabled = collect($data['enabled_reasons'] ?? []);
        $reasons = $defaults
            ->filter(fn (array $row) => $enabled->contains($row['code']))
            ->values()
            ->all();

        Setting::set('rejection.reasons', $reasons ?: $defaults->all());

        return back()->with('status', 'Credit policy saved.');
    }
}
