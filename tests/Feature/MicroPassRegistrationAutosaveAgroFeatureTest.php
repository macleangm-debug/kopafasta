<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanApplicationDraftService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Database\Seeders\PublicLoanProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MicroPassRegistrationAutosaveAgroFeatureTest extends TestCase
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
            'customer_number' => 'CU-RAA-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'RAA',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'country_code' => 'TZ',
            'membership_status' => 'active',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    public function test_registration_creates_pending_account_until_pin_and_security_complete(): void
    {
        $response = $this->post(route('site.register.borrower.post'), [
            'country' => 'TZ',
            'first_name' => 'Agro',
            'last_name' => 'Tester',
            'gender' => 'female',
            'phone' => '255712345678',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect(route('site.borrower.setup-pin'));
        $response->assertSessionMissing('status');

        $user = User::query()->where('phone', '255712345678')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_active);
        $this->assertSame('pending', $user->customer?->status);
        $this->assertNull($user->customer?->onboarded_at);

        $this->actingAs($user);
        $this->get(route('site.borrower.setup-pin'))->assertOk();

        $this->post(route('site.borrower.setup-pin.post'), [
            'phase' => 'pin',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])->assertRedirect(route('site.borrower.setup-pin'));

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);

        $this->get(route('site.borrower.setup-pin'))->assertOk();
        $keys = session('pin_setup_question_keys');
        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);
        $answers = collect($keys)->mapWithKeys(fn ($key) => [$key => 'answer-'.$key])->all();

        $this->post(route('site.borrower.setup-pin.post'), [
            'phase' => 'questions',
            'answers' => $answers,
        ])->assertRedirect(route('site.borrower.dashboard'));

        $user->refresh();
        $this->assertTrue((bool) $user->is_active);
        $this->assertSame('active', $user->customer?->fresh()->status);
        $this->assertNotNull($user->customer?->fresh()->onboarded_at);
        $this->assertTrue(app(PinService::class)->hasPin($user));
        $this->assertTrue(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user));
    }

    public function test_save_draft_persists_guarantor_and_purpose_detail_payload_keys(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'IL')->where('is_active', true)->firstOrFail();

        $payload = [
            'phase' => 'application',
            'step' => 3,
            'step_key' => 'guarantor',
            'loan_product_id' => $product->id,
            'form' => [
                'requested_amount' => 500000,
                'requested_tenure_months' => 6,
                'purpose' => 'agriculture',
            ],
            'inputs' => ['product_question' => ['farming_activity_type' => 'crops']],
            'external_guarantor' => [
                'name' => 'Guarantor One',
                'phone' => '255700111222',
                'status' => 'invited',
            ],
            'internal_guarantor' => [
                'invitation_id' => 99,
                'name' => 'Internal G',
            ],
            'education_documents' => [
                'farm_activity_photos' => ['customer_document_id' => 7],
            ],
            'institution_payment' => [
                'method' => 'bank',
                'bank_name' => 'CRDB',
            ],
            'asset_substep' => 2,
            'group' => ['name' => 'Team', 'members' => []],
        ];

        $this->actingAs($customer->user)
            ->putJson(route('site.borrower.apply.draft.save'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
        $this->assertNotNull($draft);
        $this->assertSame('Guarantor One', data_get($draft->payload, 'external_guarantor.name'));
        $this->assertSame(99, (int) data_get($draft->payload, 'internal_guarantor.invitation_id'));
        $this->assertSame(7, (int) data_get($draft->payload, 'education_documents.farm_activity_photos.customer_document_id'));
        $this->assertSame('CRDB', data_get($draft->payload, 'institution_payment.bank_name'));
        $this->assertSame(2, (int) data_get($draft->payload, 'asset_substep'));
    }

    public function test_multi_page_agriculture_document_upload_merges_to_pdf(): void
    {
        Storage::fake('public');
        $this->seed(PublicLoanProductsSeeder::class);
        $customer = $this->borrower();
        $product = LoanProduct::query()->where('code', 'KB')->where('is_active', true)->firstOrFail();

        $page1 = UploadedFile::fake()->image('page1.jpg', 400, 600);
        $page2 = UploadedFile::fake()->image('page2.jpg', 400, 600);

        $this->actingAs($customer->user)
            ->postJson(route('site.borrower.apply.education-document'), [
                'loan_product_id' => $product->id,
                'document_code' => 'land_use_evidence',
                'pages' => [$page1, $page2],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('document_code', 'land_use_evidence');

        $docs = app(LoanApplicationDraftService::class)->find($customer, $product->id)?->payload['education_documents'] ?? [];
        $this->assertTrue((bool) data_get($docs, 'land_use_evidence.is_pdf'));
        $this->assertNotEmpty(data_get($docs, 'land_use_evidence.customer_document_id'));
    }

    public function test_register_borrower_page_has_no_got_it_show_notice_helper(): void
    {
        app()->setLocale('en');
        $html = $this->get(route('site.register.borrower'))->assertOk()->getContent();
        $this->assertStringNotContainsString('showNotice(', $html);
        $this->assertStringContainsString('canContinueStep2', $html);
        $this->assertTrue(
            str_contains($html, 'Enter gender') || str_contains($html, 'Weka jinsia'),
            'Expected gender placeholder Enter gender / Weka jinsia'
        );
    }

    public function test_agriculture_config_requires_farm_and_land_evidence(): void
    {
        $fields = collect(config('loan_product_questions.AG.fields'));
        $farm = $fields->firstWhere('document_code', 'farm_activity_photos');
        $land = $fields->firstWhere('document_code', 'land_use_evidence');
        $this->assertTrue((bool) ($farm['required'] ?? false));
        $this->assertTrue((bool) ($land['required'] ?? false));
        $this->assertSame('multi_page', $land['capture'] ?? null);
        $this->assertSame('location', $fields->firstWhere('key', 'farming_location')['type'] ?? null);
        $this->assertSame('income_range', $fields->firstWhere('key', 'expected_revenue')['type'] ?? null);
    }
}
