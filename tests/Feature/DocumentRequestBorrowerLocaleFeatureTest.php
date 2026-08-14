<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\ApplicationDocumentRequestService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRequestBorrowerLocaleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_preset_labels_and_instructions_translate_for_swahili_borrowers(): void
    {
        $service = app(ApplicationDocumentRequestService::class);

        $this->assertSame(
            'Ongeza mali ya dhamana',
            $service->localizedLabel('Add collateral asset', 'sw')
        );
        $this->assertSame(
            'Taarifa mpya ya pesa kwa simu',
            $service->localizedLabel('Updated Mobile Money Statement', 'sw')
        );

        $collateralCopy = $service->localizedInstructions(
            'Add collateral asset',
            'Underwriting needs collateral for this loan. Please add a collateral asset in your profile with ownership and insurance documents.',
            'sw'
        );
        $this->assertSame('Ongeza dhamana, umiliki na bima kwenye wasifu.', $collateralCopy);

        $mobileCopy = $service->localizedInstructions(
            'Updated Mobile Money Statement',
            'Please upload an updated mobile money statement.',
            'sw'
        );
        $this->assertSame('Pakia taarifa ya pesa kwa simu ya hivi karibuni.', $mobileCopy);

        $presetLabels = array_merge(
            ApplicationDocumentRequestService::PRESET_LABELS,
            ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS,
            [
                'New National ID photo',
                'New face verification photo',
                'Identity verification photo',
            ]
        );

        foreach (array_unique($presetLabels) as $label) {
            $swLabel = $service->localizedLabel($label, 'sw');
            $this->assertNotSame($label, $swLabel, "Missing Swahili label for [{$label}]");
            $this->assertStringNotContainsString('Please ', $swLabel);

            $swCopy = $service->localizedInstructions($label, null, 'sw');
            $this->assertNotSame('', trim($swCopy));
            $this->assertLessThan(90, mb_strlen($swCopy), "Too wordy [{$label}]: {$swCopy}");
            $this->assertStringNotContainsString('Please ', $swCopy);
            $this->assertStringNotContainsString('Underwriting', $swCopy);
            $this->assertStringNotContainsString('Tafadhali', $swCopy);
            $this->assertStringNotContainsString('Ukaguzi', $swCopy);

            foreach (ApplicationDocumentRequestService::legacyPresetInstructions()[$label] ?? [] as $legacyEnglish) {
                $this->assertSame(
                    $swCopy,
                    $service->localizedInstructions($label, $legacyEnglish, 'sw'),
                    "Legacy English for [{$label}] should still translate"
                );
            }
        }
    }

    public function test_loan_application_document_cards_are_swahili_with_camera_button(): void
    {
        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        app(PinRecoveryChallengeService::class)->enroll($borrowerUser, [
            'mother_first_name' => 'Asha',
            'primary_school' => 'Uhuru Primary',
            'nida_middle4' => '4582',
        ]);
        $customer = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-LOC-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Upendo',
            'last_name' => 'Ketto',
            'phone' => '255712349111',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-LOC',
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-LOC-001',
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 800_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(ApplicationDocumentRequestService::class);
        $service->create($application, $admin, 'Add collateral asset');
        $service->create($application, $admin, 'Updated Mobile Money Statement');
        $service->create($application, $admin, 'Tax Documents');

        $html = $this->actingAs($customer->user)
            ->get(route('site.borrower.application', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ongeza mali ya dhamana', $html);
        $this->assertStringContainsString('Taarifa mpya ya pesa kwa simu', $html);
        $this->assertStringContainsString('Nyaraka za kodi', $html);
        $this->assertStringContainsString('Ongeza dhamana, umiliki na bima kwenye wasifu.', $html);
        $this->assertStringContainsString('Pakia taarifa ya pesa kwa simu ya hivi karibuni.', $html);
        $this->assertStringContainsString('Pakia nyaraka za kodi zilizoombwa.', $html);
        $this->assertStringNotContainsString('Add collateral asset', $html);
        $this->assertStringNotContainsString('Underwriting needs collateral', $html);
        $this->assertStringNotContainsString('Updated Mobile Money Statement', $html);
        $this->assertStringNotContainsString('Please upload an updated mobile money statement.', $html);
        $this->assertStringNotContainsString('Ukaguzi unahitaji dhamana', $html);
        $this->assertStringNotContainsString('Tafadhali pakia', $html);
        $this->assertStringNotContainsString('Hatufikii orodha yako ya mawasiliano', $html);
        $this->assertStringNotContainsString('Pakia picha moja au zaidi, au PDF', $html);
        $this->assertStringNotContainsString('Kamilisha hii kwenye wasifu wako', $html);
        $this->assertStringNotContainsString('Pakia hapa kwa mkopo huu', $html);
        $this->assertStringNotContainsString('Au tumia kamera', $html);
        $this->assertStringNotContainsString('Or use camera', $html);
        $this->assertStringContainsString('Kamera', $html);
        $this->assertStringContainsString('ring-1 ring-brand/20', $html);
    }
}
