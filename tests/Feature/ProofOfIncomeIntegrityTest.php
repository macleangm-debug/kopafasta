<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDisbursementAccount;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Models\User;
use App\Services\Application360Presenter;
use App\Services\ApplicationRequirementsService;
use App\Services\CustomerDossierService;
use App\Services\IncomeProofService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProofOfIncomeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setMany([
            'kyc.require_income_proof' => false,
            'kyc.require_residence_letter' => false,
            'identity_verification.require_nida' => false,
            'identity_verification.require_facial' => false,
            'identity_verification.verification_stage' => 'underwriting',
        ]);
    }

    public function test_salaried_profile_without_proof_of_income_is_incomplete(): void
    {
        $customer = $this->profileCustomer([
            'activity_type' => 'employed',
            'employment_type' => 'employed',
            'income_range' => '500000_1000000',
            'activity_details' => [
                'employer_name' => 'Acme',
                'job_title' => 'Clerk',
            ],
        ]);

        $svc = app(ProfileCompletionService::class);
        $this->assertFalse(app(IncomeProofService::class)->satisfiesRequirement($customer));
        $this->assertFalse($svc->isActivityComplete($customer));
        $this->assertLessThan(100, $svc->calculate($customer)['percent']);
        $this->assertNotEmpty($svc->incomeProofGaps($customer));
    }

    public function test_business_owner_without_proof_of_income_is_incomplete(): void
    {
        $customer = $this->completeActivityFields('business_owner');

        $svc = app(ProfileCompletionService::class);
        $this->assertFalse(app(IncomeProofService::class)->satisfiesRequirement($customer));
        $this->assertFalse($svc->isActivityComplete($customer));
        $this->assertLessThan(100, $svc->calculate($customer)['percent']);
    }

    public function test_transport_operator_without_proof_of_income_is_incomplete(): void
    {
        $customer = $this->completeActivityFields('transport_operator');

        $svc = app(ProfileCompletionService::class);
        $this->assertFalse(app(IncomeProofService::class)->satisfiesRequirement($customer));
        $this->assertFalse($svc->isActivityComplete($customer));
        $this->assertLessThan(100, $svc->calculate($customer)['percent']);
    }

    public function test_valid_proof_of_income_recalculates_completion(): void
    {
        $customer = $this->completeActivityFields('business_owner');
        $svc = app(ProfileCompletionService::class);
        $before = $svc->calculate($customer)['percent'];
        $this->assertLessThan(100, $before);

        $this->attachIncomeProof($customer, 'mobile_money_statement');

        $after = $svc->calculate($customer->fresh());
        $this->assertTrue(app(IncomeProofService::class)->satisfiesRequirement($customer->fresh()));
        $this->assertTrue($svc->isActivityComplete($customer->fresh()));
        $this->assertSame(100, $after['percent']);
    }

    public function test_existing_valid_profile_with_proof_stays_complete(): void
    {
        $customer = $this->completeActivityFields('business_owner');
        $this->attachIncomeProof($customer, 'bank_statement', 'pending_review');

        $svc = app(ProfileCompletionService::class);
        $this->assertSame(100, $svc->calculate($customer->fresh())['percent']);
        $this->assertSame('pending_review', app(IncomeProofService::class)->evidenceState($customer->fresh())['state']);
    }

    public function test_application_cannot_treat_profile_complete_when_proof_missing(): void
    {
        $customer = $this->completeActivityFields('employed', [
            'employment_type' => 'employed',
            'activity_details' => [
                'employer_name' => 'Acme',
                'job_title' => 'Clerk',
            ],
        ]);

        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
        $income = collect($checklist['items'])->firstWhere('key', 'income_proof')
            ?? collect($checklist['items'])->firstWhere('key', 'salary_slip');

        $this->assertNotNull($income);
        $this->assertFalse((bool) ($income['complete'] ?? true));
        $this->assertFalse((bool) ($checklist['can_apply'] ?? true));
        $this->assertFalse((bool) ($checklist['can_submit'] ?? true));
        $this->assertLessThan(100, (int) ($checklist['profile_percent'] ?? 100));
    }

    public function test_setting_off_cannot_bypass_proof_of_income(): void
    {
        Setting::set('kyc.require_income_proof', false);
        $customer = $this->completeActivityFields('trader');

        $this->assertTrue(app(IncomeProofService::class)->isRequired());
        $this->assertFalse(app(IncomeProofService::class)->satisfiesRequirement($customer));
        $this->assertLessThan(100, app(ProfileCompletionService::class)->calculate($customer)['percent']);
    }

    public function test_rejected_statement_does_not_satisfy_and_needs_replacement(): void
    {
        $customer = $this->completeActivityFields('business_owner');
        $this->attachIncomeProof($customer, 'mobile_money_statement', 'rejected');

        $state = app(IncomeProofService::class)->evidenceState($customer->fresh());
        $this->assertFalse(app(IncomeProofService::class)->satisfiesRequirement($customer->fresh()));
        $this->assertSame('needs_replacement', $state['state']);
        $this->assertSame('Needs replacement', $state['status_label']);
        $this->assertLessThan(100, app(ProfileCompletionService::class)->calculate($customer->fresh())['percent']);
    }

    public function test_profile_360_shows_proof_of_income_state_and_statement_document(): void
    {
        $customer = $this->completeActivityFields('transport_operator');
        $this->attachIncomeProof($customer, 'mobile_money_statement', 'pending_review');

        $dossier = app(CustomerDossierService::class)->dossier($customer->fresh());
        $this->assertSame('pending_review', $dossier['income_proof']['state'] ?? null);
        $this->assertSame('Pending review', $dossier['income_proof']['status_label'] ?? null);
        $codes = collect($dossier['documents_by_context']['activity'] ?? [])
            ->map(fn ($doc) => $doc->documentType?->code)
            ->all();
        $this->assertContains('mobile_money_statement', $codes);

        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', ['customer' => $customer, 'tab' => 'activity']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Proof of Income', $html);
        $this->assertStringContainsString('Pending review', $html);
        $this->assertStringContainsString('mobile money statement', strtolower($html));
    }

    public function test_application_360_shows_compact_proof_of_income_status(): void
    {
        $customer = $this->completeActivityFields('business_owner');
        $admin = User::factory()->create(['role' => 'admin']);
        $borrower = User::factory()->create(['role' => 'borrower']);
        $customer->update(['user_id' => $borrower->id]);
        $product = \App\Models\LoanProduct::query()->where('code', 'IL')->first()
            ?? \App\Models\LoanProduct::create([
                'code' => 'IL',
                'name' => 'Individual Loan',
                'category' => 'individual',
                'interest_rate' => 0.18,
                'min_amount' => 500_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'is_active' => true,
                'status' => 'active',
                'application_fee_amount' => 10_000,
            ]);
        $app = \App\Models\LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-POI-'.random_int(1000, 9999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 12,
            'purpose' => 'business',
            'submitted_at' => now()->subDay(),
            'application_fee_status' => 'paid',
        ]);

        $panel = app(Application360Presenter::class)->forApplication($app->fresh(), $admin);
        $borrowerCard = collect($panel['people'])->firstWhere('role', 'Borrower');
        $this->assertSame('missing', $borrowerCard['income_proof']['state'] ?? null);

        $this->attachIncomeProof($customer, 'bank_statement', 'pending_review');
        $panel = app(Application360Presenter::class)->forApplication($app->fresh(), $admin);
        $borrowerCard = collect($panel['people'])->firstWhere('role', 'Borrower');
        $this->assertSame('pending_review', $borrowerCard['income_proof']['state'] ?? null);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', $app))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Proof of Income', $html);
        $this->assertStringContainsString('Pending review', $html);
    }

    public function test_guarantor_invitation_does_not_bind_same_name_member(): void
    {
        $borrower = $this->completeActivityFields('business_owner');
        $this->attachIncomeProof($borrower, 'bank_statement');
        $sameName = $this->profileCustomer([
            'first_name' => 'Paulo',
            'last_name' => 'Albert Mtawa',
            'phone' => '255618000111',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = \App\Models\LoanProduct::query()->where('code', 'IL')->first()
            ?? \App\Models\LoanProduct::create([
                'code' => 'IL',
                'name' => 'Individual Loan',
                'category' => 'individual',
                'interest_rate' => 0.18,
                'min_amount' => 500_000,
                'max_amount' => 10_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
                'is_active' => true,
                'status' => 'active',
                'application_fee_amount' => 10_000,
            ]);
        $app = \App\Models\LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-G-'.random_int(1000, 9999),
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
            'requested_amount' => 1_000_000,
            'requested_tenure_months' => 12,
            'purpose' => 'business',
            'submitted_at' => now()->subDay(),
            'application_fee_status' => 'paid',
        ]);
        $record = \App\Models\Guarantor::create([
            'first_name' => 'Paulo',
            'last_name' => 'Albert Mtawa',
            'phone' => '+255667094545',
            'relationship' => 'relative',
        ]);
        $link = \App\Models\CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $record->id,
            'loan_application_id' => $app->id,
            'status' => 'rejected',
        ]);
        \App\Models\GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $app->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'guarantor_customer_id' => null,
            'type' => 'external',
            'channel' => 'sms',
            'invitee_name' => 'Paulo Albert Mtawa',
            'token' => 'poi-g-'.random_int(1000, 9999),
            'short_code' => 'POIG'.random_int(100, 999),
            'contact' => '+255667094545',
            'status' => 'rejected',
            'expires_at' => now()->addDays(7),
        ]);

        $panel = app(Application360Presenter::class)->forApplication($app->fresh(), $admin);
        $guarantor = collect($panel['people'])->firstWhere('role', 'Guarantor');
        $this->assertSame('Paulo Albert Mtawa', $guarantor['name'] ?? null);
        $this->assertArrayHasKey('customer_id', $guarantor);
        $this->assertNull($guarantor['customer_id']);
        $this->assertFalse((bool) ($guarantor['is_member'] ?? true));
        $this->assertNotSame((int) $sameName->id, (int) ($guarantor['customer_id'] ?? 0));
        $this->assertNotSame((int) $borrower->id, (int) ($guarantor['customer_id'] ?? 0));
    }

    /** @param  array<string, mixed>  $overrides */
    private function completeActivityFields(string $type, array $overrides = []): Customer
    {
        $details = match ($type) {
            'employed' => [
                'employer_name' => 'Acme',
                'job_title' => 'Clerk',
            ],
            'transport_operator' => [
                'vehicle_type' => 'motorcycle',
            ],
            'trader' => [
                'trade_type' => 'food',
            ],
            default => [
                'business_name' => 'Uat Shop',
                'region' => 'Dar es Salaam',
                'district' => 'Ilala',
                'street' => 'Market Street',
                'employee_count' => '1',
            ],
        };

        return $this->profileCustomer(array_merge([
            'activity_type' => $type,
            'employment_type' => $type,
            'income_range' => '500000_1000000',
            'activity_details' => $details,
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function profileCustomer(array $overrides = []): Customer
    {
        $branch = Branch::create([
            'code' => 'PI'.bin2hex(random_bytes(3)),
            'name' => 'POI Integrity',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'borrower',
            'email' => 'poi.'.uniqid().'@example.com',
        ]);

        $customer = Customer::query()->create(array_merge([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'customer_number' => 'C'.random_int(100000, 999999),
            'member_no' => 'M'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Uat',
            'last_name' => 'Borrower',
            'phone' => '2557'.random_int(10000000, 99999999),
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'marital_status' => 'single',
            'number_of_children' => 0,
            'nok_first_name' => 'Kin',
            'nok_last_name' => 'Person',
            'nok_phone' => '255711111111',
            'nok_relationship' => 'sibling',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Ilala',
            'nok_street' => 'Kin Street',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'street' => 'Home Street',
            'lga_officer_name' => 'Officer',
            'lga_officer_position' => 'VEO',
            'lga_officer_phone' => '255722222222',
            'no_physical_nida_card' => true,
        ], $overrides));

        CustomerDisbursementAccount::create([
            'customer_id' => $customer->id,
            'type' => 'mobile_money',
            'mobile_provider' => 'mpesa',
            'mobile_number' => '255700000088',
            'account_name' => $customer->first_name.' '.$customer->last_name,
            'is_default' => true,
        ]);

        return $customer->fresh();
    }

    private function attachIncomeProof(Customer $customer, string $code, string $status = 'pending_review'): CustomerDocument
    {
        $type = DocumentType::query()->firstOrCreate(
            ['code' => $code],
            ['name' => str_replace('_', ' ', $code), 'is_active' => true, 'category' => 'kyc'],
        );

        return CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $type->id,
            'loan_application_id' => null,
            'file_path' => 'kyc/'.$code.'.pdf',
            'status' => $status,
        ]);
    }
}
