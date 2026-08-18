<?php

namespace Tests\Feature;

use App\Models\CompanySignatory;
use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\LegalSettingsService;
use App\Services\LoanAgreementDisclosureService;
use App\Services\LoanAgreementProductProfile;
use App\Services\LoanAgreementService;
use App\Services\LoanRejectionReasonService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanAgreementMasterTemplateFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $existing = Customer::query()->where('customer_number', 'CU-AGR-01')->first();
        if ($existing) {
            return $existing;
        }

        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-AGR-01',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mushi',
            'national_id' => '19900101123456789012',
            'phone' => '255712000111',
            'street' => '12 Uhuru Street',
            'ward' => 'Kisutu',
            'district' => 'Ilala',
            'region' => 'Dar es Salaam',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'code' => 'IL-AGR',
            'name' => 'Agreement Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
    }

    public function test_contract_pdf_includes_nida_address_penalty_and_recovery_not_flat_late_fee(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-1',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-AGR-1',
            'status' => 'sent',
        ]);
        $application->load('product', 'customer');

        $html = view('pdf.loan-contract', [
            'application' => $application,
            'agreement' => $agreement,
            'snapshot' => array_merge(
                app(LoanAgreementDisclosureService::class)->companyIdentity(),
                app(LoanAgreementDisclosureService::class)->penaltyDisclosure($application),
                [
                    'locale' => 'en',
                    'customer_name' => 'Asha Mushi',
                    'customer_id' => '19900101123456789012',
                    'customer_address' => '12 Uhuru Street, Kisutu, Ilala, Dar es Salaam',
                    'customer_phone' => '255712000111',
                    'principal' => 500_000,
                    'displayed_monthly_rate' => 0.15,
                    'tenure_months' => 12,
                    'installment_count' => 12,
                    'estimated_emi' => 45_000,
                    'total_repayable' => 540_000,
                    'application_number' => 'APP-AGR-1',
                    'recovery_schedule' => app(LoanAgreementDisclosureService::class)->recoverySchedule($application),
                    'repayment_schedule' => [],
                ]
            ),
        ])->render();

        $this->assertStringContainsString('19900101123456789012', $html);
        $this->assertStringContainsString('12 Uhuru Street, Kisutu, Ilala, Dar es Salaam', $html);
        $this->assertStringContainsString('unpaid remainder of the first overdue instalment', $html);
        $this->assertStringContainsString('Call centre', $html);
        $this->assertStringContainsString('Finance manager', $html);
        $this->assertStringContainsString('English version prevails', $html);
        $this->assertStringContainsString('kopafasta', $html);
        $this->assertStringContainsString('Asha Mushi', $html);
        $this->assertStringNotContainsString('letter-spacing', $html);
        $this->assertStringNotContainsString(format_money(2000), $html);
        $this->assertStringNotContainsString('TAFADHALI SOMA MKATABA HUU KWA MAKINI KABLA YA KUSAINI.', $html);
    }

    public function test_contract_pdf_uses_borrower_selected_language(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-SW',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-AGR-SW',
            'status' => 'sent',
        ]);
        $application->load('product', 'customer');

        $html = view('pdf.loan-contract', [
            'application' => $application,
            'agreement' => $agreement,
            'snapshot' => array_merge(
                app(LoanAgreementDisclosureService::class)->companyIdentity(),
                app(LoanAgreementDisclosureService::class)->penaltyDisclosure($application),
                [
                    'locale' => 'sw',
                    'customer_name' => 'Asha Mushi',
                    'customer_id' => '19900101123456789012',
                    'customer_address' => '12 Uhuru Street, Kisutu, Ilala, Dar es Salaam',
                    'customer_phone' => '255712000111',
                    'principal' => 500_000,
                    'displayed_monthly_rate' => 0.15,
                    'tenure_months' => 12,
                    'installment_count' => 12,
                    'estimated_emi' => 45_000,
                    'total_repayable' => 540_000,
                    'application_number' => 'APP-AGR-SW',
                    'customer_activity' => 'trader',
                    'purpose' => 'school_fees',
                    'recovery_schedule' => app(LoanAgreementDisclosureService::class)->recoverySchedule($application),
                    'repayment_schedule' => [],
                ]
            ),
        ])->render();

        $this->assertStringContainsString('Ada ya shule', $html);
        $this->assertStringContainsString('Mfanyabiashara', $html);
        $this->assertStringNotContainsString('school fees', $html);
        $this->assertStringNotContainsString('School Fees', $html);
        $this->assertStringNotContainsString('Trader', $html);
        $this->assertStringContainsString('toleo la Kiingereza ndilo litakalotawala', $html);
        $this->assertStringContainsString('Jamhuri ya Muungano wa Tanzania', $html);
        $this->assertStringContainsString('Kituo cha simu', $html);
        $this->assertStringContainsString('Mshirika wa kisheria', $html);
        $this->assertStringContainsString('Ada ya TZS 100,000', $html);
        $this->assertStringContainsString('29. Sheria inayotumika', $html);
        $this->assertStringNotContainsString('PLEASE READ THIS AGREEMENT CAREFULLY BEFORE SIGNING.', $html);
        $this->assertStringNotContainsString('Loan facility', $html);
        $this->assertStringNotContainsString('United Republic of Tanzania', $html);
        $this->assertStringNotContainsString('Call Center Partner', $html);
        $this->assertStringNotContainsString('16. Mikopo ya kikundi', $html);
        $this->assertStringNotContainsString('GPS Partner', $html);
        $this->assertStringNotContainsString('Mshirika wa GPS', $html);
        $this->assertStringNotContainsString('26–30', $html);
    }

    public function test_download_rewrites_stored_pdf_with_branded_template(): void
    {
        Storage::fake('public');

        $customer = $this->borrower();
        $customer->user->update(['preferences' => ['preferred_locale' => 'en']]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-BRAND',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);
        Storage::disk('public')->put('agreements/LC-OLD.pdf', '%PDF-1.4 old unbranded');
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-OLD',
            'status' => 'signed',
            'signed_at' => now(),
            'file_path' => 'agreements/LC-OLD.pdf',
            'snapshot' => [
                'customer_name' => 'Asha Mushi',
                'principal' => 500_000,
            ],
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-agreements.download', $agreement))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertGreaterThan(2000, strlen((string) $response->getContent()));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertNotSame(
            '%PDF-1.4 old unbranded',
            Storage::disk('public')->get('agreements/LC-OLD.pdf'),
        );
    }

    public function test_credit_file_letters_refresh_stale_contract_onto_branded_template(): void
    {
        Storage::fake('public');

        $customer = $this->borrower();
        $customer->user->update(['preferences' => ['preferred_locale' => 'en']]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-STALE',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'disbursed',
        ]);
        Storage::disk('public')->put('agreements/FLC-STALE.pdf', '%PDF-1.4 Final Loan Contract');
        LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'final_loan_contract',
            'reference' => 'FLC-STALE',
            'status' => 'signed',
            'signed_at' => now(),
            'file_path' => 'agreements/FLC-STALE.pdf',
            'snapshot' => ['customer_name' => 'Asha Mushi', 'principal' => 500_000],
        ]);

        $letters = app(LoanAgreementService::class)->creditFileLetters($application);
        $this->assertNotSame(
            LoanAgreementDisclosureService::DOCUMENT_VERSION,
            data_get($letters['final']?->snapshot, 'document_version'),
        );

        $this->actingAs(User::factory()->create(['role' => 'admin']), 'admin')
            ->get(route('admin.loan-agreements.download', $letters['final']))
            ->assertOk();

        $this->assertSame(
            LoanAgreementDisclosureService::DOCUMENT_VERSION,
            data_get($letters['final']->fresh()->snapshot, 'document_version'),
        );
        $this->assertNotSame(
            '%PDF-1.4 Final Loan Contract',
            Storage::disk('public')->get('agreements/FLC-STALE.pdf'),
        );
    }

    public function test_recovery_disclosure_states_percentage_of_shared_base(): void
    {
        Setting::set('recovery.fee_base', 'principal');
        Setting::set('recovery.commission_percent.call_center', 10);
        Setting::set('recovery.markup_percent.call_center', 3);

        $application = LoanApplication::create([
            'customer_id' => $this->borrower()->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-2',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);

        $schedule = app(LoanAgreementDisclosureService::class)->recoverySchedule($application);
        $callCentre = collect($schedule['stages'])->firstWhere('type', 'call_center');

        $this->assertSame('principal', $schedule['fee_base']);
        $this->assertStringContainsString('10%', $callCentre['display_en']);
        $this->assertStringContainsString('3%', $callCentre['display_en']);
        $this->assertStringContainsString('principal amount', $callCentre['display_en']);
        $this->assertStringContainsString('Posted only when this stage is actually assigned', $callCentre['display_en']);
        $this->assertNull(collect($schedule['stages'])->firstWhere('type', 'gps_partner'));
    }

    public function test_new_contract_reads_legal_and_gps_from_live_settings(): void
    {
        Setting::set('recovery.fee_type.legal_partner', 'fixed');
        Setting::set('recovery.fixed_amount.legal_partner', 100_000);
        Setting::set('recovery.charges_borrower.gps_partner', false);
        Setting::set('partner_defaults.gps_installer.base_cost', 50_000);
        Setting::set('partner_defaults.gps_installer.monitoring_monthly', 20_000);
        Setting::set('partner_defaults.gps_installer.has_markup', false);
        Setting::set('partner_defaults.gps_installer.markup_percent', 0);

        $il = $this->applicationWithProduct($this->product(), 'APP-SET-IL');
        $disclosure = app(LoanAgreementDisclosureService::class);
        $legal = collect($disclosure->recoverySchedule($il)['stages'])->firstWhere('type', 'legal_partner');
        $this->assertNotNull($legal);
        $this->assertSame('fixed', $legal['fee_type']);
        $this->assertStringContainsString('100,000', $legal['display_en']);
        $this->assertNull($disclosure->gpsPostApprovalFee($il));

        Setting::set('recovery.fixed_amount.legal_partner', 250_000);
        $legalAfter = collect($disclosure->recoverySchedule($il->fresh())['stages'])->firstWhere('type', 'legal_partner');
        $this->assertStringContainsString('250,000', $legalAfter['display_en']);
        $this->assertStringNotContainsString('100,000', $legalAfter['display_en']);

        $ab = LoanProduct::create([
            'code' => 'AB',
            'name' => 'Asset-Backed',
            'category' => 'asset',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_collateral' => true,
        ]);
        $assetApp = $this->applicationWithProduct($ab, 'APP-SET-AB', 6);
        $gps = $disclosure->gpsPostApprovalFee($assetApp);
        $this->assertNotNull($gps);
        $this->assertEquals(50_000.0, $gps['install_amount']);
        $this->assertEquals(20_000.0, $gps['monthly_amount']);
        $this->assertSame(6, $gps['months']);
        $this->assertEquals(170_000.0, $gps['total']);

        $html = view('pdf.loan-contract', [
            'application' => $assetApp,
            'agreement' => LoanAgreement::create([
                'loan_application_id' => $assetApp->id,
                'customer_id' => $assetApp->customer_id,
                'document_type' => 'loan_contract',
                'reference' => 'LC-SET-AB',
                'status' => 'sent',
            ]),
            'snapshot' => array_merge(
                $disclosure->companyIdentity(),
                $disclosure->penaltyDisclosure($assetApp),
                [
                    'locale' => 'en',
                    'customer_name' => 'Asha Mushi',
                    'principal' => 500_000,
                    'displayed_monthly_rate' => 0.15,
                    'tenure_months' => 6,
                    'is_asset_loan' => true,
                    'gps_fee' => $gps,
                    'recovery_schedule' => $disclosure->recoverySchedule($assetApp),
                    'repayment_schedule' => [],
                ]
            ),
        ])->render();

        $this->assertStringContainsString('GPS (post-approval)', $html);
        $this->assertStringContainsString('deactivation has no extra borrower charge', $html);
        $this->assertStringNotContainsString('GPS Partner', $html);
    }

    public function test_unsigned_contract_follows_live_settings_signed_contract_stays_frozen(): void
    {
        Storage::fake('public');

        Setting::set('recovery.fee_type.legal_partner', 'fixed');
        Setting::set('recovery.fixed_amount.legal_partner', 100_000);

        $application = $this->applicationWithProduct($this->product(), 'APP-SET-FREEZE');
        $disclosure = app(LoanAgreementDisclosureService::class);
        $service = app(LoanAgreementService::class);
        $scheduleAt100k = $disclosure->recoverySchedule($application);

        Storage::disk('public')->put('agreements/LC-LIVE.pdf', '%PDF-1.4 unsigned');
        $unsigned = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-LIVE',
            'status' => 'sent',
            'file_path' => 'agreements/LC-LIVE.pdf',
            'snapshot' => [
                'customer_name' => 'Asha Mushi',
                'principal' => 500_000,
                'recovery_schedule' => $scheduleAt100k,
                'document_version' => LoanAgreementDisclosureService::DOCUMENT_VERSION,
            ],
        ]);

        Storage::disk('public')->put('agreements/LC-FROZEN.pdf', '%PDF-1.4 signed');
        $signed = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $application->customer_id,
            'document_type' => 'final_loan_contract',
            'reference' => 'LC-FROZEN',
            'status' => 'signed',
            'signed_at' => now(),
            'file_path' => 'agreements/LC-FROZEN.pdf',
            'snapshot' => [
                'customer_name' => 'Asha Mushi',
                'principal' => 500_000,
                'recovery_schedule' => $scheduleAt100k,
                'document_version' => LoanAgreementDisclosureService::DOCUMENT_VERSION,
            ],
        ]);

        Setting::set('recovery.fixed_amount.legal_partner', 250_000);

        $service->ensureBrandedPdf($unsigned);
        $liveLegal = collect(data_get($unsigned->fresh()->snapshot, 'recovery_schedule.stages'))
            ->firstWhere('type', 'legal_partner');
        $this->assertStringContainsString('250,000', $liveLegal['display_en']);

        $service->refreshBrandedPdf($signed);
        $frozenLegal = collect(data_get($signed->fresh()->snapshot, 'recovery_schedule.stages'))
            ->firstWhere('type', 'legal_partner');
        $this->assertStringContainsString('100,000', $frozenLegal['display_en']);
        $this->assertStringNotContainsString('250,000', $frozenLegal['display_en']);
    }

    public function test_salary_advance_product_gets_its_own_contract_module(): void
    {
        $product = LoanProduct::create([
            'code' => 'SA',
            'name' => 'Salary Advance',
            'category' => 'salary_advance',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $application = $this->applicationWithProduct($product, 'APP-SA');
        $profile = app(LoanAgreementProductProfile::class)->for($application);

        $this->assertTrue($profile['is_salary_advance']);
        $this->assertFalse($profile['show_group']);
        $this->assertFalse($profile['gps_post_approval']);

        $html = view('pdf.loan-contract', [
            'application' => $application,
            'agreement' => LoanAgreement::create([
                'loan_application_id' => $application->id,
                'customer_id' => $application->customer_id,
                'document_type' => 'loan_contract',
                'reference' => 'LC-SA',
                'status' => 'sent',
            ]),
            'snapshot' => [
                'locale' => 'en',
                'customer_name' => 'Asha Mushi',
                'principal' => 500_000,
                'displayed_monthly_rate' => 0.15,
                'tenure_months' => 12,
                'is_salary_advance' => true,
                'contract_modules' => $profile,
                'recovery_schedule' => ['stages' => []],
                'repayment_schedule' => [],
            ],
        ])->render();

        $this->assertStringContainsString('3A. Salary advance facility', $html);
        $this->assertStringContainsString('The agreed term is 12 months', $html);
        $this->assertStringNotContainsString('typically up to 12 months', $html);
        $this->assertStringNotContainsString('16. Group loans', $html);
        $this->assertStringNotContainsString('GPS (post-approval)', $html);
    }

    public function test_contract_examples_and_clauses_come_from_live_settings(): void
    {
        Setting::set('loan.default_penalty_rate', 2);
        Setting::set('loan.default_grace_days', 5);
        Setting::set('loan.penalty_cap_percent', 30);
        Setting::set('legal.default_clause', 'LIVE-DEFAULT-CLAUSE-FROM-SETTINGS');
        Setting::set('legal.jurisdiction', 'United Republic of Tanzania');

        $product = $this->product();
        $product->update([
            'default_grace_days' => 5,
            'penalty_rate_percent' => 2,
            'penalty_basis' => 'per_day',
        ]);
        $application = $this->applicationWithProduct($product, 'APP-LIVE-VARS');
        $application->load('product', 'customer');
        $disclosure = app(LoanAgreementDisclosureService::class);
        $penalty = $disclosure->penaltyDisclosure($application);
        $examples = $disclosure->workedExamples($penalty, 500_000, 45_000, [
            ['interest_due' => 8_000],
        ]);

        $html = view('pdf.loan-contract', [
            'application' => $application,
            'agreement' => LoanAgreement::create([
                'loan_application_id' => $application->id,
                'customer_id' => $application->customer_id,
                'document_type' => 'loan_contract',
                'reference' => 'LC-LIVE-VARS',
                'status' => 'sent',
            ]),
            'snapshot' => array_merge(
                $disclosure->companyIdentity(),
                $penalty,
                $examples,
                [
                    'locale' => 'en',
                    'customer_name' => 'Asha Mushi',
                    'principal' => 500_000,
                    'displayed_monthly_rate' => 0.15,
                    'tenure_months' => 12,
                    'estimated_emi' => 45_000,
                    'legal_clauses' => app(LegalSettingsService::class)->contractClauses(),
                    'worked_examples' => $examples,
                    'facility_charges' => $disclosure->facilityCharges($application),
                    'recovery_schedule' => $disclosure->recoverySchedule($application),
                    'repayment_schedule' => [],
                ]
            ),
        ])->render();

        $this->assertStringContainsString('LIVE-DEFAULT-CLAUSE-FROM-SETTINGS', $html);
        $this->assertStringContainsString('5-day grace', $html);
        $this->assertStringContainsString('2.00%', $html);
        $this->assertStringContainsString('Example using this facility', $html);
        $this->assertStringNotContainsString('3-day grace', $html);
        $this->assertStringNotContainsString('TZS 50,000 at 1% per day after a 3-day grace', $html);
        $this->assertStringNotContainsString('if TZS 100,000 principal, TZS 10,000 interest and TZS 5,000 penalty', $html);
    }

    private function applicationWithProduct(LoanProduct $product, string $number, int $tenure = 12): LoanApplication
    {
        return LoanApplication::create([
            'customer_id' => $this->borrower()->id,
            'loan_product_id' => $product->id,
            'application_number' => $number,
            'requested_amount' => 500_000,
            'requested_tenure_months' => $tenure,
            'status' => 'approved',
        ]);
    }

    public function test_borrower_accepts_contract_with_pin_not_otp(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-3',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'loan_contract',
            'reference' => 'LC-AGR-PIN',
            'status' => 'sent',
        ]);

        [$ok, $message] = app(LoanAgreementService::class)->signWithPin($agreement, '1234', '127.0.0.1', 'phpunit');

        $this->assertTrue($ok, $message);
        $this->assertSame('pin', $agreement->fresh()->signature_method);
    }

    public function test_ceo_and_finance_manager_signatories_can_be_created(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.signatories.store'), [
                'name' => 'CEO Test',
                'signatory_type' => 'ceo',
                'position' => 'Chief Executive Officer',
                'is_active' => '1',
                'signature_touched' => '0',
            ])
            ->assertRedirect(route('admin.settings.signatories.index'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.signatories.store'), [
                'name' => 'FM Test',
                'signatory_type' => 'finance_manager',
                'position' => 'Finance manager',
                'is_active' => '1',
                'signature_touched' => '0',
            ])
            ->assertRedirect(route('admin.settings.signatories.index'));

        $this->assertTrue(CompanySignatory::query()->where('signatory_type', 'ceo')->exists());
        $this->assertTrue(CompanySignatory::query()->where('signatory_type', 'finance_manager')->exists());
    }

    public function test_offer_letter_shows_borrower_guarantor_or_group_members(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-AGR-PARTIES',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 12,
            'status' => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id' => $customer->id,
            'document_type' => 'offer_letter',
            'reference' => 'OL-AGR-PARTIES',
            'status' => 'sent',
        ]);

        $individual = view('pdf.offer-letter', [
            'application' => $application->load('product'),
            'agreement' => $agreement,
            'snapshot' => [
                'locale' => 'en',
                'customer_name' => 'Asha Mushi',
                'customer_id' => '19900101123456789012',
                'customer_address' => '12 Uhuru Street',
                'customer_phone' => '255712000111',
                'guarantor_name' => 'Juma Guarantor',
                'guarantor_nida' => '19800101123456789012',
                'guarantor_phone' => '255712000222',
                'application_number' => 'APP-AGR-PARTIES',
                'product_name' => 'Agreement Product',
                'product_code' => 'IL-AGR',
                'principal' => 500_000,
                'interest_rate' => 0.15,
                'displayed_monthly_rate' => 0.15,
                'tenure_months' => 12,
                'repayment_cadence' => 'monthly',
                'installment_count' => 12,
                'estimated_emi' => 45_000,
                'total_repayable' => 540_000,
            ],
        ])->render();

        $this->assertStringContainsString('kopafasta', $individual);
        $this->assertStringContainsString('Asha Mushi', $individual);
        $this->assertStringContainsString('Juma Guarantor', $individual);
        $this->assertStringContainsString('Guarantor', $individual);
        $this->assertStringNotContainsString('letter-spacing', $individual);

        $group = view('pdf.offer-letter', [
            'application' => $application,
            'agreement' => $agreement,
            'snapshot' => [
                'locale' => 'en',
                'customer_name' => 'Asha Mushi',
                'customer_id' => '19900101123456789012',
                'application_number' => 'APP-GL-PARTIES',
                'product_name' => 'Group Loan',
                'product_code' => 'GL',
                'principal' => 50_000,
                'interest_rate' => 0.18,
                'displayed_monthly_rate' => 0.18,
                'tenure_months' => 2,
                'repayment_cadence' => 'weekly',
                'installment_count' => 8,
                'estimated_emi' => 8_000,
                'total_repayable' => 64_000,
                'is_group_loan' => true,
                'group_name' => 'Umoja Group',
                'group_members' => [
                    ['name' => 'Asha Mushi', 'role' => 'leader', 'national_id' => '19900101123456789012', 'phone' => '255712000111', 'requested_amount' => 25_000],
                    ['name' => 'Neema Member', 'role' => 'member', 'national_id' => '19920202123456789012', 'phone' => '255712000333', 'requested_amount' => 25_000],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Group leader', $group);
        $this->assertStringContainsString('Group member', $group);
        $this->assertStringContainsString('Neema Member', $group);
        $this->assertStringContainsString('Umoja Group', $group);
        $this->assertStringNotContainsString('NOT APPLICABLE', $group);
        $this->assertStringContainsString('Every group member must sign', $group);
        $this->assertStringContainsString('Inasubiri', view('pdf.offer-letter', [
            'application' => $application,
            'agreement' => $agreement,
            'snapshot' => [
                'locale' => 'sw',
                'customer_name' => 'Asha Mushi',
                'customer_id' => '19900101123456789012',
                'application_number' => 'APP-GL-PARTIES',
                'product_name' => 'Group Loan',
                'product_code' => 'GL',
                'principal' => 50_000,
                'interest_rate' => 0.18,
                'displayed_monthly_rate' => 0.18,
                'tenure_months' => 2,
                'repayment_cadence' => 'weekly',
                'installment_count' => 8,
                'estimated_emi' => 8_000,
                'total_repayable' => 64_000,
                'is_group_loan' => true,
                'group_name' => 'Umoja Group',
                'group_members' => [
                    ['name' => 'Asha Mushi', 'role' => 'leader', 'national_id' => '19900101123456789012', 'phone' => '255712000111', 'requested_amount' => 25_000, 'signature_status' => 'signed'],
                    ['name' => 'Neema Member', 'role' => 'member', 'national_id' => '19920202123456789012', 'phone' => '255712000333', 'requested_amount' => 25_000, 'signature_status' => 'pending'],
                ],
            ],
        ])->render());
    }

    public function test_offer_letter_pulls_approval_purpose_and_charges_from_the_platform(): void
    {
        Storage::fake('public');

        Setting::set('recovery.fee_type.legal_partner', 'fixed');
        Setting::set('recovery.fixed_amount.legal_partner', 100_000);
        Setting::set('recovery.charges_borrower.gps_partner', false);
        Setting::set('partner_defaults.gps_installer.base_cost', 50_000);
        Setting::set('partner_defaults.gps_installer.monitoring_monthly', 20_000);
        Setting::set('partner_defaults.gps_installer.has_markup', false);
        Setting::set('partner_defaults.gps_installer.markup_percent', 0);

        $product = LoanProduct::create([
            'code' => 'AB-OL',
            'name' => 'Asset-Backed Offer',
            'category' => 'asset',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_collateral' => true,
        ]);
        $application = $this->applicationWithProduct($product, 'APP-OL-PLAT', 6);
        $application->update([
            'purpose' => 'working_capital',
            'screening_payload' => ['purpose' => 'working_capital'],
            'credit_appraisal_payload' => [
                'committee_approval' => [
                    'reason_code' => 'strong_affordability',
                    'reason_label' => config('credit_recommendation.approval_reasons.strong_affordability'),
                    'notes' => 'Capacity supports the instalment.',
                ],
            ],
        ]);

        $letter = app(LoanAgreementService::class)->generateOfferLetter($application->fresh(['customer', 'product']));
        $snapshot = $letter->fresh()->snapshot;

        $this->assertSame('strong_affordability', $snapshot['approval_reason_code'] ?? null);
        $this->assertSame('Strong affordability / repayment capacity', $snapshot['approval_reason_label'] ?? null);
        $this->assertSame('working_capital', $snapshot['purpose'] ?? null);
        $this->assertEquals(170_000.0, $snapshot['gps_fee']['total'] ?? null);
        $legal = collect(data_get($snapshot, 'recovery_schedule.stages'))->firstWhere('type', 'legal_partner');
        $this->assertNotNull($legal);
        $this->assertStringContainsString('100,000', $legal['display_en']);

        $html = view('pdf.offer-letter', [
            'application' => $application->fresh(['product']),
            'agreement' => $letter,
            'snapshot' => $snapshot,
        ])->render();

        $this->assertStringContainsString('Strong affordability / repayment capacity', $html);
        $this->assertStringContainsString('Capacity supports the instalment.', $html);
        $this->assertStringContainsString('Mtaji wa kufanya kazi', $html);
        $this->assertStringContainsString('GPS (baada ya kuidhinishwa)', $html);
        $this->assertStringContainsString(format_money(170_000), $html);
        $this->assertStringContainsString('100,000', $html);
        $this->assertStringContainsString('Muda wa msamaha', $html);
    }

    public function test_rejection_letter_lists_catalog_reasons_in_borrower_language_and_keeps_capacity_figures(): void
    {
        Storage::fake('public');

        $this->borrower()->user->update(['preferences' => ['preferred_locale' => 'sw']]);

        $capacity = __('borrower.loan_profile.capacity_auto_reject_reason', [
            'amount' => format_money(2_000_000),
            'installment' => format_money(380_000),
            'capacity' => format_money(33_330),
        ], 'en');

        $application = LoanApplication::create([
            'customer_id' => $this->borrower()->id,
            'loan_product_id' => $this->product()->id,
            'application_number' => 'APP-RJ-PLAT',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'status' => 'rejected',
            'current_stage' => 'rejected',
            'rejection_reason_code' => 'repayment_exceeds_limit',
            'rejection_reason_codes' => ['repayment_exceeds_limit', 'incomplete_kyc'],
            'rejection_reason' => $capacity,
            'rejection_advice_code' => 'reapply_smaller_amount',
            'screening_payload' => [
                'capacity_auto_reject' => [
                    'is_group' => false,
                    'requested_amount' => 2_000_000,
                    'proposed_installment' => 380_000,
                    'available_capacity' => 33_330,
                ],
            ],
        ]);

        $reasons = app(LoanRejectionReasonService::class)->reasonsForLetter(
            $application->rejection_reason_codes,
            $application->rejection_reason_code,
            $application->rejection_reason,
            'sw',
        );
        $this->assertSame([
            __('rejection.reasons.repayment_exceeds_limit', [], 'sw'),
            __('rejection.reasons.incomplete_kyc', [], 'sw'),
        ], $reasons['labels']);
        $this->assertSame($capacity, $reasons['detail']);

        $letter = app(LoanAgreementService::class)->generateRejectionLetter($application->fresh(['customer', 'product']));
        $snapshot = $letter->fresh()->snapshot;

        $this->assertSame(['repayment_exceeds_limit', 'incomplete_kyc'], $snapshot['rejection_codes']);
        $this->assertSame($reasons['labels'], $snapshot['rejection_reasons']);
        $this->assertSame($capacity, $snapshot['rejection_detail']);
        $this->assertSame(__('rejection.advice.reapply_smaller_amount', [], 'sw'), $snapshot['rejection_advice']);

        $html = view('pdf.rejection-letter', [
            'application' => $application,
            'agreement' => $letter,
            'snapshot' => $snapshot,
        ])->render();

        $this->assertStringContainsString(__('rejection.reasons.repayment_exceeds_limit', [], 'sw'), $html);
        $this->assertStringContainsString(__('rejection.reasons.incomplete_kyc', [], 'sw'), $html);
        $this->assertStringContainsString(format_money(2_000_000), $html);
        $this->assertStringContainsString(format_money(380_000), $html);
        $this->assertStringContainsString(format_money(33_330), $html);
        $this->assertStringContainsString(__('rejection.advice.reapply_smaller_amount', [], 'sw'), $html);
        $this->assertStringContainsString('<li>', $html);
    }

    public function test_company_stamp_white_background_is_removed(): void
    {
        $path = storage_path('framework/testing/stamp-white-test.png');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $image = imagecreatetruecolor(4, 4);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 15, 61, 46);
        imagefilledrectangle($image, 0, 0, 3, 3, $white);
        imagefilledrectangle($image, 1, 1, 2, 2, $ink);
        imagepng($image, $path);
        imagedestroy($image);

        $out = app(LegalSettingsService::class)->transparentStampPath($path);
        $this->assertFileExists($out);
        $processed = imagecreatefrompng($out);
        $this->assertNotFalse($processed);
        $corner = imagecolorsforindex($processed, imagecolorat($processed, 0, 0));
        $this->assertGreaterThan(100, $corner['alpha']);
        imagedestroy($processed);
    }
}
