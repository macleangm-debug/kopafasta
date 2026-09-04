<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargesFee;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\MembershipService;
use App\Services\PartnerMembershipService;
use App\Services\Plus\PlusService;
use App\Services\ReleaseInfoService;
use App\Services\Staging\StagingPaymentsService;
use App\Services\ValuationPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function show(ReleaseInfoService $release, StagingPaymentsService $staging): View
    {
        return view('admin.settings.system', [
            'release' => $release->snapshot(),
            'stagingEnabled' => $staging->isEnabled(),
            'stagingPayments' => $staging->isEnabled() ? $this->stagingForm($staging) : null,
        ]);
    }

    public function saveStagingPayments(Request $request, StagingPaymentsService $staging): RedirectResponse
    {
        $staging->assertStaging();

        $data = $request->validate([
            'mode' => ['required', 'in:simulator,psp_sandbox'],
            'default_test_fee' => ['required', 'integer', 'min:0', 'max:10000000'],
            'allow_success' => ['nullable', 'boolean'],
            'allow_pending' => ['nullable', 'boolean'],
            'allow_failure' => ['nullable', 'boolean'],
            'allow_reversal' => ['nullable', 'boolean'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        Setting::setMany([
            'staging_payments.mode' => $data['mode'],
            'staging_payments.default_test_fee' => (int) $data['default_test_fee'],
            'staging_payments.allow_success' => (bool) ($data['allow_success'] ?? false),
            'staging_payments.allow_pending' => (bool) ($data['allow_pending'] ?? false),
            'staging_payments.allow_failure' => (bool) ($data['allow_failure'] ?? false),
            'staging_payments.allow_reversal' => (bool) ($data['allow_reversal'] ?? false),
            'staging_payments.overrides' => $data['overrides'] ?? config('staging_payments.overrides'),
        ]);

        return back()->with('status', 'Staging payment settings saved. Commercial Settings Hub prices were not changed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function stagingForm(StagingPaymentsService $staging): array
    {
        $settings = $staging->settings();
        $overrides = is_array($settings['overrides'] ?? null)
            ? $settings['overrides']
            : config('staging_payments.overrides', []);
        $plusCanonical = (float) (config('kopafasta_plus.plans.monthly.prices.TZ.amount') ?? 35000);
        $membership = MembershipService::config();
        $partner = PartnerMembershipService::config();
        $valuation = app(ValuationPricingService::class)->quote();
        $appFeeCatalog = (float) (ChargesFee::query()->where('code', 'APP_FEE')->value('amount') ?? 0);

        $canonicals = [
            [
                'key' => 'application_fee',
                'label' => 'Individual application fee (catalog APP_FEE / product)',
                'canonical' => $appFeeCatalog,
                'source' => 'charges_fees.APP_FEE + loan_products.application_fee_amount',
            ],
            [
                'key' => 'valuation_fee',
                'label' => 'Valuation fee (borrower)',
                'canonical' => (float) ($valuation['borrower_amount'] ?? 0),
                'source' => 'ValuationPricingService',
            ],
            [
                'key' => 'plus',
                'label' => 'Kopafasta Plus',
                'canonical' => $plusCanonical,
                'source' => 'kopafasta_plus.config / config',
            ],
            [
                'key' => 'membership',
                'label' => 'Borrower membership / registration',
                'canonical' => (float) ($membership['registration_fee'] ?? 0),
                'source' => 'settings membership.registration_fee',
            ],
            [
                'key' => 'partner_membership',
                'label' => 'Partner/affiliate membership (default)',
                'canonical' => (float) ($partner['default_fee_amount'] ?? 0),
                'source' => 'partners.membership',
            ],
        ];

        foreach (LoanProduct::query()->where('is_active', true)->orderBy('code')->get() as $product) {
            $canonicals[] = [
                'key' => $staging->overrideKey('application_fee', $product),
                'label' => 'Application fee · '.$product->code.' '.$product->name,
                'canonical' => (float) ($product->application_fee_amount ?: $appFeeCatalog),
                'source' => 'loan_products.'.$product->code,
                'product' => $product,
            ];
        }

        return [
            'mode' => $staging->mode(),
            'default_test_fee' => $staging->defaultTestFee(),
            'allow_success' => $staging->allows('success'),
            'allow_pending' => $staging->allows('pending'),
            'allow_failure' => $staging->allows('failed'),
            'allow_reversal' => $staging->allows('reversed'),
            'overrides' => $overrides,
            'audit' => $staging->auditRows($canonicals),
        ];
    }
}
