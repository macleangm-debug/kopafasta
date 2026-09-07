<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\SmartLoanApplicationWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroPassCEducationKilimoFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower', 'pin_hash' => bcrypt('1234')]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-MPC-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Micro',
            'last_name' => 'Pass',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
        ]);
    }

    public function test_education_loan_places_details_after_fee_before_guarantor(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'EL',
            'name' => 'Education Loan',
            'category' => 'education',
            'purpose_mode' => 'fixed',
            'fixed_purpose' => 'education',
            'is_active' => true,
            'interest_rate' => 0.16,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
            'application_fee_amount' => 10_000,
        ]);

        $keys = collect(app(SmartLoanApplicationWizardService::class)
            ->borrowerStepPlan($customer, $product, 500_000))
            ->pluck('key')
            ->all();

        $this->assertSame(['quote', 'education_details', 'guarantor', 'review', 'submit'], $keys);
        $this->assertTrue($product->hasFixedPurpose());
        $this->assertSame('education', $product->fixedPurposeKey());
        $this->assertTrue(app(ApplicationFeePaymentService::class)->blocksWizardStep('education_details'));
        $this->assertSame(
            'education_details',
            app(ApplicationFeePaymentService::class)->nextStepAfterApplicationFee($customer, $product, [
                'form' => ['requested_amount' => 500_000],
            ])
        );

        $el = config('loan_product_questions.EL');
        $this->assertSame('education_details', $el['fold_into'] ?? null);
        $admission = collect($el['fields'])->firstWhere('key', 'admission_letter');
        $this->assertSame('document', $admission['type'] ?? null);
        $this->assertStringNotContainsString('reference', strtolower((string) ($admission['label'] ?? $admission['label_key'] ?? '')));
    }

    public function test_kilimo_purpose_is_fixed_from_product_settings(): void
    {
        $product = LoanProduct::create([
            'code' => 'KB',
            'name' => 'Kilimo Boost',
            'category' => 'agriculture',
            'purpose_mode' => 'fixed',
            'fixed_purpose' => 'agriculture',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);

        $this->assertTrue($product->hasFixedPurpose());
        $this->assertSame('agriculture', $product->fixedPurposeKey());

        $payload = loan_product_wizard_payload($product);
        $this->assertSame('fixed', $payload['purpose_mode']);
        $this->assertSame('agriculture', $payload['fixed_purpose']);

        $other = LoanProduct::create([
            'code' => 'IL-MPC',
            'name' => 'Individual',
            'category' => 'individual',
            'purpose_mode' => 'free',
            'is_active' => true,
            'interest_rate' => 0.19,
            'min_amount' => 500_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);
        $this->assertFalse($other->hasFixedPurpose());
    }

    public function test_education_sw_labels_exist(): void
    {
        $this->assertSame('Maelezo ya Elimu', __('borrower.apply.steps.education_details', [], 'sw'));
        $this->assertSame('Jina la Shule / Taasisi', __('borrower.apply.education_details.institution', [], 'sw'));
        $this->assertSame('Barua ya Kujiunga / Ada', __('borrower.apply.education_details.admission_letter', [], 'sw'));
        $this->assertSame('Pakia', __('borrower.profile.upload', [], 'sw'));
        $this->assertSame('Kamera', __('borrower.document_upload.camera', [], 'sw'));
    }
}
