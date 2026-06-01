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
            'signatory_name'    => ['nullable', 'string', 'max:120'],
            'signatory_title'   => ['nullable', 'string', 'max:120'],
            'signature_path'    => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('signature_image')) {
            $data['signature_path'] = $request->file('signature_image')->store('company', 'public');
        }

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["company.$k" => $v])->all());
        return back()->with('status', 'Company profile saved.');
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

            'email_provider' => ['nullable', 'string', 'max:50'],
            'email_from_address' => ['nullable', 'email', 'max:150'],
            'email_from_name'    => ['nullable', 'string', 'max:150'],
            'email_smtp_host' => ['nullable', 'string', 'max:200'],
            'email_smtp_port' => ['nullable', 'integer'],
            'email_smtp_user' => ['nullable', 'string', 'max:200'],
            'email_smtp_pass' => ['nullable', 'string', 'max:255'],
            'email_encryption'=> ['nullable', 'string', 'max:10'],
        ]);

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
            'freshness_days'        => ['nullable', 'integer', 'min:30', 'max:365'],
        ]);

        foreach (['require_nida','require_tin','require_selfie','require_address_proof','require_income_proof','auto_approve_low_risk','crb_check_required','crb_sandbox'] as $k) {
            $data[$k] = (bool) ($data[$k] ?? false);
        }

        $data['freshness_days'] = (int) ($data['freshness_days'] ?? 90);

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
            'lock_hours'            => ['required', 'integer', 'min:1', 'max:168'],
        ]);

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

    public function loanProducts()
    {
        return redirect()->route('admin.loan-products.index');
    }

    public function saveLoanRules(Request $request)
    {
        $data = $request->validate([
            'default_grace_days'   => ['required', 'integer', 'min:0', 'max:90'],
            'default_penalty_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'penalty_basis'        => ['required', 'in:per_day,per_month,one_time'],
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
        ]);

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
            'cash_gl_account_id'             => ['nullable', 'exists:chart_of_accounts,id'],
            'loan_receivable_gl_account_id'  => ['nullable', 'exists:chart_of_accounts,id'],
            'fee_income_gl_account_id'       => ['nullable', 'exists:chart_of_accounts,id'],
            'interest_income_gl_account_id'  => ['nullable', 'exists:chart_of_accounts,id'],
            'penalty_income_gl_account_id'   => ['nullable', 'exists:chart_of_accounts,id'],
            'bad_debt_expense_gl_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'default_expense_gl_account_id'  => ['nullable', 'exists:chart_of_accounts,id'],
        ]);
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
}
