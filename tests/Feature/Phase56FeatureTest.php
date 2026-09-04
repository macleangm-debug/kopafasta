<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\ApplicationRequirementsService;
use App\Services\KycFreshnessService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase56FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_checklist_lists_stale_kyc_sections_with_labels(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-P56-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Stale',
            'last_name' => 'Borrower',
            'phone' => '255712345897',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'street' => 'Sample Street',
            'lga_officer_name' => 'Asha Juma',
            'lga_officer_position' => 'Mtendaji',
            'lga_officer_phone' => '255712345001',
            'activity_type' => 'trader',
            'income_range' => '500k_1m',
            'activity_details' => ['trade_type' => 'food'],
            'profile_section_confirmed_at' => [
                'activity' => now()->subDays(120)->toIso8601String(),
                'residence' => now()->subDays(120)->toIso8601String(),
            ],
        ]);

        $freshness = app(KycFreshnessService::class);
        $labels = $freshness->staleSectionLabels($customer);

        $this->assertContains(__('borrower.kyc.residence'), $labels);
        $this->assertContains(__('borrower.kyc.activity'), $labels);

        $checklist = app(ApplicationRequirementsService::class)->checklist($customer);
        $kycItem = collect($checklist['items'])->firstWhere('key', 'kyc_freshness');

        $this->assertNotNull($kycItem);
        $this->assertFalse($kycItem['complete']);
        $this->assertContains('residence', $kycItem['stale_sections']);
        $this->assertStringContainsString(__('borrower.kyc.residence'), $kycItem['detail']);
        $this->assertFalse($checklist['can_apply']);
    }

    public function test_section_labels_cover_known_kyc_sections(): void
    {
        $service = app(KycFreshnessService::class);

        $this->assertSame(__('borrower.kyc.residence'), $service->sectionLabel('residence'));
        $this->assertSame(__('borrower.kyc.activity'), $service->sectionLabel('activity'));
        $this->assertSame(__('borrower.profile.proof_of_income_title'), $service->sectionLabel('documents'));
        $this->assertSame(__('borrower.profile.kin_info'), $service->sectionLabel('kin'));
    }
}
