<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\LoanProductRequirement;
use App\Models\User;
use App\Services\CustomerAssetService;
use App\Services\LoanApplicationDraftService;
use App\Services\LoanApplicationProfileService;
use App\Services\LoanProductReadinessService;
use App\Services\ProfileCompletionService;
use App\Services\PublicProductPresentationService;
use App\Services\SmartLoanApplicationWizardService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MicroPassAgAbDocProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function borrower(): Customer
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'is_active' => true,
            'pin_hash' => bcrypt('1234'),
            'password' => Hash::make('secret'),
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-MP-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'AgAb',
            'last_name' => 'Profile',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'nida_verification_status' => 'verified',
            'face_verification_status' => 'verified',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'national_id' => '19900101123456789012',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => 'Samora',
            'activity_type' => 'trader',
            'income_range' => '500k_1m',
            'activity_details' => ['trade_type' => 'food'],
            'nok_first_name' => 'Next',
            'nok_last_name' => 'Kin',
            'nok_phone' => '255712348099',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Kinondoni',
            'nok_street' => 'Kin Street',
        ]);
    }

    public function test_agriculture_draft_autosave_merges_product_question_inputs(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'KB')->where('is_active', true)->firstOrFail();
        $drafts = app(LoanApplicationDraftService::class);

        $drafts->save($customer, [
            'phase' => 'application',
            'loan_product_id' => $product->id,
            'step_key' => 'agriculture_details',
            'form' => ['requested_amount' => 800000, 'requested_tenure_months' => 6, 'purpose' => 'agriculture'],
            'inputs' => [
                'product_question[farming_activity_type]' => 'crops',
                'product_question[production_stage]' => 'growing',
                'product_question[farming_region]' => 'Morogoro',
                'product_question[farming_district]' => 'Kilosa',
                'product_question[cycle_end_date]' => '2026-12-01',
                'product_question[activity_budget]' => '5m_10m',
                'product_question[expected_revenue]' => '10m_20m',
            ],
        ]);

        // Later save with empty overview fields must not wipe previously stored values.
        $drafts->save($customer, [
            'phase' => 'application',
            'loan_product_id' => $product->id,
            'step_key' => 'agriculture_details',
            'form' => ['requested_amount' => 800000, 'requested_tenure_months' => 6, 'purpose' => 'agriculture'],
            'inputs' => [
                'product_question[farming_activity_type]' => '',
                'product_question[farming_ward]' => 'Magole',
            ],
        ]);

        $payload = $drafts->find($customer, $product->id)?->payload ?? [];
        $inputs = $payload['inputs'] ?? [];
        $this->assertSame('crops', $inputs['product_question[farming_activity_type]'] ?? null);
        $this->assertSame('growing', $inputs['product_question[production_stage]'] ?? null);
        $this->assertSame('Morogoro', $inputs['product_question[farming_region]'] ?? null);
        $this->assertSame('Kilosa', $inputs['product_question[farming_district]'] ?? null);
        $this->assertSame('2026-12-01', $inputs['product_question[cycle_end_date]'] ?? null);
        $this->assertSame('5m_10m', $inputs['product_question[activity_budget]'] ?? null);
        $this->assertSame('10m_20m', $inputs['product_question[expected_revenue]'] ?? null);
        $this->assertSame('Magole', $inputs['product_question[farming_ward]'] ?? null);
    }

    public function test_ab_readiness_excludes_picture_ownership_insurance_stages(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'AB',
            'name' => 'Asset Backed',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        foreach ([
            ['name' => 'Vehicle photos (multiple)', 'description' => 'Clear photos of the vehicle'],
            ['name' => 'Proof of ownership', 'description' => 'Registration card'],
            ['name' => 'Comprehensive insurance', 'description' => 'Valid comprehensive insurance cover certificate'],
            ['name' => 'Valuation report', 'description' => 'Recent valuation report'],
        ] as $row) {
            LoanProductRequirement::create([
                'loan_product_id' => $product->id,
                'name' => $row['name'],
                'description' => $row['description'],
                'is_required' => true,
            ]);
        }

        $product->load('requirements');
        $readiness = app(LoanProductReadinessService::class)->assess($customer, $product);
        $labels = collect($readiness['documents'] ?? [])
            ->map(fn ($row) => strtolower((string) ($row['name'] ?? $row['label'] ?? '')))
            ->implode(' | ');

        $this->assertStringNotContainsString('photo', $labels);
        $this->assertStringNotContainsString('ownership', $labels);
        $this->assertStringNotContainsString('insurance', $labels);
        $this->assertStringContainsString('valuation', $labels);

        $specific = collect(__('borrower.apply.readiness.specific.AB'))
            ->pluck('label')
            ->map(fn ($v) => strtolower((string) $v))
            ->all();
        $this->assertNotContains('asset photos', $specific);
        $this->assertNotContains('ownership documents', $specific);
        $this->assertNotContains('insurance cover', $specific);
        $this->assertTrue(collect($specific)->contains(fn ($l) => str_contains($l, 'profile asset')));

        $swLabels = collect(__('borrower.apply.readiness.specific.AB', [], 'sw'))
            ->pluck('label')
            ->all();
        $this->assertSame(['Mali kwenye wasifu', 'Kiasi na muda', 'Lengo'], $swLabels);

        $presentation = app(PublicProductPresentationService::class)->forProduct($product);
        $reqNames = collect($presentation['documents'] ?? [])
            ->pluck('name')
            ->map(fn ($n) => strtolower((string) $n))
            ->all();
        $this->assertFalse(collect($reqNames)->contains(fn ($n) => str_contains($n, 'vehicle photos')));
        $this->assertFalse(collect($reqNames)->contains(fn ($n) => str_contains($n, 'proof of ownership')));
        $this->assertFalse(collect($reqNames)->contains(fn ($n) => str_contains($n, 'comprehensive insurance')));
        $this->assertTrue(collect($reqNames)->contains(fn ($n) => str_contains($n, 'valuation')));

        $stepKeys = collect(app(SmartLoanApplicationWizardService::class)
            ->borrowerStepPlan($customer, $product))
            ->pluck('key')
            ->all();
        $this->assertSame('asset_details', $stepKeys[0]);
        $this->assertNotContains('asset_photos', $stepKeys);
        $this->assertNotContains('ownership', $stepKeys);
        $this->assertNotContains('insurance', $stepKeys);

        $asset = \App\Models\CustomerAsset::make([
            'asset_type' => 'vehicle',
            'photo_paths' => ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'],
            'metadata' => [
                'photo_angles' => [
                    'front' => 'a.jpg',
                    'back' => 'b.jpg',
                    'left' => 'c.jpg',
                    'right' => 'd.jpg',
                ],
                'person_with_asset_path' => 'owner.jpg',
                'ownership_document_path' => 'own.pdf',
            ],
        ]);
        $this->assertNull(app(CustomerAssetService::class)->incompleteForApply($asset));
    }

    public function test_ab_draft_profile_steps_are_asset_amount_purpose(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'AB')->where('is_active', true)->first()
            ?? LoanProduct::create([
                'code' => 'AB',
                'name' => 'Asset Backed',
                'is_active' => true,
                'interest_rate' => 0.15,
                'min_amount' => 100_000,
                'max_amount' => 5_000_000,
                'tenure_min_months' => 3,
                'tenure_max_months' => 24,
            ]);

        $draft = app(LoanApplicationDraftService::class)->save($customer, [
            'phase' => 'application',
            'loan_product_id' => $product->id,
            'step_key' => 'asset_details',
            'form' => [
                'customer_asset_ids' => [1],
                'requested_amount' => 1_500_000,
                'requested_tenure_months' => 12,
                'purpose' => 'business',
            ],
            'inputs' => [],
        ]);

        $details = (new \ReflectionClass(LoanApplicationProfileService::class))
            ->getMethod('productDetailsForDraft');
        $details->setAccessible(true);
        $payload = $details->invoke(app(LoanApplicationProfileService::class), $draft, $product);
        $keys = collect($payload['steps'] ?? [])->pluck('key')->all();

        $this->assertSame(['details', 'amount', 'purpose'], $keys);
        $this->assertNotContains('photos', $keys);
        $this->assertNotContains('ownership', $keys);
        $this->assertNotContains('insurance', $keys);
    }

    public function test_profile_overview_shows_actionable_remaining_deep_links(): void
    {
        $customer = $this->borrower();
        $summary = app(ProfileCompletionService::class)->completionSummary($customer);

        $this->assertArrayHasKey('actionable', $summary);
        $this->assertArrayHasKey('remaining_count', $summary);
        $this->assertIsArray($summary['actionable']);

        $response = $this->actingAs($customer->user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile'))
            ->assertOk();

        $html = $response->getContent();
        foreach ([
            __('borrower.profile.hub.layperson.about_you'),
            __('borrower.profile.hub.layperson.where_you_live'),
            __('borrower.profile.hub.layperson.work_money'),
            __('borrower.profile.hub.layperson.your_assets'),
        ] as $label) {
            $this->assertTrue(
                str_contains($html, $label) || str_contains($html, e($label)),
                "Expected profile hub to include layperson label: {$label}"
            );
        }
        $this->assertTrue(
            str_contains($html, __('borrower.profile.hero_completion_percent', ['percent' => (int) ($summary['percent'] ?? 0)]))
            || str_contains($html, __('borrower.profile.hero_completion_done')),
            'Profile home should show hero completion percent or all-set state'
        );
        $this->assertTrue(
            str_contains($html, __('borrower.membership.my_card'))
            || str_contains($html, __('borrower.profile.hero_completion_done')),
            'Profile home should offer Kopafasta Card or all-set state'
        );
        $this->assertStringNotContainsString(__('borrower.profile.status.in_progress'), $html);
        $this->assertStringNotContainsString(__('borrower.profile.status.not_started'), $html);
        $this->assertStringNotContainsString(__('borrower.profile.status.under_review'), $html);

        $this->assertStringContainsString('data-inline-document-progress', file_get_contents(
            resource_path('views/components/site/document-upload.blade.php')
        ));
        $this->assertStringContainsString('data-inline-document-progress', file_get_contents(
            resource_path('views/site/borrower/profile/activity.blade.php')
        ));
        $overlay = file_get_contents(resource_path('js/saving-overlay.js'));
        $this->assertStringContainsString('data-inline-document-progress', $overlay);
        $this->assertStringContainsString('kfFormNeedsSaving', $overlay);
    }

    public function test_asset_backed_profile_evidence_helper_matches_names(): void
    {
        $this->assertTrue(LoanProductRequirement::nameIsAssetBackedProfileEvidence(
            'Vehicle photos (multiple)',
            'Clear photos of the vehicle'
        ));
        $this->assertTrue(LoanProductRequirement::nameIsAssetBackedProfileEvidence(
            'Proof of ownership',
            'Registration card'
        ));
        $this->assertTrue(LoanProductRequirement::nameIsAssetBackedProfileEvidence(
            'Comprehensive insurance',
            'Valid comprehensive insurance cover certificate'
        ));
        $this->assertFalse(LoanProductRequirement::nameIsAssetBackedProfileEvidence(
            'Valuation report',
            'Recent valuation report from an approved valuer'
        ));
    }
}
