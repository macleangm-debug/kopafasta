<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Models\User;
use App\Services\PinService;
use App\Services\ProfileCompletionService;
use App\Services\ProfileRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileFaceLoopAndIdCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setMany([
            'identity_verification.require_nida' => true,
            'identity_verification.require_facial' => true,
            'identity_verification.verification_stage' => 'underwriting',
        ]);
    }

    private function makeCustomer(array $attrs = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'CU-FL'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Juma',
            'phone' => '255700'.random_int(100000, 999999),
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
            'date_of_birth' => '1990-01-01',
            'national_id' => '19900101123456789012',
            'nida_verification_status' => 'verified',
            'identity_locked' => true,
            'face_verification_status' => 'incomplete',
            'no_physical_nida_card' => false,
            'marital_status' => 'single',
            'number_of_children' => 0,
            'nok_first_name' => 'Baba',
            'nok_last_name' => 'Juma',
            'nok_phone' => '255700000002',
            'nok_relationship' => 'parent',
            'nok_region' => 'Dar es Salaam',
            'nok_district' => 'Ilala',
            'nok_street' => 'Street 1',
            'activity_type' => 'trader',
            'income_range' => '500k_1m',
            'activity_details' => ['trade_type' => 'food'],
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'street' => 'Street 1',
            'lga_officer_name' => 'Officer',
            'lga_officer_position' => 'Chair',
            'lga_officer_phone' => '255700000003',
        ], $attrs));
    }

    public function test_face_wizard_gates_auto_submit_when_already_pending_or_verified(): void
    {
        $blade = file_get_contents(resource_path('views/components/site/face-verification-wizard.blade.php'));

        $this->assertStringContainsString('isFaceAlreadySubmitted', $blade);
        $this->assertStringContainsString('faceStatus:', $blade);
        $this->assertStringContainsString("['pending', 'verified']", $blade);
    }

    public function test_nida_step_requires_front_and_back_images(): void
    {
        $frontType = DocumentType::query()->firstOrCreate(
            ['code' => 'national_id_front'],
            ['name' => 'National ID front', 'is_active' => true]
        );
        $backType = DocumentType::query()->firstOrCreate(
            ['code' => 'national_id_back'],
            ['name' => 'National ID back', 'is_active' => true]
        );

        $customer = $this->makeCustomer();
        $revision = app(ProfileRevisionService::class);
        $this->assertFalse($revision->nidaStepComplete($customer));

        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $frontType->id,
            'file_path' => 'customers/nida-front.jpg',
            'status' => 'pending',
        ]);
        $this->assertFalse($revision->nidaStepComplete($customer->fresh()));

        CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type_id' => $backType->id,
            'file_path' => 'customers/nida-back.jpg',
            'status' => 'pending',
        ]);
        $this->assertTrue($revision->nidaStepComplete($customer->fresh()));
    }

    public function test_missing_id_or_face_keeps_profile_incomplete_and_lists_actionable_items(): void
    {
        $customer = $this->makeCustomer();

        $completion = app(ProfileCompletionService::class);
        $this->assertFalse($completion->isFullyComplete($customer));
        $this->assertFalse($completion->tabStatuses($customer)['personal']['complete']);

        $summary = $completion->completionSummary($customer);
        $keys = collect($summary['actionable'])->pluck('key')->all();

        $this->assertContains('nida_front', $keys);
        $this->assertContains('nida_back', $keys);
        $this->assertContains('face', $keys);

        foreach (collect($summary['actionable'])->whereIn('key', ['nida_front', 'nida_back', 'face']) as $item) {
            $this->assertNotEmpty($item['url']);
            $this->assertStringContainsString('personal', (string) $item['url']);
        }
    }

    public function test_personal_focus_face_renders_pending_without_forced_resubmit_path(): void
    {
        $customer = $this->makeCustomer(['face_verification_status' => 'pending']);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']))
            ->assertOk()
            ->assertSee('profile-face', false)
            ->assertSee('isFaceAlreadySubmitted', false);
    }
}
