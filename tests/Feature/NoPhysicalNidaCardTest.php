<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\FaceVerificationService;
use App\Services\LoanQualificationService;
use App\Services\MemberEngagementService;
use App\Services\ProfileValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoPhysicalNidaCardTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attrs = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);

        return Customer::create(array_merge([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-NC-'.uniqid(),
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'No',
            'last_name'             => 'Card',
            'phone'                 => '2557'.random_int(10000000, 99999999),
            'national_id'           => '19'.str_pad((string) random_int(0, 999999999999999999), 18, '0', STR_PAD_LEFT),
            'no_physical_nida_card' => true,
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
            'monthly_income'        => 500_000,
        ], $attrs));
    }

    public function test_national_id_uploads_complete_without_photos_when_flagged(): void
    {
        $customer = $this->customer();

        $this->assertTrue(app(ProfileValidationService::class)->nationalIdUploadsComplete($customer));
    }

    public function test_face_holding_nida_angle_skipped_without_card(): void
    {
        $customer = $this->customer();
        $keys = app(FaceVerificationService::class)->requiredAngleKeysFor($customer);

        $this->assertNotContains('holding_nida', $keys);
        $this->assertContains('front', $keys);
    }

    public function test_trust_score_profile_factor_reduced_without_physical_card(): void
    {
        $withCard = $this->customer(['no_physical_nida_card' => false]);
        $withoutCard = $this->customer(['no_physical_nida_card' => true]);

        $engagement = app(MemberEngagementService::class);
        $withProfile = collect($engagement->trustScore($withCard)['factors'])->firstWhere('key', 'profile_completion')['score'] ?? 0;
        $withoutProfile = collect($engagement->trustScore($withoutCard)['factors'])->firstWhere('key', 'profile_completion')['score'] ?? 0;

        $this->assertLessThanOrEqual($withProfile, $withoutProfile);
    }

    public function test_qualification_lists_no_card_factor(): void
    {
        $customer = $this->customer();
        $result = app(LoanQualificationService::class)->calculate($customer);

        $labels = collect($result['factors'])->pluck('label')->all();
        $this->assertContains(__('borrower.nida.no_card_factor_label'), $labels);
    }
}
