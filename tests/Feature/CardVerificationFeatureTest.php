<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBorrowerPin;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use App\Services\CardVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardVerificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_form_page_loads_with_predefined_prefixes(): void
    {
        $response = $this->get(route('site.card.verify'));

        $response->assertOk();
        $response->assertSeeText(__('site.card_verify.heading'));
        $response->assertSee('KPF-TZ-', false);
        $response->assertSee('PT-SP-TZ-', false);
        $response->assertDontSee('PT-CP-TZ-', false);
        $response->assertDontSee('PT-TW-TZ-', false);
        $response->assertSeeText(__('site.card_verify.number_hint'));
    }

    public function test_member_lookup_redirects_to_short_verify_without_dashes_in_input(): void
    {
        $user = User::factory()->create();
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-CV-001',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Verify',
            'last_name' => 'Member',
            'phone' => '255712340001',
            'member_no' => 'KPF-TZ-AB12',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addMonths(11),
        ]);

        $response = $this->post(route('site.card.verify.lookup'), [
            'type' => 'member',
            'number' => 'AB12',
        ]);

        $response->assertRedirect(route('site.short.member', ['memberNo' => 'KPF-TZ-AB12']));
    }

    public function test_partner_short_link_verifies_active_supplier(): void
    {
        Partner::create([
            'name' => 'Dar Motors Ltd',
            'category' => 'supplier',
            'status' => 'active',
            'phone' => '255712340002',
            'partner_number' => 'PT-SP-TZ-Z9Y8',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addMonths(11),
        ]);

        $response = $this->get(route('site.short.partner', ['partnerNo' => 'PTSPTZZ9Y8']));

        $response->assertOk();
        $response->assertSeeText('DAR MOTORS LTD');
        $response->assertSeeText(__('site.card_verify.verified_badge'));
        $response->assertSeeText(__('site.card_verify.verify_another'));
    }

    public function test_compose_id_uses_predefined_prefix_and_strips_dashes(): void
    {
        $service = app(CardVerificationService::class);

        $this->assertSame('KPF-TZ-AB12', $service->composeId('member', 'ab12'));
        $this->assertSame('KPF-TZ-AB12', $service->composeId('member', 'KPF-TZ-AB12'));
        $this->assertSame('PT-DC-TZ-Q1W2', $service->composeId('debt_collector', 'q1w2'));
        $this->assertSame('PT-DC-TZ-Q1W2', $service->composeId('debt_collector', 'PTDCTZQ1W2'));
    }

    public function test_public_asset_request_only_requires_asset_name_and_avoids_free_signup_copy(): void
    {
        $response = $this->get(route('site.marketplace'));
        $response->assertOk();
        $response->assertDontSee('Sign up free', false);
        $response->assertSeeText(__('borrower.marketplace.request_signup_hint'));
        $response->assertDontSee('name="budget"', false);
        $response->assertDontSee('name="preferred_tenure_months"', false);

        $post = $this->post(route('site.marketplace.request'), [
            'asset_name' => 'Toyota Hilux 2019',
        ]);

        $post->assertRedirect(route('site.register.borrower'));
        $this->assertSame('Toyota Hilux 2019', session('pending_asset_request.asset_name'));
        $this->assertArrayNotHasKey('budget', session('pending_asset_request', []));
    }

    public function test_member_verify_page_includes_verify_another_cta(): void
    {
        $user = User::factory()->create();
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-CV-002',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Another',
            'last_name' => 'Cta',
            'phone' => '255712340003',
            'member_no' => 'KPF-TZ-CT99',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addMonths(11),
        ]);

        $this->get(route('site.short.member', ['memberNo' => 'KPF-TZ-CT99']))
            ->assertOk()
            ->assertSeeText(__('site.card_verify.verify_another'));
    }

    public function test_borrower_verify_stays_in_account_shell(): void
    {
        $this->withoutMiddleware(EnsureBorrowerPin::class);

        $user = User::factory()->create(['role' => 'customer']);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-CV-003',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Shell',
            'last_name' => 'User',
            'phone' => '255712340004',
            'member_no' => 'KPF-TZ-SH01',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addMonths(11),
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.verify'))
            ->assertOk()
            ->assertSeeText(__('site.card_verify.heading'))
            ->assertSee(route('site.borrower.verify.lookup'), false);

        $target = User::factory()->create();
        Customer::create([
            'user_id' => $target->id,
            'customer_number' => 'CU-CV-004',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Target',
            'last_name' => 'Member',
            'phone' => '255712340005',
            'member_no' => 'KPF-TZ-TG22',
            'membership_issued_at' => now()->subMonth(),
            'membership_expires_at' => now()->addMonths(11),
        ]);

        $this->actingAs($user)
            ->post(route('site.borrower.verify.lookup'), [
                'type' => 'member',
                'number' => 'TG22',
            ])
            ->assertRedirect(route('site.borrower.verify.member', ['memberNo' => 'KPF-TZ-TG22']));
    }

    public function test_verify_form_includes_qr_scan_control(): void
    {
        $this->get(route('site.card.verify'))
            ->assertOk()
            ->assertSee(__('site.card_verify.scan_aria'), false)
            ->assertSee('cardVerifyForm', false)
            ->assertSee('x-ref="suffix"', false);
    }

    public function test_parse_scan_payload_reads_member_and_partner_qr_urls(): void
    {
        $service = app(CardVerificationService::class);

        $this->assertSame(
            ['type' => 'member', 'number' => 'X72A'],
            $service->parseScanPayload('https://kopafasta.test/v/KPF-TZ-X72A')
        );
        $this->assertSame(
            ['type' => 'member', 'number' => 'X72A'],
            $service->parseScanPayload('https://kopafasta.test/v/KPFTZX72A')
        );
        $this->assertSame(
            ['type' => 'supplier', 'number' => 'Z9Y8'],
            $service->parseScanPayload('https://kopafasta.test/v/p/PT-SP-TZ-Z9Y8')
        );
        $this->assertSame(
            ['type' => 'member', 'number' => 'AB12'],
            $service->parseScanPayload('KPF-TZ-AB12')
        );
    }
}
