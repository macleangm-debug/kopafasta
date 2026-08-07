<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.hub');
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
            'whatsapp'    => ['nullable', 'string', 'max:30'],
            'website'     => ['nullable', 'string', 'max:200'],
            'app_base_url' => ['nullable', 'url', 'max:255'],
            'address'     => ['nullable', 'string', 'max:500'],
            'currency'    => ['required', 'string', 'size:3'],
            'timezone'    => ['required', 'string', 'max:50'],
            'fiscal_year_start' => ['nullable', 'string', 'max:5'],   // MM-DD
            'thousands_separator' => ['nullable', 'string', \Illuminate\Validation\Rule::in([',', '.', ' '])],
            'decimal_separator' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['.', ','])],
        ]);

        if (($data['thousands_separator'] ?? ',') === ($data['decimal_separator'] ?? '.')) {
            return back()->withInput()->withErrors([
                'decimal_separator' => 'Thousands and decimal separators must be different.',
            ]);
        }

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["company.$k" => $v])->all());
        return back()->with('status', 'Company profile saved.');
    }

    public function legal()
    {
        return view('admin.settings.legal', [
            'values' => Setting::group('legal'),
            'contractSections' => app(\App\Services\LegalSettingsService::class)->contractSections(),
            'sectionLabels' => [
                'definitions'           => 'Definitions',
                'loan_terms'            => 'Loan terms',
                'repayment_obligations' => 'Repayment obligations',
                'default_events'        => 'Default events',
                'penalty_clauses'       => 'Penalty clauses',
                'recovery_clauses'      => 'Recovery clauses',
                'guarantor_obligations' => 'Guarantor obligations',
                'legal_costs'           => 'Legal costs',
                'jurisdiction'          => 'Jurisdiction',
                'data_privacy'          => 'Data privacy',
                'signatures'            => 'Signatures',
            ],
        ]);
    }

    public function saveLegal(Request $request)
    {
        $data = $request->validate([
            'signatory_name'      => ['nullable', 'string', 'max:120'],
            'signatory_title'     => ['nullable', 'string', 'max:120'],
            'signatory_email'     => ['nullable', 'email', 'max:150'],
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
            'contract_sections'   => ['nullable', 'array'],
            'contract_sections.*' => ['nullable', 'boolean'],
            'signature_image'     => ['nullable', 'image', 'mimes:png,jpeg,jpg,webp', 'max:5120'],
            'stamp_image'         => ['nullable', 'image', 'mimes:png,jpeg,jpg,webp', 'max:5120'],
            'remove_stamp'        => ['nullable', 'boolean'],
        ]);

        $existing = Setting::group('legal');

        if ($request->boolean('remove_stamp') && ! empty($existing['stamp_path'])) {
            Storage::disk('public')->delete($existing['stamp_path']);
            $data['stamp_path'] = '';
        }

        if ($request->hasFile('signature_image')) {
            if (! empty($existing['signature_path'])) {
                Storage::disk('public')->delete($existing['signature_path']);
            }
            $data['signature_path'] = $request->file('signature_image')->store('legal', 'public');
        }

        if ($request->hasFile('stamp_image')) {
            if (! empty($existing['stamp_path'])) {
                Storage::disk('public')->delete($existing['stamp_path']);
            }
            $data['stamp_path'] = $request->file('stamp_image')->store('legal', 'public');
        }

        unset($data['remove_stamp']);

        $sectionKeys = [
            'definitions', 'loan_terms', 'repayment_obligations', 'default_events',
            'penalty_clauses', 'recovery_clauses', 'guarantor_obligations',
            'legal_costs', 'jurisdiction', 'data_privacy', 'signatures',
        ];
        $sections = collect($sectionKeys)
            ->mapWithKeys(fn (string $key) => [$key => $request->boolean("contract_sections.$key")])
            ->all();
        unset($data['contract_sections']);

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["legal.$k" => $v])->all());
        Setting::set('legal.contract_sections', $sections);

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
        \Illuminate\Support\Facades\Cache::forget('sms.settings.v1');

        return back()->with('status', 'Gateway settings saved.');
    }

    public function smsHealth(\App\Services\Integrations\IntegrationHealthService $health)
    {
        $result = $health->check('unitxt', notifyOnFailure: true);

        return redirect()
            ->route('admin.settings.gateways')
            ->with('sms_health', $result)
            ->with('status', ($result['ok'] ?? false) ? 'SMS connection check completed.' : 'SMS connection check failed.');
    }

    // ---------------- Integrations hub ----------------
    public function integrations(\App\Services\Integrations\IntegrationCatalog $catalog)
    {
        return view('admin.settings.integrations', [
            'groups' => $catalog->grouped(),
            'categories' => $catalog->categories(),
            'channelOptions' => $catalog->channelOptions(),
        ]);
    }

    public function createIntegrationPartner(\App\Services\Integrations\IntegrationCatalog $catalog)
    {
        return view('admin.settings.integrations-partner-create', [
            'categories' => $catalog->categories(),
            'channelOptions' => $catalog->channelOptions(),
        ]);
    }

    public function showIntegrationPartner(
        string $partner,
        Request $request,
        \App\Services\Integrations\IntegrationCatalog $catalog,
        \App\Services\Integrations\IntegrationUsageService $usage,
        \App\Services\Integrations\IntegrationHealthService $health,
    ) {
        $meta = $catalog->partner($partner);
        abort_unless($meta, 404);

        $tab = $request->query('tab', 'configuration');
        if (! in_array($tab, ['configuration', 'usage'], true)) {
            $tab = 'configuration';
        }

        $category = $meta['category'] ?? null;
        $meta['is_primary'] = $category
            ? $catalog->primaryKey($category) === $partner
            : false;

        $payinView = null;
        $crbView = null;
        if ($partner === 'payin') {
            $payinView = $this->payinViewData();
        }
        if ($partner === 'crb') {
            $crbView = $this->crbViewData();
        }

        return view('admin.settings.integrations-partner', [
            'partnerKey' => $partner,
            'partner' => $meta,
            'tab' => $tab,
            'health' => $health->lastStatus($partner),
            'usage' => $usage->usage($partner, $meta['category'] ?? null),
            'billing' => $usage->billing($partner),
            'channelOptions' => $catalog->channelOptions(),
            'payin' => $payinView,
            'crb' => $crbView,
        ]);
    }

    public function saveIntegrationBilling(
        string $partner,
        Request $request,
        \App\Services\Integrations\IntegrationCatalog $catalog,
        \App\Services\Integrations\IntegrationUsageService $usage,
    ) {
        abort_unless($catalog->partner($partner), 404);

        $category = $catalog->partner($partner)['category'] ?? 'payment';

        $data = match ($category) {
            'payment' => $request->validate([
                'collection_fee_type' => ['required', 'in:percent,fixed'],
                'collection_fee_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
                'disbursement_fee_type' => ['required', 'in:percent,fixed'],
                'disbursement_fee_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            ]),
            'messaging' => $request->validate([
                'sms_fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'email_fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            ]),
            'compliance' => $request->validate([
                'included_units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'package_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
                'overage_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            ]),
            default => [],
        };

        if ($category === 'payment') {
            $data['collection_fee_value'] = \App\Support\MoneyFormat::toNumber($request->input('collection_fee_value'));
            $data['disbursement_fee_value'] = \App\Support\MoneyFormat::toNumber($request->input('disbursement_fee_value'));
        }

        $usage->saveBilling($partner, $data);

        return redirect()
            ->route('admin.settings.integrations.partner', ['partner' => $partner, 'tab' => 'usage'])
            ->with('feedback', [
                'tone' => 'success',
                'title' => 'Billing saved',
                'message' => 'Partner charge model updated for usage estimates.',
            ]);
    }

    public function saveIntegrationsPrimary(Request $request, \App\Services\Integrations\IntegrationCatalog $catalog)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:40'],
            'partner' => ['required', 'string', 'max:40'],
        ]);

        $catalog->setPrimary($data['category'], $data['partner']);

        return back()->with('feedback', [
            'tone' => 'success',
            'title' => 'Primary partner updated',
            'message' => 'Primary '.($catalog->categories()[$data['category']]['label'] ?? $data['category']).' partner updated.',
        ]);
    }

    public function saveIntegrationChannels(Request $request, \App\Services\Integrations\IntegrationCatalog $catalog)
    {
        $data = $request->validate([
            'partner' => ['required', 'string', 'max:60'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:mobile_money,bank'],
        ]);

        $catalog->setPartnerChannels($data['partner'], $data['channels']);

        return back()->with('feedback', [
            'tone' => 'success',
            'title' => 'Channels saved',
            'message' => 'Payment channels updated for this partner.',
        ]);
    }

    public function storeIntegrationPartner(Request $request, \App\Services\Integrations\IntegrationCatalog $catalog)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
            'docs_url' => ['nullable', 'url', 'max:255'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['in:mobile_money,bank'],
        ]);

        if (($data['category'] ?? '') === 'payment' && empty($data['channels'])) {
            return back()->withInput()->withErrors([
                'channels' => 'Select at least one rail (mobile money and/or bank transfer).',
            ]);
        }

        try {
            $key = $catalog->addCustomPartner($data);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('feedback', [
                'tone' => 'error',
                'title' => 'Could not add partner',
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.settings.integrations.partner', ['partner' => $key, 'tab' => 'configuration'])
            ->with('feedback', [
                'tone' => 'success',
                'title' => 'Partner added',
                'message' => "Added {$data['label']}. Configure credentials and billing next.",
            ]);
    }

    public function checkIntegrationHealth(
        Request $request,
        \App\Services\Integrations\IntegrationHealthService $health,
    ) {
        $data = $request->validate([
            'partner' => ['nullable', 'string', 'max:40'],
        ]);

        if (filled($data['partner'] ?? null)) {
            $result = $health->check($data['partner'], notifyOnFailure: true);
            $ok = (bool) ($result['ok'] ?? false);

            return back()->with('feedback', [
                'tone' => $ok ? 'success' : 'error',
                'title' => $ok ? 'Connection healthy' : 'Connection failed',
                'message' => (string) ($result['message'] ?? ''),
                'lines' => $result['guidance'] ?? [],
            ]);
        }

        $results = $health->checkAll(notifyOnFailure: true);
        $failed = collect($results)->where('ok', false)->values();

        return back()->with('feedback', [
            'tone' => $failed->isEmpty() ? 'success' : 'error',
            'title' => $failed->isEmpty() ? 'All integrations healthy' : 'Some integrations failed',
            'message' => $failed->isEmpty()
                ? 'All available integrations passed health checks.'
                : $failed->count().' integration(s) need attention.',
            'lines' => $failed->map(fn ($row) => strtoupper($row['key']).': '.$row['message'])->all(),
        ]);
    }

    // ---------------- PayIn payments ----------------
    public function payin(\App\Services\PayInService $payIn)
    {
        return redirect()->route('admin.settings.integrations.partner', [
            'partner' => 'payin',
            'tab' => 'configuration',
        ]);
    }

    /** @return array<string, mixed> */
    protected function payinViewData(): array
    {
        $values = Setting::group('payin');
        $catalog = app(\App\Services\Integrations\IntegrationCatalog::class);
        $partner = $catalog->partner('payin');
        $channels = $partner['channels'] ?? ['mobile_money'];
        $configured = filled($values['api_key'] ?? null) && filled($values['api_secret'] ?? null);

        return [
            'values' => array_merge([
                'enabled' => false,
                'environment' => 'sandbox',
                'api_key' => '',
                'api_secret' => '',
                'webhook_secret' => '',
                'default_callback_url' => '',
            ], $values),
            'gatewayMode' => Setting::get('payments.gateway_mode') ?? config('payments.gateway_mode', 'dummy'),
            'mobileMoneyThreshold' => payment_mobile_money_threshold(),
            'defaultWebhookUrl' => route('webhooks.payin'),
            'payinChannels' => $channels,
            'channelOptions' => $catalog->channelOptions(),
            'isConfigured' => $configured,
            'health' => app(\App\Services\Integrations\IntegrationHealthService::class)->lastStatus('payin'),
        ];
    }

    public function savePayin(Request $request)
    {
        $request->merge([
            'mobile_money_threshold' => (int) round(\App\Support\MoneyFormat::toNumber($request->input('mobile_money_threshold'))),
        ]);

        $data = $request->validate([
            'environment' => ['required', 'in:sandbox,production'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'default_callback_url' => ['nullable', 'url', 'max:255'],
            'gateway_mode' => ['required', 'in:dummy,live'],
            'mobile_money_threshold' => ['required', 'integer', 'min:0', 'max:100000000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:mobile_money,bank'],
            'intent' => ['nullable', 'in:save,save_and_test'],
        ]);

        $intent = $data['intent'] ?? 'save';
        unset($data['intent']);

        $gatewayMode = $data['gateway_mode'];
        $threshold = (int) $data['mobile_money_threshold'];
        $channels = array_values($data['channels']);
        unset($data['gateway_mode'], $data['mobile_money_threshold'], $data['channels']);

        // Supported rails replace the old "enable" toggle: mobile money rail = PayIn collections on.
        $data['enabled'] = in_array('mobile_money', $channels, true);

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["payin.$k" => $v])->all());
        Setting::set('payments.gateway_mode', $gatewayMode);
        Setting::set('payments.mobile_money_threshold', $threshold);
        app(\App\Services\Integrations\IntegrationCatalog::class)->setPartnerChannels('payin', $channels);
        config([
            'payments.gateway_mode' => $gatewayMode,
            'payments.mobile_money_threshold' => $threshold,
        ]);

        if ($intent === 'save_and_test') {
            $result = app(\App\Services\Integrations\IntegrationHealthService::class)
                ->check('payin', notifyOnFailure: true);

            $ok = (bool) ($result['ok'] ?? false);
            $lines = array_values(array_filter([
                $result['message'] ?? null,
            ]));
            foreach ($result['guidance'] ?? [] as $tip) {
                $lines[] = $tip;
            }
            if ($ok && $gatewayMode !== 'live') {
                $lines[] = 'Gateway mode is still Dummy — switch to Live and save before real USSD payments.';
            }

            return redirect()
                ->route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration'])
                ->with('feedback', [
                    'tone' => $ok ? ($gatewayMode === 'live' ? 'success' : 'warning') : 'error',
                    'title' => $ok ? 'PayIn connected' : 'PayIn connection failed',
                    'message' => $ok
                        ? 'Settings saved and PayIn responded successfully.'
                        : 'Settings were saved, but the connection check failed.',
                    'lines' => $lines,
                ]);
        }

        $lines = [];
        if ($gatewayMode !== 'live') {
            $lines[] = 'Gateway mode is Dummy — borrowers will not get live USSD. Switch to Live for real payments.';
        }

        return redirect()
            ->route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration'])
            ->with('feedback', [
                'tone' => $gatewayMode === 'live' ? 'success' : 'warning',
                'title' => 'Settings saved',
                'message' => 'PayIn settings saved. Fields are locked — click Edit to change.',
                'lines' => $lines,
            ]);
    }

    public function payinHealth(\App\Services\Integrations\IntegrationHealthService $health)
    {
        $result = $health->check('payin', notifyOnFailure: true);
        $ok = (bool) ($result['ok'] ?? false);

        return redirect()
            ->route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration'])
            ->with('feedback', [
                'tone' => $ok ? 'success' : 'error',
                'title' => $ok ? 'PayIn connected' : 'PayIn connection failed',
                'message' => (string) ($result['message'] ?? ($ok ? 'Connection healthy.' : 'Connection failed.')),
                'lines' => $result['guidance'] ?? [],
            ]);
    }

    // ---------------- Transactional messaging ----------------
    public function messaging(\App\Services\Messaging\TransactionalMessagingService $messaging)
    {
        return view('admin.settings.messaging', $messaging->formValues());
    }

    public function saveMessaging(Request $request, \App\Services\Messaging\TransactionalMessagingService $messaging)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'force_log_driver' => ['nullable', 'boolean'],
            'overdue_reminders' => ['nullable', 'boolean'],
            'reminder_offsets_days' => ['nullable', 'string', 'max:40'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['nullable'],
            'events' => ['nullable', 'array'],
            'whatsapp' => ['nullable', 'array'],
            'whatsapp.provider' => ['nullable', 'string', 'max:40'],
            'whatsapp.api_url' => ['nullable', 'string', 'max:255'],
            'whatsapp.api_token' => ['nullable', 'string', 'max:255'],
            'whatsapp.from_number' => ['nullable', 'string', 'max:40'],
        ]);

        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['force_log_driver'] = (bool) ($data['force_log_driver'] ?? false);
        $data['overdue_reminders'] = (bool) ($data['overdue_reminders'] ?? false);

        $messaging->save($data);

        return back()->with('status', 'Transactional messaging settings saved.');
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

    public function crb()
    {
        return redirect()->route('admin.settings.integrations.partner', [
            'partner' => 'crb',
            'tab' => 'configuration',
        ]);
    }

    /** @return array<string, mixed> */
    protected function crbViewData(): array
    {
        $values = Setting::group('kyc');
        $sample = config('crb_samples.scenarios.verified', []);

        return [
            'values' => $values,
            'driver' => config('crb.driver'),
            'usesStub' => app(\App\Services\CrbService::class)->usesStub(),
            'sampleNida' => $sample['nida'] ?? '19810713-00001-23456-78',
            'sampleLabel' => $sample['label'] ?? 'Single hit (verified)',
        ];
    }

    public function saveCrb(Request $request)
    {
        $data = $request->validate([
            'crb_check_required'    => ['nullable', 'boolean'],
            'crb_sandbox'           => ['nullable', 'boolean'],
            'crb_endpoint'          => ['nullable', 'url', 'max:255'],
            'crb_email'             => ['nullable', 'string', 'max:150'],
            'crb_freshness_days'    => ['required', 'integer', 'min:30', 'max:365'],
            'crb_cost_per_request'  => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);

        foreach (['crb_check_required', 'crb_sandbox'] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }

        Setting::setMany(collect($data)->mapWithKeys(fn ($value, $key) => ["kyc.$key" => $value])->all());

        return redirect()
            ->route('admin.settings.integrations.partner', ['partner' => 'crb', 'tab' => 'configuration'])
            ->with('feedback', [
                'tone' => 'success',
                'title' => 'CRB settings saved',
                'message' => 'Fields are locked — click Edit settings to change. Usage & billing tracks requests and package pricing.',
            ]);
    }

    public function testCrbConnection()
    {
        $sample = config('crb_samples.scenarios.verified', []);
        $nida = (string) ($sample['nida'] ?? '19810713-00001-23456-78');

        $result = app(\App\Services\CrbService::class)->verifyConsumerIdentity(
            $nida,
            $sample['full_name'] ?? null,
            $sample['date_of_birth'] ?? null,
        );

        if ($result->success) {
            $driverLabel = $result->raw['driver'] ?? (app(\App\Services\CrbService::class)->usesStub() ? 'stub' : 'live');

            return redirect()
                ->route('admin.settings.integrations.partner', ['partner' => 'crb', 'tab' => 'configuration'])
                ->with('feedback', [
                    'tone' => 'success',
                    'title' => 'CRB test succeeded',
                    'message' => $driverLabel.': '.$result->fullName,
                ]);
        }

        return redirect()
            ->route('admin.settings.integrations.partner', ['partner' => 'crb', 'tab' => 'configuration'])
            ->with('feedback', [
                'tone' => 'error',
                'title' => 'CRB test failed',
                'message' => $result->message ?? 'CRB test failed.',
            ]);
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
            'require_facial'        => ['nullable', 'boolean'],
            'require_nida'          => ['nullable', 'boolean'],
            'verification_stage'    => ['required', 'in:profile_creation,underwriting'],
        ]);
        $data['require_dob'] = (bool) ($data['require_dob'] ?? false);
        $data['require_facial'] = (bool) ($data['require_facial'] ?? true);
        $data['require_nida'] = (bool) ($data['require_nida'] ?? true);

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

    public function authPortal()
    {
        $values = app(\App\Services\AuthPortalSettingsService::class)->forForm();
        $values['turnstile_site_key'] = \App\Models\Setting::get('security.turnstile_site_key')
            ?? config('security.turnstile_site_key', '');
        $values['turnstile_secret_key'] = \App\Models\Setting::get('security.turnstile_secret_key')
            ?? config('security.turnstile_secret_key', '');

        return view('admin.settings.auth-portal', [
            'values' => $values,
        ]);
    }

    public function saveAuthPortal(Request $request)
    {
        $data = $request->validate([
            'require_2fa_admin'        => ['nullable', 'boolean'],
            'require_2fa_staff'        => ['nullable', 'boolean'],
            'require_2fa_partner'      => ['nullable', 'boolean'],
            'two_factor_session_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'turnstile_site_key'       => ['nullable', 'string', 'max:255'],
            'turnstile_secret_key'     => ['nullable', 'string', 'max:255'],
        ]);

        Setting::setMany([
            'auth_portal.require_2fa_admin'        => $request->boolean('require_2fa_admin'),
            'auth_portal.require_2fa_staff'        => $request->boolean('require_2fa_staff'),
            'auth_portal.require_2fa_partner'      => $request->boolean('require_2fa_partner'),
            'auth_portal.two_factor_session_hours' => (int) $data['two_factor_session_hours'],
            'security.turnstile_site_key'          => trim((string) ($data['turnstile_site_key'] ?? '')),
            'security.turnstile_secret_key'        => trim((string) ($data['turnstile_secret_key'] ?? '')),
        ]);

        return back()->with('status', 'Authentication settings saved.');
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
            'qualification_new_member_floor'        => ['nullable', 'integer', 'min:0'],
            'max_active_applications_per_product'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_active_loans'                        => ['nullable', 'integer', 'min:1', 'max:5'],
            'max_active_guarantees'                 => ['nullable', 'integer', 'min:1', 'max:20'],
            'allow_asset_reuse'                     => ['nullable', 'boolean'],
            'top_up_min_successful_repayments'      => ['nullable', 'integer', 'min:0', 'max:60'],
            'payment_holiday_accrue_interest'       => ['nullable', 'boolean'],
            'payment_holiday_max_months'            => ['nullable', 'integer', 'min:1', 'max:12'],
            'group_min_members'                     => ['required', 'integer', 'min:3', 'max:100'],
            'group_max_members'                     => ['required', 'integer', 'min:3', 'max:200'],
            'group_repayment_cadence'               => ['required', 'in:weekly,monthly'],
            'group_leader_unlock_repayments'        => ['required', 'integer', 'min:1', 'max:12'],
            'group_unlock_days'                     => ['nullable', 'integer', 'min:0', 'max:365'],
            'group_payout_order'                    => ['required', 'in:leader_first,leader_last,manual,random,rotation,committee'],
            'group_application_fee_per_member'      => ['nullable', 'boolean'],
            'group_post_approval_fee_per_group'     => ['nullable', 'boolean'],
        ]);
        $data['allow_asset_reuse'] = (bool) ($data['allow_asset_reuse'] ?? false);
        $data['payment_holiday_accrue_interest'] = (bool) ($data['payment_holiday_accrue_interest'] ?? false);
        $data['payment_holiday_max_months'] = (int) ($data['payment_holiday_max_months'] ?? 3);
        $data['max_active_applications_per_product'] = (int) ($data['max_active_applications_per_product'] ?? 1);
        $data['max_active_loans'] = (int) ($data['max_active_loans'] ?? 1);
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
        $data['qualification_new_member_floor'] = (int) ($data['qualification_new_member_floor'] ?? 0);

        $groupMin = (int) ($data['group_min_members'] ?? 3);
        $groupMax = (int) ($data['group_max_members'] ?? 10);
        if ($groupMax < $groupMin) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'group_max_members' => 'Maximum group members must be greater than or equal to the minimum.',
            ]);
        }
        $data['group_min_members'] = $groupMin;
        $data['group_max_members'] = $groupMax;
        $data['group_leader_unlock_repayments'] = max(1, (int) ($data['group_leader_unlock_repayments'] ?? 2));
        $data['group_unlock_days'] = max(0, (int) ($data['group_unlock_days'] ?? 0));
        $data['group_payout_order'] = in_array($data['group_payout_order'] ?? '', ['leader_first', 'leader_last', 'manual', 'random', 'rotation', 'committee'], true)
            ? $data['group_payout_order']
            : 'leader_first';
        $data['group_application_fee_per_member'] = (bool) ($data['group_application_fee_per_member'] ?? config('group_lending.application_fee_per_member', false));
        $data['group_post_approval_fee_per_group'] = (bool) ($data['group_post_approval_fee_per_group'] ?? config('group_lending.post_approval_fee_per_group', true));

        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["loan.$k" => $v])->all());
        return back()->with('status', 'Loan rules saved.');
    }

    public function underwriting()
    {
        $values = Setting::group('underwriting');
        $insurance = app(\App\Services\PartnerDefaultsService::class)->defaultsFor('insurance');
        $values['collateral_insurance_rate_percent'] = $insurance['rate_percent']
            ?? ($values['collateral_insurance_rate_percent'] ?? 3.5);
        $values['collateral_insurance_markup_percent'] = $insurance['markup_percent']
            ?? ($values['collateral_insurance_markup_percent'] ?? 0);

        return view('admin.settings.underwriting', [
            'values' => $values,
        ]);
    }

    public function saveUnderwriting(Request $request)
    {
        $data = $request->validate([
            'guarantor_invitation_expiry_days'       => ['required', 'integer', 'min:1', 'max:90'],
            'awaiting_guarantor_deadline_days'       => ['required', 'integer', 'min:1', 'max:90'],
            'document_request_default_due_days'      => ['required', 'integer', 'min:1', 'max:60'],
            'stage_sla_days'                         => ['required', 'integer', 'min:1', 'max:60'],
            'default_rate_tier_count'                => ['required', 'integer', 'min:2', 'max:8'],
            'default_rate_discount_fraction'         => ['required', 'numeric', 'min:0', 'max:0.85'],
            'hold_applications_until_guarantor_approved' => ['nullable', 'boolean'],
            'block_acknowledge_without_guarantor'    => ['nullable', 'boolean'],
            'enable_counter_offers'                  => ['nullable', 'boolean'],
            'enable_asset_backed_alternative'        => ['nullable', 'boolean'],
            'enable_automatic_rejection'             => ['nullable', 'boolean'],
            'collateral_secure_decision_days'        => ['required', 'integer', 'min:1', 'max:30'],
            'insurance_expiry_buffer_months'         => ['required', 'integer', 'min:0', 'max:24'],
            'insurance_renewal_decision_days'        => ['required', 'integer', 'min:1', 'max:30'],
            'collateral_secure_grace_days'           => ['required', 'integer', 'min:0', 'max:14'],
            'collateral_insurance_rate_percent'      => ['required', 'numeric', 'min:0', 'max:100'],
            'collateral_insurance_markup_percent'    => ['required', 'numeric', 'min:0', 'max:100'],
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

        $markup = max(0, (float) $data['collateral_insurance_markup_percent']);
        app(\App\Services\PartnerDefaultsService::class)->saveFromRequest([
            'insurance_rate_percent' => $data['collateral_insurance_rate_percent'],
            'insurance_has_markup' => $markup > 0,
            'insurance_markup_percent' => $markup,
        ], ['insurance']);

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
            'deferred_fee_liability_gl_account_id'    => ['nullable', 'exists:chart_of_accounts,id'],
            'borrower_refunds_payable_gl_account_id'  => ['nullable', 'exists:chart_of_accounts,id'],
            'recovery_revenue_gl_account_id'          => ['nullable', 'exists:chart_of_accounts,id'],
            'recovery_partner_payable_gl_account_id'  => ['nullable', 'exists:chart_of_accounts,id'],
            'supplier_payable_gl_account_id'          => ['nullable', 'exists:chart_of_accounts,id'],
            'asset_lending_principal_clearing_gl_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'valuation_revenue_gl_account_id'         => ['nullable', 'exists:chart_of_accounts,id'],
            'gps_revenue_gl_account_id'               => ['nullable', 'exists:chart_of_accounts,id'],
            'asset_lending_revenue_gl_account_id'     => ['nullable', 'exists:chart_of_accounts,id'],
            'capital_partner_interest_share_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capital_allocation_strategy'             => ['nullable', 'in:proportional,round_robin,priority,manual'],
            'write_off_approval_required'             => ['nullable', 'boolean'],
            'repayment_approval_required'           => ['nullable', 'boolean'],
            'collections_gateway_only'              => ['nullable', 'boolean'],
        ]);
        $data['write_off_approval_required'] = $request->boolean('write_off_approval_required');
        $data['repayment_approval_required'] = $request->boolean('repayment_approval_required');
        $data['collections_gateway_only'] = $request->boolean('collections_gateway_only');
        Setting::setMany(collect($data)->mapWithKeys(fn($v, $k) => ["finance.$k" => $v])->all());
        return back()->with('status', 'Finance defaults saved.');
    }

    public function assetLending()
    {
        return view('admin.settings.asset-lending', [
            'values'     => array_merge(
                Setting::group('asset_lending'),
                Setting::group('partners'),
            ),
            'categories' => config('asset_lending.categories', []),
        ]);
    }

    public function saveAssetLending(Request $request)
    {
        $data = $request->validate([
            'markup_base'                    => ['required', 'in:deposit,asset_price'],
            'default_deposit_markup_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_waiting_period_days'    => ['required', 'integer', 'min:0', 'max:90'],
            'insurance_expiry_warning_days'  => ['required', 'integer', 'min:1', 'max:365'],
            'default_monthly_rate_percent'   => ['required', 'numeric', 'min:0', 'max:100'],
            'max_asset_photos'               => ['required', 'integer', 'min:1', 'max:20'],
            'vehicle_max_age_years'          => ['required', 'integer', 'min:1', 'max:40'],
            'code_prefix'                    => ['required', 'string', 'max:10'],
            'default_country_code'           => ['required', 'string', 'size:2'],
        ]);

        Setting::setMany([
            'asset_lending.markup_base'                    => $data['markup_base'],
            'asset_lending.default_deposit_markup_percent' => $data['default_deposit_markup_percent'],
            'asset_lending.default_waiting_period_days'    => $data['default_waiting_period_days'],
            'asset_lending.insurance_expiry_warning_days'  => $data['insurance_expiry_warning_days'],
            'asset_lending.default_monthly_rate_percent'   => $data['default_monthly_rate_percent'],
            'asset_lending.max_asset_photos'               => $data['max_asset_photos'],
            'asset_lending.vehicle_max_age_years'          => $data['vehicle_max_age_years'],
            'partners.code_prefix'                         => strtoupper($data['code_prefix']),
            'partners.default_country_code'                => strtoupper($data['default_country_code']),
        ]);

        return back()->with('status', 'Asset lending settings saved.');
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
            'registration_fee'  => ['required', 'numeric', 'min:0'],
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
            'referrer_points'        => ['required', 'integer', 'min:0', 'max:100000'],
            'commission_percent'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wallet_max_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'attribution_days'       => ['required', 'integer', 'min:1', 'max:365'],
            'message_share_template' => ['nullable', 'string', 'max:2000'],
            'message_invite_sms'     => ['nullable', 'string', 'max:500'],
            'message_share_en'       => ['nullable', 'string', 'max:2000'],
            'message_share_sw'       => ['nullable', 'string', 'max:2000'],
        ]);

        if (! isset($data['commission_percent'])) {
            $data['commission_percent'] = Setting::get('referrals.commission_percent', config('referrals.commission_percent'));
        }

        Setting::setMany(collect($data)->mapWithKeys(fn ($v, $k) => ["referrals.$k" => $v])->all());

        return back()->with('status', 'Referral settings saved.');
    }

    public function affiliates()
    {
        return view('admin.settings.affiliates', [
            'values' => app(\App\Services\AffiliateSettingsService::class)->forForm(),
        ]);
    }

    public function saveAffiliates(Request $request)
    {
        $data = $request->validate([
            'code_prefix'                         => ['required', 'string', 'max:10'],
            'default_registration_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_application_discount_percent'  => ['required', 'numeric', 'min:0', 'max:100'],
            'default_commission_percent'          => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_mode'                     => ['required', 'in:percentage,fixed,tiered,hybrid'],
            'hybrid_fixed_amount'                 => ['nullable', 'numeric', 'min:0'],
            'hybrid_percent'                      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_commission_default'            => ['nullable', 'numeric', 'min:0'],
            'fixed_commission_registration_fee'   => ['nullable', 'numeric', 'min:0'],
            'fixed_commission_application_fee'    => ['nullable', 'numeric', 'min:0'],
            'fixed_commission_post_approval_fee'  => ['nullable', 'numeric', 'min:0'],
            'commission_tiers'                    => ['nullable', 'array'],
            'commission_tiers.*.min_count'        => ['nullable', 'integer', 'min:0'],
            'commission_tiers.*.max_count'        => ['nullable', 'integer', 'min:0'],
            'commission_tiers.*.type'             => ['nullable', 'in:fixed,percentage'],
            'commission_tiers.*.amount'           => ['nullable', 'numeric', 'min:0'],
            'commission_calculation_base'         => ['required', 'in:original_amount,discounted_amount'],
            'applies_to'                          => ['nullable', 'array'],
            'applies_to.*'                        => ['nullable', 'boolean'],
            'message_share_template'              => ['nullable', 'string', 'max:500'],
            'message_referral_sms'                => ['nullable', 'string', 'max:500'],
            'message_verification_notice'         => ['nullable', 'string', 'max:500'],
            'message_welcome_partner'             => ['nullable', 'string', 'max:500'],
            'message_share_template_sw'           => ['nullable', 'string', 'max:500'],
            'message_referral_sms_sw'             => ['nullable', 'string', 'max:500'],
            'message_verification_notice_sw'      => ['nullable', 'string', 'max:500'],
            'message_welcome_partner_sw'          => ['nullable', 'string', 'max:500'],
            'require_kyc_for_verification'        => ['nullable', 'boolean'],
            'membership_enabled'                  => ['nullable', 'boolean'],
            'membership_fee_amount'               => ['nullable', 'numeric', 'min:0'],
            'membership_duration_days'            => ['nullable', 'integer', 'min:1', 'max:1095'],
            'membership_grace_period_hours'       => ['nullable', 'integer', 'min:1', 'max:720'],
            'membership_required_before_sharing'  => ['nullable', 'boolean'],
            'eval_auto_apply_actions'             => ['nullable', 'boolean'],
            'eval_period_days'                    => ['nullable', 'integer', 'min:1', 'max:365'],
            'eval_min_events_for_scoring'         => ['nullable', 'integer', 'min:1', 'max:1000'],
            'eval_watchlist_risk_score'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'eval_watchlist_fraud_score'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'eval_suspend_risk_score'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'eval_suspend_fraud_score'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'eval_duplicate_ip_threshold'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'eval_low_conversion_threshold'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'eval_high_click_threshold'           => ['nullable', 'integer', 'min:1', 'max:100000'],
            'fraud_medium_score'                  => ['nullable', 'integer', 'min:0', 'max:100'],
            'fraud_high_score'                    => ['nullable', 'integer', 'min:0', 'max:100'],
            'fraud_blocked_score'                 => ['nullable', 'integer', 'min:0', 'max:100'],
            'fraud_shared_phone_threshold'        => ['nullable', 'integer', 'min:1', 'max:100'],
            'fraud_shared_device_threshold'       => ['nullable', 'integer', 'min:1', 'max:100'],
            'fraud_multi_account_threshold'       => ['nullable', 'integer', 'min:1', 'max:100'],
            'minimum_payout_amount'               => ['required', 'numeric', 'min:0'],
        ]);

        $feeTypes = ['registration_fee', 'application_fee', 'post_approval_fee', 'interest', 'repayments'];
        $appliesTo = collect($feeTypes)
            ->mapWithKeys(fn (string $type) => [$type => $request->boolean("applies_to.$type")])
            ->all();

        $tiers = collect($data['commission_tiers'] ?? [])
            ->map(function (array $tier): array {
                $max = $tier['max_count'] ?? null;

                return [
                    'min_count' => (int) ($tier['min_count'] ?? 1),
                    'max_count' => filled($max) ? (int) $max : null,
                    'type'      => (string) ($tier['type'] ?? 'fixed'),
                    'amount'    => (float) ($tier['amount'] ?? 0),
                ];
            })
            ->filter(fn (array $tier) => $tier['amount'] > 0)
            ->values()
            ->all();

        Setting::setMany([
            'affiliates.code_prefix'                         => $data['code_prefix'],
            'affiliates.default_registration_discount_percent' => $data['default_registration_discount_percent'],
            'affiliates.default_application_discount_percent'  => $data['default_application_discount_percent'],
            'affiliates.default_commission_percent'          => $data['default_commission_percent'],
            'affiliates.commission_mode'                     => $data['commission_mode'],
            'affiliates.hybrid_fixed_amount'                 => (float) ($data['hybrid_fixed_amount'] ?? 0),
            'affiliates.hybrid_percent'                      => (float) ($data['hybrid_percent'] ?? 0),
            'affiliates.fixed_commission_amounts'            => [
                'default'           => (float) ($data['fixed_commission_default'] ?? 0),
                'registration_fee'  => (float) ($data['fixed_commission_registration_fee'] ?? 0),
                'application_fee'   => (float) ($data['fixed_commission_application_fee'] ?? 0),
                'post_approval_fee' => (float) ($data['fixed_commission_post_approval_fee'] ?? 0),
            ],
            'affiliates.commission_tiers'                    => $tiers,
            'affiliates.evaluation'                          => [
                'auto_apply_actions'                  => $request->boolean('eval_auto_apply_actions'),
                'period_days'                         => (int) ($data['eval_period_days'] ?? 30),
                'min_events_for_scoring'              => (int) ($data['eval_min_events_for_scoring'] ?? 3),
                'watchlist_risk_score'                => (float) ($data['eval_watchlist_risk_score'] ?? 60),
                'watchlist_fraud_score'               => (float) ($data['eval_watchlist_fraud_score'] ?? 50),
                'suspend_risk_score'                  => (float) ($data['eval_suspend_risk_score'] ?? 80),
                'suspend_fraud_score'                 => (float) ($data['eval_suspend_fraud_score'] ?? 75),
                'duplicate_ip_registration_threshold' => (int) ($data['eval_duplicate_ip_threshold'] ?? 3),
                'low_conversion_threshold'            => (float) ($data['eval_low_conversion_threshold'] ?? 5),
                'high_click_threshold'                => (int) ($data['eval_high_click_threshold'] ?? 50),
            ],
            'affiliates.fraud'                               => [
                'medium_score'                         => (int) ($data['fraud_medium_score'] ?? 20),
                'high_score'                           => (int) ($data['fraud_high_score'] ?? 50),
                'blocked_score'                        => (int) ($data['fraud_blocked_score'] ?? 80),
                'shared_phone_customer_threshold'      => (int) ($data['fraud_shared_phone_threshold'] ?? 2),
                'shared_device_registration_threshold' => (int) ($data['fraud_shared_device_threshold'] ?? 2),
                'multi_account_device_threshold'       => (int) ($data['fraud_multi_account_threshold'] ?? 2),
            ],
            'affiliates.commission_calculation_base'         => $data['commission_calculation_base'],
            'affiliates.applies_to'                          => $appliesTo,
            'affiliates.messages'                            => [
                'share_template'      => $data['message_share_template'] ?? '',
                'referral_sms'        => $data['message_referral_sms'] ?? '',
                'verification_notice' => $data['message_verification_notice'] ?? '',
                'welcome_partner'     => $data['message_welcome_partner'] ?? '',
            ],
            'affiliates.messages_sw'                         => [
                'share_template'      => $data['message_share_template_sw'] ?? '',
                'referral_sms'        => $data['message_referral_sms_sw'] ?? '',
                'verification_notice' => $data['message_verification_notice_sw'] ?? '',
                'welcome_partner'     => $data['message_welcome_partner_sw'] ?? '',
            ],
            'affiliates.membership'                          => [
                'enabled'                 => $request->boolean('membership_enabled'),
                'fee_amount'              => (float) ($data['membership_fee_amount'] ?? 50000),
                'duration_days'           => (int) ($data['membership_duration_days'] ?? 365),
                'grace_period_hours'      => (int) ($data['membership_grace_period_hours'] ?? 48),
                'required_before_sharing' => $request->boolean('membership_required_before_sharing'),
            ],
            'affiliates.require_kyc_for_verification'        => $request->boolean('require_kyc_for_verification'),
            'affiliates.minimum_payout_amount'               => (float) ($data['minimum_payout_amount'] ?? config('affiliates.minimum_payout_amount', 50000)),
        ]);

        return back()->with('status', 'Affiliate settings saved.');
    }

    public function partners()
    {
        $cfg = \App\Services\PartnerMembershipService::config();
        $roles = app(\App\Services\PartnerService::class)->roleOptions();
        unset($roles['affiliate'], $roles['capital']);

        return view('admin.settings.partners', [
            'values' => $cfg,
            'roles'  => $roles,
        ]);
    }

    public function savePartners(Request $request)
    {
        $roles = array_keys(app(\App\Services\PartnerService::class)->roleOptions());
        $roles = array_values(array_diff($roles, ['affiliate', 'capital']));

        $data = $request->validate([
            'membership_enabled'            => ['nullable', 'boolean'],
            'default_fee_amount'            => ['nullable', 'numeric', 'min:0'],
            'default_duration_days'         => ['nullable', 'integer', 'min:1', 'max:1095'],
            'grace_period_days'             => ['nullable', 'integer', 'min:0', 'max:90'],
            'notify_days_before_expiry'     => ['nullable', 'integer', 'min:1', 'max:90'],
            'categories_requiring_payment'  => ['nullable', 'array'],
            'category_fees'                 => ['nullable', 'array'],
            'category_fees.*'               => ['nullable', 'numeric', 'min:0'],
        ]);

        $requirePay = [];
        $fees = [];
        foreach ($roles as $role) {
            $requirePay[$role] = $request->boolean("categories_requiring_payment.$role");
            $fees[$role] = (float) ($data['category_fees'][$role] ?? $data['default_fee_amount'] ?? 0);
        }

        Setting::setMany([
            'partners.membership' => [
                'enabled' => $request->boolean('membership_enabled'),
                'default_fee_amount' => (float) ($data['default_fee_amount'] ?? 0),
                'default_duration_days' => (int) ($data['default_duration_days'] ?? 365),
                'grace_period_days' => (int) ($data['grace_period_days'] ?? 14),
                'notify_days_before_expiry' => (int) ($data['notify_days_before_expiry'] ?? 30),
                'categories_requiring_payment' => $requirePay,
                'category_fees' => $fees,
            ],
        ]);

        return back()->with('status', 'Partner membership settings saved.');
    }

    public function countries(Request $request)
    {
        $service = app(\App\Services\CountrySettingsService::class);
        $code = strtoupper((string) $request->query('country', $service->defaultCountryCode()));

        if (! in_array($code, $service->codes(), true)) {
            $code = $service->defaultCountryCode();
        }

        $countries = collect($service->codes())
            ->map(fn (string $c) => $service->forCode($c))
            ->all();

        return view('admin.settings.countries', [
            'countries' => $countries,
            'selected'  => $service->forCode($code),
        ]);
    }

    public function saveCountry(Request $request, string $country)
    {
        $code = strtoupper($country);
        abort_unless(in_array($code, app(\App\Services\CountrySettingsService::class)->codes(), true), 404);

        $data = $request->validate([
            'active'              => ['nullable', 'boolean'],
            'language'            => ['required', 'in:en,sw'],
            'currency'            => ['required', 'string', 'size:3'],
            'timezone'            => ['required', 'string', 'max:50'],
            'phone_prefix'        => ['required', 'string', 'max:6'],
            'national_id_label'   => ['required', 'string', 'max:50'],
            'national_id_format'  => ['required', 'in:nida_20,digits_8,digits_16,alphanumeric'],
            'grace_period_days'   => ['required', 'integer', 'min:0', 'max:60'],
            'repayment_ratio_pct' => ['required', 'numeric', 'min:1', 'max:100'],
            'crb_freshness_days'  => ['required', 'integer', 'min:30', 'max:365'],
            'kyc_freshness_days'  => ['required', 'integer', 'min:30', 'max:365'],
            'guarantor_required'  => ['nullable', 'boolean'],
            'contract_locale'     => ['required', 'in:en,sw'],
            'contract_template'   => ['nullable', 'string', 'max:200'],
            'loan_policy_notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['guarantor_required'] = $request->boolean('guarantor_required');

        app(\App\Services\CountrySettingsService::class)->save($code, $data);

        return redirect()
            ->route('admin.settings.countries', ['country' => $code])
            ->with('status', $code.' country settings saved.');
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

    public function recovery()
    {
        $policy = app(\App\Services\RecoveryPolicyService::class);
        $raw = Setting::group('recovery');
        $types = $policy->partnerTypes();

        $values = [
            'grace_period_days'       => $raw['grace_period_days'] ?? 2,
            'auction_hold_days'       => $raw['auction_hold_days'] ?? config('recovery.default_auction_hold_days', 4),
            'gps_map_enabled'         => filter_var(Setting::get('gps.map_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'fee_base'                => $raw['fee_base'] ?? 'principal',
            'auto_escalate'           => (bool) ($raw['auto_escalate'] ?? true),
            'auto_assign_call_center' => (bool) ($raw['auto_assign_call_center'] ?? true),
            'call_center_lead_days'   => $raw['call_center_lead_days'] ?? 0,
            'sla_days'                => [],
            'commission_percent'=> [],
            'markup_percent'    => [],
            'fee_type'          => [],
            'fixed_amount'      => [],
            'priority'          => [],
            'loan_types'        => [],
            'collateral_scope'  => [],
            'auto_escalate_type'=> [],
            'has_markup'        => [],
            'repossession_charges' => Setting::get('repossession.charges') ?? [],
            'partner_defaults' => app(\App\Services\PartnerDefaultsService::class)->allDefaults(),
        ];

        foreach ($types as $type => $meta) {
            $values['sla_days'][$type] = $raw["sla_days.{$type}"] ?? $meta['default_sla_days'];
            $values['commission_percent'][$type] = $raw["commission_percent.{$type}"] ?? $meta['default_commission_percent'];
            $values['markup_percent'][$type] = $policy->storedMarkupPercent($type);
            $values['has_markup'][$type] = $policy->hasMarkupForType($type);
            $values['fee_type'][$type] = $raw["fee_type.{$type}"] ?? ($meta['default_fee_type'] ?? 'percentage');
            $values['fixed_amount'][$type] = $raw["fixed_amount.{$type}"] ?? $meta['default_fixed_amount'];
            $values['priority'][$type] = $raw["priority.{$type}"] ?? ($meta['default_priority'] ?? 99);
            $values['loan_types'][$type] = $raw["loan_types.{$type}"] ?? ($meta['default_loan_types'] ?? 'all');
            $values['collateral_scope'][$type] = $raw["collateral_scope.{$type}"] ?? ($meta['default_collateral_scope'] ?? 'all');
            $values['auto_escalate_type'][$type] = (bool) ($raw["auto_escalate_type.{$type}"] ?? ($meta['default_auto_escalate'] ?? true));
        }

        $types = collect($types)
            ->sortBy(fn ($meta, $type) => (int) ($values['priority'][$type] ?? 99))
            ->all();

        $partnerDefaults = $values['partner_defaults'];

        return view('admin.settings.recovery', compact('values', 'types', 'partnerDefaults'));
    }

    public function saveRecovery(Request $request)
    {
        $types = array_keys(config('recovery.partner_types', []));
        $serviceCategories = array_keys(config('partner_defaults.categories', []));

        $rules = [
            'grace_period_days'       => ['required', 'integer', 'min:1', 'max:60'],
            'auction_hold_days'       => ['required', 'integer', 'min:1', 'max:30'],
            'gps_map_enabled'         => ['nullable', 'boolean'],
            'fee_base'                => ['required', 'in:principal,outstanding'],
            'auto_escalate'           => ['nullable', 'boolean'],
            'auto_assign_call_center' => ['nullable', 'boolean'],
            'call_center_lead_days'   => ['required', 'integer', 'min:0', 'max:30'],
        ];

        foreach ($types as $type) {
            $rules["sla_days_{$type}"] = ['required', 'integer', 'min:1', 'max:90'];
            $rules["commission_percent_{$type}"] = ['required', 'numeric', 'min:0', 'max:100'];
            $rules["markup_percent_{$type}"] = ['required', 'numeric', 'min:0', 'max:100'];
            $rules["has_markup_{$type}"] = ['nullable', 'boolean'];
            $rules["fee_type_{$type}"] = ['required', 'in:percentage,fixed'];
            $rules["fixed_amount_{$type}"] = ['nullable', 'numeric', 'min:0'];
            $rules["priority_{$type}"] = ['required', 'integer', 'min:1', 'max:99'];
            $rules["loan_types_{$type}"] = ['nullable', 'string', 'max:120'];
            $rules["collateral_scope_{$type}"] = ['required', 'in:all,secured,unsecured'];
            $rules["auto_escalate_type_{$type}"] = ['nullable', 'boolean'];
        }

        foreach (array_keys(config('repossession_charges.asset_types', [])) as $assetType) {
            $rules["repossession_partner_cost_{$assetType}"] = ['nullable', 'numeric', 'min:0'];
            $rules["repossession_markup_{$assetType}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $rules["repossession_manual_{$assetType}"] = ['nullable', 'boolean'];
        }

        foreach ($serviceCategories as $category) {
            $mode = config("partner_defaults.categories.{$category}.pricing_mode");
            $rules["{$category}_has_markup"] = ['nullable', 'boolean'];
            $rules["{$category}_markup_percent"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            if ($mode === 'percent_of_value') {
                $rules["{$category}_rate_percent"] = ['required', 'numeric', 'min:0', 'max:100'];
            } else {
                $rules["{$category}_base_cost"] = ['required', 'numeric', 'min:0'];
            }
            if ($mode === 'fixed_plus_recurring') {
                $rules["{$category}_monitoring_monthly"] = ['required', 'numeric', 'min:0'];
            }
        }

        $data = $request->validate($rules);

        $settings = [
            'recovery.grace_period_days'       => $data['grace_period_days'],
            'recovery.auction_hold_days'       => $data['auction_hold_days'],
            'gps.map_enabled'                  => $request->boolean('gps_map_enabled'),
            'recovery.fee_base'                => $data['fee_base'],
            'recovery.auto_escalate'           => $request->boolean('auto_escalate'),
            'recovery.auto_assign_call_center' => $request->boolean('auto_assign_call_center'),
            'recovery.call_center_lead_days'   => $data['call_center_lead_days'],
        ];

        foreach ($types as $type) {
            $settings["recovery.sla_days.{$type}"] = $data["sla_days_{$type}"];
            $settings["recovery.commission_percent.{$type}"] = $data["commission_percent_{$type}"];
            $settings["recovery.markup_percent.{$type}"] = $data["markup_percent_{$type}"];
            $settings["recovery.has_markup.{$type}"] = $request->boolean("has_markup_{$type}");
            $settings["recovery.fee_type.{$type}"] = $data["fee_type_{$type}"];
            $settings["recovery.fixed_amount.{$type}"] = $data["fixed_amount_{$type}"] ?? null;
            $settings["recovery.priority.{$type}"] = $data["priority_{$type}"];
            $settings["recovery.loan_types.{$type}"] = filled($data["loan_types_{$type}"] ?? null)
                ? strtoupper(trim((string) $data["loan_types_{$type}"]))
                : 'all';
            $settings["recovery.collateral_scope.{$type}"] = $data["collateral_scope_{$type}"];
            $settings["recovery.auto_escalate_type.{$type}"] = $request->boolean("auto_escalate_type_{$type}");
        }

        $repossession = [];
        foreach (array_keys(config('repossession_charges.asset_types', [])) as $assetType) {
            $repossession[$assetType] = [
                'partner_cost'   => $data["repossession_partner_cost_{$assetType}"] ?? null,
                'markup_percent' => $data["repossession_markup_{$assetType}"] ?? 10,
                'manual_quote'   => $request->boolean("repossession_manual_{$assetType}"),
            ];
        }
        $settings['repossession.charges'] = $repossession;

        Setting::setMany($settings);

        $partnerInput = [];
        foreach ($serviceCategories as $category) {
            $partnerInput["{$category}_has_markup"] = $request->boolean("{$category}_has_markup");
            $partnerInput["{$category}_markup_percent"] = $data["{$category}_markup_percent"] ?? 0;
            if (array_key_exists("{$category}_rate_percent", $data)) {
                $partnerInput["{$category}_rate_percent"] = $data["{$category}_rate_percent"];
            }
            if (array_key_exists("{$category}_base_cost", $data)) {
                $partnerInput["{$category}_base_cost"] = $data["{$category}_base_cost"];
            }
            if (array_key_exists("{$category}_monitoring_monthly", $data)) {
                $partnerInput["{$category}_monitoring_monthly"] = $data["{$category}_monitoring_monthly"];
            }
        }
        app(\App\Services\PartnerDefaultsService::class)->saveFromRequest($partnerInput);

        $tab = (string) $request->input('_tab', 'timeline');
        if (! in_array($tab, ['timeline', 'recovery', 'repossession', 'service'], true)) {
            $tab = 'timeline';
        }

        return redirect()
            ->route('admin.settings.recovery', ['tab' => $tab])
            ->with('status', 'Recovery policy saved.');
    }

    public function chatbot()
    {
        $entries = collect(app(\App\Services\ChatbotContentService::class)->entries())
            ->map(function (array $entry) {
                $keywords = $entry['keywords'] ?? [];
                $entry['keywords'] = is_array($keywords) ? implode(', ', $keywords) : (string) $keywords;

                return $entry;
            })
            ->values()
            ->all();

        return view('admin.settings.chatbot', [
            'entries' => $entries,
        ]);
    }

    public function saveChatbot(Request $request)
    {
        $data = $request->validate([
            'entries'               => ['required', 'array'],
            'entries.*.key'         => ['nullable', 'string', 'max:40'],
            'entries.*.sort'        => ['nullable', 'integer', 'min:0', 'max:999'],
            'entries.*.keywords'    => ['nullable', 'string', 'max:500'],
            'entries.*.question_en' => ['nullable', 'string', 'max:500'],
            'entries.*.question_sw' => ['nullable', 'string', 'max:500'],
            'entries.*.answer_en'   => ['nullable', 'string', 'max:2000'],
            'entries.*.answer_sw'   => ['nullable', 'string', 'max:2000'],
        ]);

        $entries = collect($data['entries'])->values()->map(function (array $row, int $index) use ($request) {
            $keywords = array_values(array_filter(array_map(
                'trim',
                preg_split('/[,;|]+/', (string) ($row['keywords'] ?? '')) ?: []
            )));

            return [
                'key'         => filled($row['key'] ?? null) ? (string) $row['key'] : 'entry_'.($index + 1),
                'sort'        => (int) ($row['sort'] ?? ($index + 1)),
                'active'      => $request->boolean("entries.{$index}.active"),
                'keywords'    => $keywords,
                'question_en' => (string) ($row['question_en'] ?? ''),
                'question_sw' => (string) ($row['question_sw'] ?? ''),
                'answer_en'   => (string) ($row['answer_en'] ?? ''),
                'answer_sw'   => (string) ($row['answer_sw'] ?? ''),
            ];
        })->all();

        app(\App\Services\ChatbotContentService::class)->saveEntries($entries);

        return back()->with('status', 'Chatbot content saved.');
    }
}
