<?php

namespace Tests\Feature;

use App\Models\CreditHistory;
use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\CrbCreditCheckService;
use App\Services\CrbFreshnessService;
use App\Services\Crb\StubCrbCreditFixture;
use App\Services\Gate3CrbPanelService;
use App\Services\ScreeningChecklistAutoVerdictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Gate3StubCrbScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_stub_builds_participant_specific_clean_report(): void
    {
        $customer = $this->customer([
            'first_name' => 'Steward',
            'middle_name' => 'Alphonce',
            'last_name' => 'Amuli',
            'gender' => 'male',
            'date_of_birth' => '1996-08-15',
            'national_id' => '19960815-12107-00005-21',
            'phone' => '255686398843',
        ]);

        $built = app(StubCrbCreditFixture::class)->build($customer, 'clean');
        $this->assertSame('clean', $built['scenario']);
        $this->assertSame('Steward Alphonce Amuli', $built['personal']['full_name']);
        $this->assertSame('Male', $built['personal']['gender']);
        $this->assertSame('19960815-12107-00005-21', $built['personal']['ids'][0]['id_number']);
        $this->assertSame('approve', $built['credit']['recommendation']);
        $this->assertSame(0, (int) $built['credit']['delinquencies']);
        $this->assertSame('stub', $built['report_meta']['driver']);
    }

    public function test_wrong_subject_fixture_is_amina_and_blocks_gate3(): void
    {
        $customer = $this->customer(['first_name' => 'Steward', 'last_name' => 'Amuli', 'gender' => 'male']);
        $history = app(CrbCreditCheckService::class)->installStubReport($customer, 'wrong_subject');
        $this->assertSame('crb_stub', $history->source);
        $this->assertSame('Amina Juma Mwinyi', data_get($history->payload, 'personal.full_name'));

        $panel = app(Gate3CrbPanelService::class)->panel(
            $this->applicationFor($customer),
            $customer->fresh(),
            ['person' => 'borrower'],
        );
        $this->assertSame('CURRENT', $panel['freshness']['status']);
        $this->assertFalse($panel['subject']['matches']);
        $this->assertSame('FAILED', $panel['chip']);
        $this->assertTrue($panel['block_secondary']);
    }

    public function test_clean_subject_match_can_pass_gate3_chip(): void
    {
        $customer = $this->customer([
            'first_name' => 'Steward',
            'middle_name' => 'Alphonce',
            'last_name' => 'Amuli',
            'gender' => 'male',
            'date_of_birth' => '1996-08-15',
            'national_id' => '19960815-12107-00005-21',
        ]);
        app(CrbCreditCheckService::class)->installStubReport($customer, 'clean');
        $panel = app(Gate3CrbPanelService::class)->panel(
            $this->applicationFor($customer),
            $customer->fresh(),
            ['person' => 'borrower'],
        );
        $this->assertSame('CURRENT', $panel['freshness']['status']);
        $this->assertTrue($panel['subject']['matches']);
        $this->assertSame('PASSED', $panel['chip']);
        $this->assertFalse($panel['block_secondary']);
        $this->assertSame('TEST / CRB STUB', $panel['freshness']['source'] ?? null);
    }

    public function test_refer_scenario_is_refer_not_hard_fail(): void
    {
        $customer = $this->customer([
            'first_name' => 'Kasimu',
            'last_name' => 'Mshamu',
            'gender' => 'male',
            'date_of_birth' => '1995-07-10',
            'national_id' => '19950710-11111-00001-11',
            'marital_status' => 'married',
            'spouse_first_name' => 'Halima',
            'spouse_last_name' => 'Mwazembe',
            'number_of_children' => 3,
        ]);
        app(CrbCreditCheckService::class)->installStubReport($customer, 'refer');
        $panel = app(Gate3CrbPanelService::class)->panel(
            $this->applicationFor($customer),
            $customer->fresh(),
            ['person' => 'borrower'],
        );
        $this->assertTrue($panel['subject']['matches']);
        $this->assertSame('REFER', $panel['chip']);
        $codes = collect($panel['flags'])->pluck('code');
        $this->assertTrue($codes->contains('crb_refer'));
        $this->assertFalse($codes->contains('name_mismatch'));
    }

    public function test_hard_fail_scenario_blocks_without_exception_path(): void
    {
        $customer = $this->customer([
            'first_name' => 'Grace',
            'last_name' => 'Guarantor',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
            'national_id' => '19900101-22222-00002-22',
        ]);
        app(CrbCreditCheckService::class)->installStubReport($customer, 'hard_fail');
        $summary = app(CrbCreditCheckService::class)->summaryForCustomer($customer);
        $auto = app(ScreeningChecklistAutoVerdictService::class)->suggest($this->applicationFor($customer), 'borrower', [
            'customer' => $customer,
            'crb' => $summary,
        ]);
        $this->assertSame('fail', $auto['credit_file.crb_reviewed']['verdict'] ?? null);
        $this->assertContains($auto['credit_file.crb_reviewed']['fail_reason_code'] ?? '', ['delinquencies', 'high_exposure']);

        $panel = app(Gate3CrbPanelService::class)->panel(
            $this->applicationFor($customer),
            $customer->fresh(),
            ['person' => 'borrower'],
        );
        $this->assertTrue($panel['subject']['matches']);
        $this->assertSame('FAILED', $panel['chip']);
    }

    public function test_no_record_scenario(): void
    {
        $customer = $this->customer(['first_name' => 'No', 'last_name' => 'Record']);
        app(CrbCreditCheckService::class)->installStubReport($customer, 'no_record');
        $summary = app(CrbCreditCheckService::class)->summaryForCustomer($customer);
        $this->assertTrue((bool) ($summary['no_record'] ?? false));
        $auto = app(ScreeningChecklistAutoVerdictService::class)->suggest($this->applicationFor($customer), 'borrower', [
            'customer' => $customer,
            'crb' => $summary,
        ]);
        $this->assertSame('crb_no_record', $auto['identity.name_vs_crb']['fail_reason_code'] ?? null);
    }

    public function test_freshness_settings_89_current_91_stale_and_setting_change(): void
    {
        Setting::set('kyc.crb_freshness_days', 90);
        $this->assertSame(90, app(CrbFreshnessService::class)->freshnessDays());

        $customer = $this->customer([
            'first_name' => 'Fresh',
            'last_name' => 'Boundary',
            'gender' => 'male',
            'date_of_birth' => '1992-02-02',
            'national_id' => '19920202-33333-00003-33',
        ]);
        $crb = app(CrbCreditCheckService::class);
        $app = $this->applicationFor($customer);

        $crb->installStubReport($customer, 'clean', now()->subDays(89));
        $panel89 = app(Gate3CrbPanelService::class)->panel($app, $customer->fresh(), ['person' => 'borrower']);
        $this->assertSame('CURRENT', $panel89['freshness']['status']);
        $this->assertSame('PASSED', $panel89['chip']);

        // A newer checked_at wins in latest() — replace with a single older report for the stale proof.
        CreditHistory::query()->where('customer_id', $customer->id)->delete();
        $crb->installStubReport($customer, 'clean', now()->subDays(91));
        $panel91 = app(Gate3CrbPanelService::class)->panel($app, $customer->fresh(), ['person' => 'borrower']);
        $this->assertSame('EXPIRED', $panel91['freshness']['status']);
        $this->assertSame('WAITING', $panel91['chip']);

        Setting::set('kyc.crb_freshness_days', 100);
        $panelAfter = app(Gate3CrbPanelService::class)->panel($app, $customer->fresh(), ['person' => 'borrower']);
        $this->assertSame(100, (int) $panelAfter['freshness']['valid_for_days']);
        $this->assertSame('CURRENT', $panelAfter['freshness']['status']);
    }

    public function test_borrower_and_guarantor_stub_reports_are_isolated(): void
    {
        $borrower = $this->customer([
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'gender' => 'male',
            'date_of_birth' => '1991-01-01',
            'national_id' => '19910101-44444-00004-44',
        ]);
        $guarantor = $this->customer([
            'first_name' => 'Guarantor',
            'last_name' => 'Two',
            'gender' => 'female',
            'date_of_birth' => '1988-05-05',
            'national_id' => '19880505-55555-00005-55',
        ]);
        $crb = app(CrbCreditCheckService::class);
        $crb->installStubReport($borrower, 'clean');
        $crb->installStubReport($guarantor, 'hard_fail');

        $app = $this->applicationFor($borrower);
        $link = $this->attachGuarantor($app, $guarantor);

        $bPanel = app(Gate3CrbPanelService::class)->panel($app, $borrower->fresh(), ['person' => 'borrower']);
        $gPanel = app(Gate3CrbPanelService::class)->panel($app, $guarantor->fresh(), [
            'person' => 'guarantor',
            'g' => $link->id,
        ]);

        $this->assertSame('Borrower One', $bPanel['subject']['crb_name']);
        $this->assertSame('Guarantor Two', $gPanel['subject']['crb_name']);
        $this->assertSame('PASSED', $bPanel['chip']);
        $this->assertSame('FAILED', $gPanel['chip']);
        $this->assertSame('crb_stub', CreditHistory::query()->where('customer_id', $borrower->id)->latest('id')->value('source'));
        $this->assertSame('crb_stub', CreditHistory::query()->where('customer_id', $guarantor->id)->latest('id')->value('source'));
    }

    public function test_matrix_borrower_pass_with_guarantor_scenarios(): void
    {
        $borrower = $this->customer([
            'first_name' => 'Leader',
            'last_name' => 'Pass',
            'gender' => 'male',
            'date_of_birth' => '1993-03-03',
            'national_id' => '19930303-66666-00006-66',
        ]);
        $crb = app(CrbCreditCheckService::class);
        $crb->installStubReport($borrower, 'clean');
        $app = $this->applicationFor($borrower);

        foreach (['clean' => 'PASSED', 'refer' => 'REFER', 'hard_fail' => 'FAILED', 'wrong_subject' => 'FAILED', 'stale' => 'WAITING'] as $scenario => $chip) {
            $g = $this->customer([
                'first_name' => 'G'.ucfirst($scenario),
                'last_name' => 'Case',
                'gender' => 'male',
                'date_of_birth' => '1985-06-06',
                'national_id' => '19850606-'.str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT).'-00007-77',
            ]);
            $crb->installStubReport($g, $scenario);
            $link = $this->attachGuarantor($app, $g);
            $panel = app(Gate3CrbPanelService::class)->panel($app, $g->fresh(), [
                'person' => 'guarantor',
                'g' => $link->id,
            ]);
            $this->assertSame($chip, $panel['chip'], 'scenario '.$scenario);
            $borrowerPanel = app(Gate3CrbPanelService::class)->panel($app, $borrower->fresh(), ['person' => 'borrower']);
            $this->assertSame('PASSED', $borrowerPanel['chip'], 'borrower must stay PASS while guarantor is '.$scenario);
        }
    }

    /** @param  array<string, mixed>  $attrs */
    private function customer(array $attrs = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-G3-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Person',
            'phone' => '25576'.random_int(1000000, 9999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'national_id' => '19900101-'.random_int(10000, 99999).'-00000-00',
            'monthly_income' => 2_000_000,
        ], $attrs));
    }

    private function applicationFor(Customer $customer): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'G3-'.random_int(100, 999),
            'name' => 'Gate3 Product',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'requires_collateral' => false,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-G3-'.random_int(1000, 9999),
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'status' => 'submitted',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }

    private function attachGuarantor(LoanApplication $app, Customer $guarantorCustomer): CustomerGuarantor
    {
        $contact = Guarantor::create([
            'first_name' => $guarantorCustomer->first_name,
            'last_name' => $guarantorCustomer->last_name,
            'phone' => $guarantorCustomer->phone,
            'relationship' => 'friend',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $app->customer_id,
            'guarantor_id' => $contact->id,
            'loan_application_id' => $app->id,
            'status' => 'approved',
        ]);
        GuarantorInvitation::create([
            'customer_id' => $app->customer_id,
            'customer_guarantor_id' => $link->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $app->loan_product_id,
            'guarantor_customer_id' => $guarantorCustomer->id,
            'type' => 'member',
            'status' => 'accepted',
            'contact' => $guarantorCustomer->phone,
            'invitee_name' => $guarantorCustomer->full_name,
            'token' => 'tok-g3-'.random_int(10000, 99999),
        ]);

        return $link->fresh('invitation');
    }
}
