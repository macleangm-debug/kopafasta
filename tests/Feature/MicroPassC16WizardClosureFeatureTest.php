<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerPayment;
use App\Models\DocumentType;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanApplicationDraftService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * C1.6 — Education Continue path, shell header geometry, Emergency Document Holder.
 */
class MicroPassC16WizardClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C16-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C16',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_wizard_uses_full_shell_width_with_independently_constrained_body(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $el = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();
        $this->assertNotNull($el);

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', ['product' => $el->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('kf-chrome-topbar-desktop', $html);
        $this->assertStringContainsString('max-w-3xl mx-auto w-full min-w-0', $html);
        // Topbar must not live inside the wizard max-w-3xl wrapper.
        $topbarPos = strpos($html, 'kf-chrome-topbar-desktop');
        $wizardBodyPos = strpos($html, 'max-w-3xl mx-auto w-full min-w-0');
        $this->assertNotFalse($topbarPos);
        $this->assertNotFalse($wizardBodyPos);
        $this->assertLessThan($wizardBodyPos, $topbarPos);
    }

    public function test_education_validation_messages_exist_in_en_and_sw(): void
    {
        $this->assertSame('Enter the school or institution name.', __('borrower.apply.education_details.school_required'));
        $this->assertSame('Weka jina la shule au taasisi.', __('borrower.apply.education_details.school_required', [], 'sw'));
        $this->assertSame('Upload the admission / fee letter.', __('borrower.apply.education_details.document_required'));
        $this->assertSame('Pakia barua ya kujiunga / ada.', __('borrower.apply.education_details.document_required', [], 'sw'));
    }

    public function test_education_details_continue_unlock_logic_is_in_wizard_bundle(): void
    {
        $js = file_get_contents(resource_path('js/apply-wizard.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('Paid-return lock only prevents demotion', $js);
        $this->assertStringContainsString("if (this.stepKey === 'education_details')", $js);
        $this->assertStringContainsString('this.educationErrors = {}', $js);
        $this->assertStringContainsString('Persist unlocked step_key so refresh/resume does not rewind', $js);
    }

    public function test_education_document_upload_persists_on_draft(): void
    {
        Storage::fake('public');
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $el = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $el->id,
            'phase' => 'application',
            'step' => 1,
            'draft_reference' => 'APP-C16-EL',
            'payload' => [
                'form' => [
                    'loan_product_id' => $el->id,
                    'requested_amount' => 500_000,
                    'requested_tenure_months' => 6,
                    'purpose' => 'education',
                ],
                'step_key' => 'education_details',
            ],
            'saved_at' => now(),
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $el->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => max(1, (int) $el->application_fee_amount),
            'currency' => 'TZS',
            'status' => 'paid',
            'reference' => 'PAY-C16-EL',
            'paid_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('admission.pdf', 120, 'application/pdf');
        $response = $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.education-document'), [
                'loan_product_id' => $el->id,
                'document_code' => 'admission_fee_letter',
                'file' => $file,
            ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $docs = $response->json('education_documents');
        $this->assertArrayHasKey('admission_fee_letter', $docs);

        $draft = app(LoanApplicationDraftService::class)->find($customer, $el->id);
        $this->assertSame(
            (int) $docs['admission_fee_letter']['customer_document_id'],
            (int) ($draft->payload['education_documents']['admission_fee_letter']['customer_document_id'] ?? 0)
        );
    }

    public function test_emergency_supporting_evidence_uses_document_holder(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $em = LoanProduct::query()->where('code', 'EM')->where('is_active', true)->first();
        $this->assertNotNull($em);

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', ['product' => $em->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('supporting_evidence', $html);
        $this->assertStringContainsString('singleImageDocumentUpload', $html);
        $this->assertStringContainsString(__('borrower.profile.view_document'), $html);
        $this->assertStringContainsString(__('borrower.profile.replace_document'), $html);
        $this->assertStringContainsString(__('borrower.profile.remove_document'), $html);
        $this->assertStringNotContainsString('Describe hospital bill', $html);
    }

    public function test_emergency_supporting_evidence_upload_persists_on_draft(): void
    {
        Storage::fake('public');
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $em = LoanProduct::query()->where('code', 'EM')->where('is_active', true)->first();

        LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $em->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => 'APP-C16-EM',
            'payload' => [
                'form' => [
                    'loan_product_id' => $em->id,
                    'requested_amount' => 200_000,
                    'requested_tenure_months' => 3,
                    'purpose' => 'emergency',
                ],
                'step_key' => 'quote',
            ],
            'saved_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('bill.jpg');
        $response = $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.education-document'), [
                'loan_product_id' => $em->id,
                'document_code' => 'supporting_evidence',
                'file' => $file,
            ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertArrayHasKey('supporting_evidence', $response->json('education_documents'));

        $draft = app(LoanApplicationDraftService::class)->find($customer, $em->id);
        $this->assertNotEmpty($draft->payload['education_documents']['supporting_evidence']['customer_document_id'] ?? null);
        $this->assertTrue(
            CustomerDocument::query()
                ->where('customer_id', $customer->id)
                ->where('id', $draft->payload['education_documents']['supporting_evidence']['customer_document_id'])
                ->exists()
        );
        $this->assertTrue(
            DocumentType::query()->where('code', 'supporting_evidence')->exists()
        );
    }

    public function test_emergency_labels_exist_in_en_and_sw(): void
    {
        $this->assertSame('Supporting evidence (optional)', __('borrower.apply.emergency.supporting_evidence'));
        $this->assertSame('Ushahidi wa ziada (si lazima)', __('borrower.apply.emergency.supporting_evidence', [], 'sw'));
    }
}
