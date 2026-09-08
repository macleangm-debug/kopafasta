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

    public function test_loan_product_display_order_and_sharia_inactive(): void
    {
        $this->seed(PublicLoanProductsSeeder::class);

        $expected = ['IL', 'FC', 'WL', 'AL', 'AB', 'EM', 'KB', 'EL'];
        $borrowerCodes = borrower_catalogue_products()->pluck('code')->all();
        $this->assertSame($expected, array_slice($borrowerCodes, 0, 8));
        $this->assertNotContains('SL', $borrowerCodes);

        $publicCodes = public_catalogue_products()->pluck('code')->all();
        $publicWithoutComingSoon = array_values(array_filter($publicCodes, fn ($code) => $code !== 'SAL-12'));
        $this->assertSame($expected, array_slice($publicWithoutComingSoon, 0, 8));
        $this->assertNotContains('SL', $publicCodes);

        $sharia = \App\Models\LoanProduct::query()->where('code', 'SL')->first();
        $this->assertNotNull($sharia);
        $this->assertSame('inactive', $sharia->status);
        $this->assertFalse((bool) $sharia->is_active);
    }

    public function test_agriculture_config_requires_farm_and_land_evidence(): void
    {
        $fields = collect(config('loan_product_questions.AG.fields'));
        $farm = $fields->firstWhere('document_code', 'farm_activity_photos');
        $land = $fields->firstWhere('document_code', 'land_use_evidence');
        $this->assertTrue((bool) ($farm['required'] ?? false));
        $this->assertTrue((bool) ($land['required'] ?? false));
        $this->assertSame('multi_page', $land['capture'] ?? null);
        $this->assertSame('multi_page', $fields->firstWhere('document_code', 'buyer_order_evidence')['capture'] ?? null);
        $this->assertSame('images', $farm['capture'] ?? null);
        $this->assertSame('location', $fields->firstWhere('key', 'farming_location')['type'] ?? null);
        $this->assertSame('sales_range', $fields->firstWhere('key', 'expected_revenue')['type'] ?? null);
        $this->assertSame('budget_range', $fields->firstWhere('key', 'activity_budget')['type'] ?? null);
        $this->assertNotEmpty(agriculture_budget_range_options());
        $this->assertArrayHasKey('above_100m', agriculture_sales_range_options());
        $this->assertArrayHasKey('5m_10m', agriculture_sales_range_options());
        $this->assertStringContainsString('100,000,000+', agriculture_sales_range_options()['above_100m']);
        $this->assertStringNotContainsString('Above', agriculture_sales_range_options()['above_100m']);
        $this->assertSame('Harvest date', __('borrower.apply.agriculture_details.cycle_end_date'));
    }

    public function test_incomplete_registration_login_and_register_start_are_not_hijacked(): void
    {
        $user = User::factory()->needsPinSetup()->create([
            'role' => 'borrower',
            'is_active' => false,
            'password' => Hash::make('Password1!'),
            'phone' => '255712300099',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-RT-099',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Route',
            'last_name' => 'Fix',
            'phone' => '255712300099',
            'country_code' => 'TZ',
        ]);

        // Security-questions stage: PIN set, recovery not enrolled.
        app(PinService::class)->setPin($user->fresh(), '1234');
        $user = $user->fresh();
        $this->assertTrue(app(PinService::class)->hasPin($user));
        $this->assertFalse(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user));

        $this->actingAs($user)
            ->get(route('site.borrower.setup-pin'))
            ->assertOk();

        // Ingia / Login must reach the borrower login screen — not bounce back to setup-pin.
        $this->actingAs($user)
            ->get(route('site.login'))
            ->assertRedirect(route('site.auth.entry', ['to' => 'login']));

        $this->followingRedirects()
            ->get(route('site.login'))
            ->assertOk()
            ->assertSee('name="pin"', false);

        $this->assertGuest('web');
        $this->assertNotNull(User::query()->find($user->id)); // pending account retained

        // Re-auth incomplete session for Register START check.
        $this->actingAs($user->fresh());

        $this->get(route('site.register.borrower', ['intent' => 'plus']))
            ->assertRedirect(route('site.auth.entry', ['to' => 'register', 'intent' => 'plus']));

        $register = $this->followingRedirects()
            ->get(route('site.register.borrower', ['intent' => 'plus']));
        $register->assertOk();
        $register->assertSee('name="first_name"', false);
        $register->assertSessionHas('login_redirect', route('site.borrower.plus.home'));
        $this->assertGuest('web');
    }

    public function test_incomplete_registration_can_still_resume_setup_pin_intentionally(): void
    {
        $user = User::factory()->needsPinSetup()->create([
            'role' => 'borrower',
            'is_active' => false,
            'password' => Hash::make('Password1!'),
            'phone' => '255712300088',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-RT-088',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Resume',
            'last_name' => 'Path',
            'phone' => '255712300088',
            'country_code' => 'TZ',
        ]);
        app(PinService::class)->setPin($user->fresh(), '1234');

        $this->actingAs($user->fresh())
            ->get(route('site.borrower.setup-pin'))
            ->assertOk();
    }

    public function test_incomplete_registration_layout_hides_welcome_back_and_logout(): void
    {
        $user = User::factory()->needsPinSetup()->create([
            'role' => 'borrower',
            'is_active' => false,
            'password' => Hash::make('Password1!'),
            'phone' => '255712300001',
        ]);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-INC-001',
            'type' => 'individual',
            'status' => 'pending',
            'first_name' => 'Inc',
            'last_name' => 'Reg',
            'phone' => '255712300001',
            'country_code' => 'TZ',
        ]);

        app()->setLocale('en');
        $html = $this->withSession(['locale' => 'en'])
            ->actingAs($user)
            ->get(route('site.borrower.setup-pin'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Welcome back', $html);
        $this->assertStringNotContainsString('Karibu tena', $html);
        $this->assertStringNotContainsString('>Log out<', $html);
        $this->assertStringNotContainsString('>Sign out<', $html);
        $this->assertTrue(
            str_contains($html, 'Log In') || str_contains($html, 'Ingia'),
            'Expected public Log In / Ingia CTA during incomplete registration'
        );
        $this->assertTrue(
            str_contains($html, 'Register') || str_contains($html, 'Jisajili'),
            'Expected public Register / Jisajili CTA during incomplete registration'
        );
    }

    public function test_register_continue_is_always_present_on_details_step(): void
    {
        $html = $this->get(route('site.register.borrower'))->assertOk()->getContent();
        $this->assertStringContainsString('canContinueStep2', $html);
        $this->assertStringContainsString(':disabled="!canContinueStep2"', $html);
        $this->assertStringNotContainsString('x-show="canContinueStep2"', $html);
        $this->assertFileExists(resource_path('views/components/site/document-source-picker.blade.php'));
    }

    public function test_welcome_notification_cta_points_to_loans_when_membership_off(): void
    {
        $this->post(route('site.register.borrower.post'), [
            'country' => 'TZ',
            'first_name' => 'Welcome',
            'last_name' => 'Loans',
            'gender' => 'male',
            'phone' => '255712399988',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect(route('site.borrower.setup-pin'));

        $user = User::query()->where('phone', '255712399988')->firstOrFail();
        $this->actingAs($user);
        $this->post(route('site.borrower.setup-pin.post'), [
            'phase' => 'pin',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ])->assertRedirect(route('site.borrower.setup-pin'));

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
        $log = \App\Models\NotificationLog::query()
            ->where('customer_id', $user->customer->id)
            ->where('template', 'registration_welcome')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('/loans', (string) $log->recipient);
        $this->assertStringNotContainsString('membership', (string) $log->recipient);
        $this->assertSame(__('borrower.membership.welcome_loans_cta'), data_get($log->meta, 'action_label'));
    }

    public function test_login_keeps_errors_inline_without_no_pin_modal_copy(): void
    {
        $html = $this->get(route('site.login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('No PIN set for this account', $html);
        $this->assertStringContainsString('name="pin"', $html);
    }
}
