<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\SmartLoanApplicationWizardService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C1.7 — purpose-detail steps, shell header, registration gender, document saving copy.
 */
class MicroPassC17WizardClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-C17-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'C17',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_emergency_and_agro_products_lock_purpose_and_insert_details_after_quote(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $wizard = app(SmartLoanApplicationWizardService::class);
        $fees = app(ApplicationFeePaymentService::class);

        $em = LoanProduct::query()->where('code', 'EM')->where('is_active', true)->first();
        $this->assertTrue($em->hasFixedPurpose());
        $this->assertSame('emergency', $em->fixedPurposeKey());
        $emKeys = collect($wizard->borrowerStepPlan($customer, $em, 500_000))->pluck('key')->all();
        $this->assertSame(['quote', 'emergency_details', 'guarantor', 'review', 'submit'], $emKeys);
        $this->assertTrue($fees->blocksWizardStep('emergency_details'));

        $kb = LoanProduct::query()->where('code', 'KB')->where('is_active', true)->first();
        $this->assertTrue($kb->hasFixedPurpose());
        $this->assertSame('agriculture', $kb->fixedPurposeKey());
        $kbKeys = collect($wizard->borrowerStepPlan($customer, $kb, 500_000))->pluck('key')->all();
        $this->assertSame(['quote', 'agriculture_details', 'guarantor', 'review', 'submit'], $kbKeys);
        $this->assertTrue($fees->blocksWizardStep('agriculture_details'));
    }

    public function test_purpose_detail_helpers_map_education_emergency_agriculture(): void
    {
        $this->assertSame('education_details', loan_purpose_details_step_key('education'));
        $this->assertSame('education_details', loan_purpose_details_step_key('school_fees'));
        $this->assertSame('emergency_details', loan_purpose_details_step_key('emergency'));
        $this->assertSame('emergency_details', loan_purpose_details_step_key('medical_emergency'));
        $this->assertSame('agriculture_details', loan_purpose_details_step_key('agriculture'));
        $this->assertNull(loan_purpose_details_step_key('business_expansion'));
    }

    public function test_purpose_labels_include_emergency_en_and_sw(): void
    {
        $this->assertSame('Emergency', __('activity.loan_purposes.emergency'));
        $this->assertSame('Dharura', __('activity.loan_purposes.emergency', [], 'sw'));
        $this->assertSame('Emergency Details', __('borrower.apply.steps.emergency_details'));
        $this->assertSame('Maelezo ya Dharura', __('borrower.apply.steps.emergency_details', [], 'sw'));
        $this->assertSame('Agriculture Details', __('borrower.apply.steps.agriculture_details'));
        $this->assertSame('Maelezo ya Kilimo', __('borrower.apply.steps.agriculture_details', [], 'sw'));
    }

    public function test_wizard_shell_uses_grid_account_column_and_independent_body(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $el = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.apply', ['product' => $el->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('lg:grid-cols-[16rem_minmax(0,1fr)]', $html);
        $this->assertStringContainsString('kf-chrome-topbar-desktop', $html);
        $this->assertStringContainsString('max-w-3xl mx-auto w-full min-w-0', $html);
        $topbarPos = strpos($html, 'kf-chrome-topbar-desktop');
        $wizardBodyPos = strpos($html, 'max-w-3xl mx-auto w-full min-w-0');
        $this->assertLessThan($wizardBodyPos, $topbarPos);
    }

    public function test_document_saving_copy_and_inline_progress_wiring_exist(): void
    {
        $this->assertSame('Saving…', __('borrower.apply.document_saving'));
        $this->assertSame('Inahifadhi…', __('borrower.apply.document_saving', [], 'sw'));
        $js = file_get_contents(resource_path('js/apply-wizard.js'));
        $this->assertStringContainsString('educationDocumentUploadProgress', $js);
        $this->assertStringContainsString('xhr.upload.onprogress', $js);
        $this->assertStringContainsString('syncPurposeDetailSteps', $js);
        $holder = file_get_contents(resource_path('views/site/apply/_apply-document-holder.blade.php'));
        $this->assertStringContainsString('document_saving', $holder);
        $this->assertStringNotContainsString('kfShowSaving', $holder);
    }

    public function test_guarantor_uses_canonical_share_sheet(): void
    {
        $blade = file_get_contents(resource_path('views/site/apply/_guarantor-step.blade.php'));
        $this->assertStringContainsString('kopafasta-share-sheet', $blade);
        $this->assertStringContainsString('openGuarantorShare', $blade);
        $group = file_get_contents(resource_path('views/site/apply/_group-steps.blade.php'));
        $this->assertStringContainsString('kopafasta-share-sheet', $group);
    }


    public function test_education_loan_plan_regression_still_inserts_education_details(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $el = LoanProduct::query()->where('code', 'EL')->where('is_active', true)->first();
        $keys = collect(app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $el, 500_000))
            ->pluck('key')->all();
        $this->assertSame(['quote', 'education_details', 'guarantor', 'review', 'submit'], $keys);
    }
}
