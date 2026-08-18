<?php

namespace Tests\Feature;

use App\Models\CompanySignatory;
use App\Models\Customer;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoanAgreementDisclosureService;
use App\Services\LoanAgreementService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAgreementMasterTemplateFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-AGR-01',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Asha',
            'last_name'       => 'Mushi',
            'national_id'     => '19900101123456789012',
            'phone'           => '255712000111',
            'street'          => '12 Uhuru Street',
            'ward'            => 'Kisutu',
            'district'        => 'Ilala',
            'region'          => 'Dar es Salaam',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'code'              => 'IL-AGR',
            'name'              => 'Agreement Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
    }

    public function test_contract_pdf_includes_nida_address_penalty_and_recovery_not_flat_late_fee(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $this->product()->id,
            'application_number'      => 'APP-AGR-1',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $customer->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-AGR-1',
            'status'              => 'sent',
        ]);
        $application->load('product', 'customer');

        $html = view('pdf.loan-contract', [
            'application' => $application,
            'agreement'   => $agreement,
            'snapshot'    => array_merge(
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
        $this->assertStringContainsString('Call Center Partner', $html);
        $this->assertStringContainsString('Finance manager', $html);
        $this->assertStringContainsString('English version prevails', $html);
        $this->assertStringNotContainsString(format_money(2000), $html);
        $this->assertStringNotContainsString('TAFADHALI SOMA MKATABA HUU KWA MAKINI KABLA YA KUSAINI.', $html);
    }

    public function test_contract_pdf_uses_borrower_selected_language(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $this->product()->id,
            'application_number'      => 'APP-AGR-SW',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $customer->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-AGR-SW',
            'status'              => 'sent',
        ]);
        $application->load('product', 'customer');

        $html = view('pdf.loan-contract', [
            'application' => $application,
            'agreement'   => $agreement,
            'snapshot'    => array_merge(
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
                    'recovery_schedule' => app(LoanAgreementDisclosureService::class)->recoverySchedule($application),
                    'repayment_schedule' => [],
                ]
            ),
        ])->render();

        $this->assertStringContainsString('TAFADHALI SOMA MKATABA HUU KWA MAKINI KABLA YA KUSAINI.', $html);
        $this->assertStringContainsString('toleo la Kiingereza ndilo litakalotawala', $html);
        $this->assertStringNotContainsString('PLEASE READ THIS AGREEMENT CAREFULLY BEFORE SIGNING.', $html);
        $this->assertStringNotContainsString('Loan facility', $html);
    }

    public function test_download_rewrites_stored_pdf_with_branded_template(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = $this->borrower();
        $customer->user->update(['preferences' => ['preferred_locale' => 'en']]);
        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $this->product()->id,
            'application_number'      => 'APP-AGR-BRAND',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
        ]);
        \Illuminate\Support\Facades\Storage::disk('public')->put('agreements/LC-OLD.pdf', '%PDF-1.4 old unbranded');
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $customer->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-OLD',
            'status'              => 'signed',
            'signed_at'           => now(),
            'file_path'           => 'agreements/LC-OLD.pdf',
            'snapshot'            => [
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
            \Illuminate\Support\Facades\Storage::disk('public')->get('agreements/LC-OLD.pdf'),
        );
    }

    public function test_recovery_disclosure_states_percentage_of_shared_base(): void
    {
        Setting::set('recovery.fee_base', 'principal');
        Setting::set('recovery.commission_percent.call_center', 10);
        Setting::set('recovery.markup_percent.call_center', 3);

        $application = LoanApplication::create([
            'customer_id'             => $this->borrower()->id,
            'loan_product_id'         => $this->product()->id,
            'application_number'      => 'APP-AGR-2',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
        ]);

        $schedule = app(LoanAgreementDisclosureService::class)->recoverySchedule($application);
        $callCentre = collect($schedule['stages'])->firstWhere('type', 'call_center');

        $this->assertSame('principal', $schedule['fee_base']);
        $this->assertStringContainsString('10%', $callCentre['display_en']);
        $this->assertStringContainsString('3%', $callCentre['display_en']);
        $this->assertStringContainsString('principal amount', $callCentre['display_en']);
        $this->assertStringContainsString('Posted only when this stage is actually assigned', $callCentre['display_en']);
    }

    public function test_borrower_accepts_contract_with_pin_not_otp(): void
    {
        $customer = $this->borrower();
        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $this->product()->id,
            'application_number'      => 'APP-AGR-3',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'approved',
        ]);
        $agreement = LoanAgreement::create([
            'loan_application_id' => $application->id,
            'customer_id'         => $customer->id,
            'document_type'       => 'loan_contract',
            'reference'           => 'LC-AGR-PIN',
            'status'              => 'sent',
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
                'name'              => 'CEO Test',
                'signatory_type'    => 'ceo',
                'position'          => 'Chief Executive Officer',
                'is_active'         => '1',
                'signature_touched' => '0',
            ])
            ->assertRedirect(route('admin.settings.signatories.index'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.signatories.store'), [
                'name'              => 'FM Test',
                'signatory_type'    => 'finance_manager',
                'position'          => 'Finance manager',
                'is_active'         => '1',
                'signature_touched' => '0',
            ])
            ->assertRedirect(route('admin.settings.signatories.index'));

        $this->assertTrue(CompanySignatory::query()->where('signatory_type', 'ceo')->exists());
        $this->assertTrue(CompanySignatory::query()->where('signatory_type', 'finance_manager')->exists());
    }
}
