<?php

namespace Tests\Feature;

use App\Models\PinRecoveryAnswer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerActivationRecoveryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_swahili_supplier_activation_message_uses_partner_account_number(): void
    {
        $partner = $this->makeInvitedSupplier();
        app()->setLocale('sw');

        $message = app(PartnerActivationService::class)->shareMessage($partner);

        $this->assertStringContainsString('Namba yako ya akaunti ya mshirika: '.$partner->partner_number, $message);
        $this->assertStringNotContainsString('Msimbo wa mshirika:', $message);
        $this->assertStringContainsString('Asante kwa kujisajili kama Muuzaji wa Mali', $message);
        $this->assertStringContainsString('Mkopo wa Mali', $message);

        app()->setLocale('en');
        $english = app(PartnerActivationService::class)->shareMessage($partner);
        $this->assertStringContainsString('Partner code: '.$partner->partner_number, $english);
        $this->assertStringContainsString('Asset Supplier', $english);
    }

    public function test_invited_partner_show_keeps_pin_activation_in_one_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->makeInvitedSupplier();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Awaiting activation', false)
            ->assertSee(__('admin.partners.copy_link'), false)
            ->assertSee(__('admin.partners.copy_message'), false)
            ->assertSee('copiedLink', false)
            ->assertSee('copiedMessage', false)
            ->assertSee(__('admin.partners.copied'), false)
            ->assertSee(__('admin.partners.or_activate_here'), false)
            ->assertSee('Activate &amp; set PIN', false)
            ->assertSee('data-loading-label="'.__('admin.partners.activating').'"', false)
            ->assertSee(__('admin.partners.resend_activation'), false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'name="pin"'));
    }

    public function test_partner_pin_setup_then_two_security_questions_are_hashed(): void
    {
        [$user, $partner] = $this->makeActivatedPartner(withPin: false);

        $this->actingAs($user)
            ->post(route('site.partner.setup-pin.post'), [
                'pin' => '2468',
                'pin_confirmation' => '2468',
            ])
            ->assertRedirect(route('site.partner.setup-recovery'));

        $this->actingAs($user)
            ->get(route('site.partner.setup-recovery'))
            ->assertOk()
            ->assertSee(__('site.auth.partner_recovery_setup_title'), false)
            ->assertSee(__('site.auth.pin_recovery.bank.primary_school'), false);

        $this->actingAs($user)
            ->post(route('site.partner.setup-recovery.post'), [
                'question_keys' => ['primary_school', 'mother_first_name'],
                'answers' => [
                    'primary_school' => 'Uhuru Primary',
                    'mother_first_name' => 'Asha',
                ],
            ])
            ->assertRedirect(route('site.partner.dashboard'));

        $user->refresh();
        $this->assertTrue(app(PinService::class)->hasPin($user));
        $this->assertTrue(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user));
        $this->assertDatabaseCount('pin_recovery_answers', 2);

        foreach (PinRecoveryAnswer::query()->where('user_id', $user->id)->get() as $row) {
            $this->assertStringStartsWith('$2y$', (string) $row->answer_hash);
            $this->assertNotSame('Uhuru Primary', $row->answer_hash);
            $this->assertNotSame('Asha', $row->answer_hash);
        }

        $this->actingAs($user)
            ->get(route('site.partner.settings'))
            ->assertOk()
            ->assertSee(__('site.auth.partner_recovery_ready'), false)
            ->assertDontSee('Uhuru Primary', false)
            ->assertDontSee('Asha', false);
    }

    public function test_existing_activated_partner_without_questions_is_not_locked_out(): void
    {
        [$user, $partner] = $this->makeActivatedPartner(withPin: true);

        $this->assertFalse(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user));

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('site.partner.settings'))
            ->assertOk()
            ->assertSee(__('site.auth.partner_recovery_setup_title'), false)
            ->assertSee(__('site.auth.partner_recovery_prompt'), false);

        $this->post(route('site.logout'));

        $this->from(route('site.partner.forgot-pin'))
            ->post(route('site.partner.forgot-pin.start'), [
                'partner_code' => $partner->partner_number,
                'phone' => $partner->phone,
            ])
            ->assertRedirect(route('site.partner.forgot-pin'))
            ->assertSessionHas('feedback.message', __('site.auth.pin_recovery.not_enrolled'));

        $this->actingAs($user)
            ->post(route('site.partner.account.recovery'), [
                'question_keys' => ['birth_village', 'childhood_friend'],
                'answers' => [
                    'birth_village' => 'Moshi',
                    'childhood_friend' => 'Juma',
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user->fresh()));
    }

    public function test_forgot_partner_pin_uses_account_number_phone_and_questions(): void
    {
        [$user, $partner] = $this->makeActivatedPartner(withPin: true);
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'primary_school' => 'Uhuru Primary',
            'mother_first_name' => 'Asha',
        ]);

        $this->get(route('site.login', ['portal' => 'partner']))
            ->assertOk()
            ->assertSee('/partner/forgot-pin', false);

        $this->from(route('site.partner.forgot-pin'))
            ->post(route('site.partner.forgot-pin.start'), [
                'partner_code' => $partner->partner_number,
                'phone' => $partner->phone,
            ])
            ->assertRedirect(route('site.partner.forgot-pin', ['step' => 2]));

        $token = session('partner_pin_recovery_token');
        $this->assertNotEmpty($token);
        $this->assertSame(2, (int) session('partner_pin_recovery_required'));

        $this->post(route('site.partner.forgot-pin.verify'), [
            'token' => $token,
            'answers' => [
                'primary_school' => 'Uhuru Primary',
                'mother_first_name' => 'Asha',
            ],
        ])->assertRedirect(route('site.partner.forgot-pin', ['step' => 3]));

        $this->assertTrue(session('partner_pin_recovery_answers_ok'));

        $this->post(route('site.partner.forgot-pin.reset'), [
            'token' => $token,
            'pin' => '9876',
            'pin_confirmation' => '9876',
        ])->assertRedirect(route('site.login', ['portal' => 'partner']));

        $user->refresh();
        $this->assertTrue(app(PinService::class)->verify('9876', $user->pin_hash));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'partner.pin.recovered',
        ]);
    }

    public function test_forgot_partner_pin_does_not_reveal_which_answer_failed(): void
    {
        [$user, $partner] = $this->makeActivatedPartner(withPin: true);
        app(PinRecoveryChallengeService::class)->enroll($user, [
            'primary_school' => 'Uhuru Primary',
            'mother_first_name' => 'Asha',
        ]);

        $this->post(route('site.partner.forgot-pin.start'), [
            'partner_code' => $partner->partner_number,
            'phone' => $partner->phone,
        ])->assertRedirect(route('site.partner.forgot-pin', ['step' => 2]));

        $token = session('partner_pin_recovery_token');

        $this->post(route('site.partner.forgot-pin.verify'), [
            'token' => $token,
            'answers' => [
                'primary_school' => 'Wrong School',
                'mother_first_name' => 'Asha',
            ],
        ])->assertRedirect(route('site.partner.forgot-pin', ['step' => 2]))
            ->assertSessionHas('feedback.message', __('site.auth.pin_recovery.mismatch', ['remaining' => 4]));

        $this->assertStringNotContainsString('primary_school', (string) session('feedback.message'));
        $this->assertStringNotContainsString('Uhuru', (string) session('feedback.message'));
    }

    private function makeInvitedSupplier(): Vendor
    {
        return Vendor::create([
            'name' => 'MacLeans Autotraders',
            'category' => 'supplier',
            'roles' => ['supplier'],
            'status' => 'inactive',
            'partner_number' => 'PT-SP-TZ-MHXL',
            'phone' => '255715000111',
            'email' => 'supplier-recovery@kopafasta.local',
            'supplier_type' => 'managed_loan',
        ]);
    }

    /** @return array{0: User, 1: Vendor} */
    private function makeActivatedPartner(bool $withPin): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'phone' => '255715000222',
            'is_active' => true,
            'preferences' => ['account_welcome_completed_at' => now()->toIso8601String()],
        ]);

        $partner = Vendor::create([
            'name' => 'Recovery Valuer',
            'category' => 'valuer',
            'roles' => ['valuer'],
            'status' => 'active',
            'partner_number' => 'PT-VL-TZ-RCV1',
            'phone' => '255715000222',
            'email' => 'recovery-valuer@kopafasta.local',
            'user_id' => $user->id,
            'activated_at' => now(),
        ]);

        if ($withPin) {
            app(PinService::class)->setPin($user, '1234');
        }

        return [$user->fresh(), $partner];
    }
}
