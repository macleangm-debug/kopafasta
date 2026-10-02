<?php

namespace Tests\Feature;

use App\Models\PinRecoveryAnswer;
use App\Models\User;
use App\Services\ConsoleSecondFactorService;
use App\Services\PinRecoveryChallengeService;
use App\Services\WebTwoFactorAuthService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffSecurityQuestionsAuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BranchSeeder::class);
        $this->seed(DepartmentSeeder::class);
        config([
            'auth_portal.require_2fa_staff' => true,
            'auth_portal.require_2fa_admin' => true,
            'auth_portal.staff_allow_authenticator' => true,
            'auth_portal.staff_allow_security_questions' => true,
            'auth_portal.privileged_require_authenticator' => true,
        ]);
    }

    public function test_staff_login_offers_method_choice_then_security_questions_to_workspace(): void
    {
        $staff = User::factory()->create([
            'role' => 'collector',
            'roles' => ['collector'],
            'email' => 'collector.kba@example.com',
            'password' => 'StaffPass99',
            'is_active' => true,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->post(route('staff.login'), [
            'login' => 'collector.kba@example.com',
            'password' => 'StaffPass99',
        ])->assertRedirect(route('auth.secure.choose', ['context' => 'staff']));

        $this->get(route('auth.secure.choose', ['context' => 'staff']))
            ->assertOk()
            ->assertSee('Security questions', false)
            ->assertSee('Authenticator app', false)
            ->assertSee('Set up your security', false);

        $keys = ['mother_first_name', 'birth_village', 'primary_school'];
        $this->post(route('auth.secure.questions.setup.store'), [
            'context' => 'staff',
            'question_keys' => $keys,
            'answer_values' => ['Alice', 'Dodoma', 'Uhuru'],
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertTrue(app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($staff->fresh()));
        $this->assertAuthenticated('admin');

        Auth::guard('admin')->logout();
        $this->flushSession();

        // Next login challenges one security question.
        $this->post(route('staff.login'), [
            'login' => 'collector.kba@example.com',
            'password' => 'StaffPass99',
        ])->assertRedirect(route('auth.secure.questions.challenge', ['context' => 'staff']));

        $challenge = $this->get(route('auth.secure.questions.challenge', ['context' => 'staff']))
            ->assertOk()
            ->assertSee('Verify it’s you', false);

        $token = session('staff_kba_challenge_token');
        $this->assertNotEmpty($token);

        $payload = \Illuminate\Support\Facades\Cache::get(ConsoleSecondFactorService::STAFF_KBA_CACHE.$token);
        $key = (string) ($payload['question_key'] ?? '');
        $answers = [
            'mother_first_name' => 'Alice',
            'birth_village' => 'Dodoma',
            'primary_school' => 'Uhuru',
        ];

        // Wrong answer keeps the same question (token unchanged).
        $this->post(route('auth.secure.questions.challenge.verify'), [
            'context' => 'staff',
            'token' => $token,
            'answer' => 'wrong-answer',
        ])->assertSessionHasErrors('answer');
        $this->assertSame($token, session('staff_kba_challenge_token'));

        $this->post(route('auth.secure.questions.challenge.verify'), [
            'context' => 'staff',
            'token' => $token,
            'answer' => $answers[$key],
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticated('admin');
    }

    public function test_privileged_admin_cannot_use_security_questions_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'email' => 'admin.priv@example.com',
            'password' => 'AdminPass99',
            'is_active' => true,
        ]);

        $second = app(ConsoleSecondFactorService::class);
        $methods = $second->allowedMethodsFor($admin, 'admin');
        $this->assertSame([ConsoleSecondFactorService::METHOD_AUTHENTICATOR], $methods);

        app(PinRecoveryChallengeService::class)->enrollSelected($admin, [
            'mother_first_name', 'birth_village', 'primary_school',
        ], [
            'mother_first_name' => 'Alice',
            'birth_village' => 'Dodoma',
            'primary_school' => 'Uhuru',
        ]);

        // Even with KBA enrolled, authenticator-required role still must enroll authenticator.
        $this->assertTrue($second->mustEnroll($admin->fresh(), 'admin'));
    }

    public function test_questions_only_never_redirects_console_staff_to_totp(): void
    {
        config([
            'auth_portal.require_2fa_staff' => true,
            'auth_portal.require_2fa_admin' => true,
            'auth_portal.staff_allow_authenticator' => false,
            'auth_portal.staff_allow_security_questions' => true,
        ]);
        \App\Models\Setting::set('auth_portal.staff_allow_authenticator', false);
        \App\Models\Setting::set('auth_portal.staff_allow_security_questions', true);
        \App\Models\Setting::set('auth_portal.authenticator_required_roles', ['admin', 'super_admin']);

        // Agent has console_access — previously forced admin/TOTP context.
        $staff = User::factory()->create([
            'role' => 'agent',
            'roles' => ['agent'],
            'name' => 'Neema Support',
            'email' => 'neema.agent@example.com',
            'password' => 'StaffPass99',
            'is_active' => true,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $second = app(ConsoleSecondFactorService::class);
        $this->assertSame(
            [ConsoleSecondFactorService::METHOD_SECURITY_QUESTIONS],
            $second->allowedMethodsFor($staff, 'staff')
        );
        $this->assertStringContainsString(
            'secure/questions/setup',
            $second->setupRedirect($staff, 'staff')
        );
        $this->assertStringNotContainsString('two-factor/setup', $second->setupRedirect($staff, 'staff'));

        $this->post(route('staff.login'), [
            'login' => 'neema.agent@example.com',
            'password' => 'StaffPass99',
        ])->assertRedirect(route('auth.secure.questions.setup', ['context' => 'staff']));

        // Hitting TOTP setup while questions-only must bounce to questions.
        $this->get(route('auth.two-factor.setup', ['context' => 'staff']))
            ->assertRedirect(route('auth.secure.questions.setup', ['context' => 'staff']));
    }

    public function test_setup_link_greets_first_name_and_routes_questions_only(): void
    {
        config([
            'auth_portal.require_2fa_staff' => true,
            'auth_portal.require_2fa_admin' => false,
            'auth_portal.staff_allow_authenticator' => false,
            'auth_portal.staff_allow_security_questions' => true,
        ]);
        \App\Models\Setting::set('auth_portal.staff_allow_authenticator', false);
        \App\Models\Setting::set('auth_portal.staff_allow_security_questions', true);

        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'collector',
            'roles' => ['collector'],
            'name' => 'Asha Juma',
            'email' => null,
            'phone' => '255711000777',
            'password' => 'old-secret',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->from(route('admin.users.show', $staff))
            ->post(route('admin.users.password-setup-link', $staff));

        $response->assertRedirect(route('admin.users.show', $staff).'#password-access')
            ->assertSessionHas('password_setup_url');

        $url = (string) session('password_setup_url');
        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        Auth::guard('admin')->logout();
        $this->flushSession();

        $this->get(route('staff.password-setup', [
            'token' => $query['token'],
            'uid' => $staff->id,
            'email' => $query['email'],
        ]))->assertOk()
            ->assertSee('Hello, Asha', false)
            ->assertSee('Choose your password', false);

        $this->post(route('staff.password-setup.store'), [
            'token' => $query['token'],
            'uid' => $staff->id,
            'email' => $query['email'],
            'password' => 'SetupChosen99',
            'password_confirmation' => 'SetupChosen99',
        ])->assertRedirect(route('auth.secure.questions.setup', ['context' => 'staff']));
    }

    public function test_setup_link_continues_into_security_setup(): void
    {
        config([
            'auth_portal.require_2fa_staff' => true,
            'auth_portal.require_2fa_admin' => false,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'collector',
            'roles' => ['collector'],
            'email' => null,
            'phone' => '255711000555',
            'password' => 'old-secret',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->from(route('admin.users.show', $staff))
            ->post(route('admin.users.password-setup-link', $staff));

        $response->assertRedirect(route('admin.users.show', $staff).'#password-access')
            ->assertSessionHas('password_setup_url');

        $url = (string) session('password_setup_url');
        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
        Auth::guard('admin')->logout();

        $this->post(route('staff.password-setup.store'), [
            'token' => $query['token'],
            'uid' => $staff->id,
            'email' => $query['email'],
            'password' => 'SetupChosen99',
            'password_confirmation' => 'SetupChosen99',
        ])->assertRedirect(route('auth.secure.choose', ['context' => 'staff']));
    }

    public function test_admin_and_staff_login_reuse_console_auth_shell(): void
    {
        $this->get(route('staff.login'))
            ->assertOk()
            ->assertSee('Staff sign in', false);

        $staffHtml = $this->get(route('staff.login'))->getContent();
        $this->assertStringContainsString('Operate loans, partners, and recoveries', $staffHtml);

        $adminHtml = $this->get(route('admin.login'))->getContent();
        $this->assertStringContainsString('Operate loans, partners, and recoveries', $adminHtml);
        $this->assertStringContainsString('Welcome back', $adminHtml);
    }

    public function test_failed_answer_does_not_rotate_question_within_attempt(): void
    {
        $staff = User::factory()->create([
            'role' => 'collector',
            'roles' => ['collector'],
            'email' => 'rotate@example.com',
            'password' => 'StaffPass99',
            'is_active' => true,
        ]);

        app(PinRecoveryChallengeService::class)->enrollSelected($staff, [
            'mother_first_name', 'birth_village', 'primary_school',
        ], [
            'mother_first_name' => 'Alice',
            'birth_village' => 'Dodoma',
            'primary_school' => 'Uhuru',
        ]);

        $second = app(ConsoleSecondFactorService::class);
        $twoFactor = app(WebTwoFactorAuthService::class);

        $this->post(route('staff.login'), [
            'login' => 'rotate@example.com',
            'password' => 'StaffPass99',
        ]);

        $this->get(route('auth.secure.questions.challenge', ['context' => 'staff']))->assertOk();
        $token = session('staff_kba_challenge_token');
        $key1 = data_get(\Illuminate\Support\Facades\Cache::get(ConsoleSecondFactorService::STAFF_KBA_CACHE.$token), 'question_key');

        $this->post(route('auth.secure.questions.challenge.verify'), [
            'context' => 'staff',
            'token' => $token,
            'answer' => 'nope',
        ]);

        $this->get(route('auth.secure.questions.challenge', ['context' => 'staff']))->assertOk();
        $token2 = session('staff_kba_challenge_token');
        $key2 = data_get(\Illuminate\Support\Facades\Cache::get(ConsoleSecondFactorService::STAFF_KBA_CACHE.$token2), 'question_key');

        $this->assertSame($token, $token2);
        $this->assertSame($key1, $key2);
    }
}
