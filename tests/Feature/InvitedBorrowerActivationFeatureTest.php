<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Guarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\GuarantorInvitationService;
use App\Services\GuarantorOnboardingService;
use App\Services\InviteeProgressPresenter;
use App\Services\MembershipService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitedBorrowerActivationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_step_invitee_progress_includes_account_opened(): void
    {
        $steps = app(InviteeProgressPresenter::class)->steps(
            acceptedDone: true,
            accountDone: true,
            profileDone: false,
            readyDone: false,
            profilePercent: 20,
        );

        $this->assertCount(5, $steps);
        $this->assertSame(['invited', 'accepted', 'account', 'profile', 'ready'], array_column($steps, 'key'));
        $this->assertTrue($steps[2]['complete']);
        $this->assertTrue($steps[3]['current']);
        $this->assertSame(__('borrower.apply.guarantor_progress.account'), $steps[2]['label']);
    }

    public function test_linked_external_guarantor_sees_request_in_mikopo_and_gets_request_notification(): void
    {
        $this->assertFalse(MembershipService::isRequiredForCountry('TZ'));

        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-ACT-B',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'phone' => '255711000201',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-BOR2',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-ACT',
            'name' => 'Act Product',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-ACT-01',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ]);

        $person = Guarantor::create([
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '+255700000012',
            'relationship' => 'friend',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $person->id,
            'loan_application_id' => $application->id,
            'status' => 'pending',
        ]);
        $invite = GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'act-invite-token',
            'short_code' => 'ACT001',
            'contact' => '+255700000012',
            'invitee_name' => 'Vase Vase',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $guarantorUser = User::factory()->create([
            'role' => 'borrower',
            'phone' => '+255700000012',
            'is_active' => true,
            'preferences' => ['account_welcome_completed_at' => now()->toIso8601String()],
        ]);
        app(PinService::class)->setPin($guarantorUser, '1234');
        app(PinRecoveryChallengeService::class)->enroll($guarantorUser, [
            'mother_first_name' => 'Amina',
            'birth_village' => 'Moshi',
            'primary_school' => 'Uhuru',
        ]);
        $guarantor = Customer::create([
            'user_id' => $guarantorUser->id,
            'customer_number' => 'CU-ACT-G',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Vase',
            'last_name' => 'Vase',
            'phone' => '255700000012',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-VAS2',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
            'onboarded_at' => now(),
        ]);

        app(GuarantorOnboardingService::class)->linkInvitee($invite, $guarantor, fromTrustedSession: true);

        $this->assertSame((int) $guarantor->id, (int) $invite->fresh()->guarantor_customer_id);
        $this->assertTrue(
            NotificationLog::query()
                ->where('customer_id', $guarantor->id)
                ->where('template', 'guarantor_request')
                ->exists()
        );

        $status = app(GuarantorInvitationService::class)->borrowerInvitationStatus($invite->fresh());
        $this->assertCount(5, $status['steps']);
        $this->assertTrue(collect($status['steps'])->firstWhere('key', 'account')['complete']);

        $this->actingAs($guarantorUser)
            ->withSession(['account_welcome_done' => true])
            ->get(route('site.borrower.loans', ['tab' => 'guarantor']))
            ->assertOk()
            ->assertSee(__('borrower.loans_page.summary_guarantor'), false)
            ->assertDontSee('portalMode="guarantor"', false);

        $html = $this->actingAs($guarantorUser)
            ->withSession(['account_welcome_done' => true])
            ->get(route('site.borrower.loans', ['tab' => 'guarantor']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/>\s*1\s*</', $html);
        $this->assertStringContainsString(__('borrower.nav.dashboard'), $html);
        $this->assertStringContainsString(__('borrower.nav.loans'), $html);
    }

    public function test_external_public_accept_skips_second_accept_and_hides_decision(): void
    {
        $borrowerUser = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($borrowerUser, '1234');
        $borrower = Customer::create([
            'user_id' => $borrowerUser->id,
            'customer_number' => 'CU-ACT-B2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'David',
            'last_name' => 'Maclean',
            'phone' => '255711000202',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-BOR3',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-ACT2',
            'name' => 'Act Product 2',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
            'requires_guarantor' => true,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-ACT-02',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'awaiting_guarantor',
            'current_stage' => 'awaiting_guarantor',
        ]);

        $person = Guarantor::create([
            'first_name' => 'Vase',
            'last_name' => 'Two',
            'phone' => '+255700000022',
            'relationship' => 'friend',
        ]);
        $link = CustomerGuarantor::create([
            'customer_id' => $borrower->id,
            'guarantor_id' => $person->id,
            'loan_application_id' => $application->id,
            'status' => 'pending',
        ]);
        $invite = GuarantorInvitation::create([
            'customer_id' => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $product->id,
            'customer_guarantor_id' => $link->id,
            'type' => 'external',
            'channel' => 'whatsapp',
            'token' => 'act-invite-token-2',
            'short_code' => 'ACT002',
            'contact' => '+255700000022',
            'invitee_name' => 'Vase Two',
            'status' => 'accepted',
            'responded_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $guarantorUser = User::factory()->create([
            'role' => 'borrower',
            'phone' => '+255700000022',
            'is_active' => true,
            'preferences' => ['account_welcome_completed_at' => now()->toIso8601String()],
        ]);
        app(PinService::class)->setPin($guarantorUser, '1234');
        $guarantor = Customer::create([
            'user_id' => $guarantorUser->id,
            'customer_number' => 'CU-ACT-G2',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Vase',
            'last_name' => 'Two',
            'phone' => '255700000022',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-VAS3',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
            'onboarded_at' => now(),
        ]);
        $invite->update(['guarantor_customer_id' => $guarantor->id]);

        $html = $this->actingAs($guarantorUser)
            ->withSession(['account_welcome_done' => true])
            ->get(route('site.borrower.guarantor-requests.show', $link))
            ->assertOk()
            ->assertSee(__('borrower.guarantor.request_overview'), false)
            ->assertDontSee(__('borrower.guarantor.your_decision'), false)
            ->assertDontSee(__('borrower.guarantor.accept_request_cta'), false)
            ->assertDontSee('guarantor-invite-popup-title', false)
            ->getContent();

        $this->assertStringContainsString('max-w-7xl', $html);
        $this->assertStringContainsString(__('borrower.loan_profile.complete_profile'), $html);
        $this->assertSame('approved', $link->fresh()->status);

        $status = app(GuarantorInvitationService::class)->borrowerInvitationStatus($invite->fresh());
        $this->assertSame('pending_profile', $status['code']);
        $this->assertContains($status['label'], [
            __('borrower.apply.guarantor_status.account_opened'),
            __('borrower.apply.guarantor_status.profile_in_progress'),
        ]);
        $this->assertNotSame(__('borrower.apply.guarantor_status.invitation_sent'), $status['label']);
    }

    public function test_dashboard_does_not_auto_open_guarantor_request_modal(): void
    {
        $user = User::factory()->create([
            'role' => 'borrower',
            'preferences' => ['account_welcome_completed_at' => now()->toIso8601String()],
        ]);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-ACT-B3',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'No',
            'last_name' => 'Modal',
            'phone' => '255711000203',
            'country_code' => 'TZ',
            'member_no' => 'KPF-TZ-NOM',
            'membership_status' => 'identity',
            'membership_issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['account_welcome_done' => true])
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertDontSee('guarantor-invite-popup-title', false)
            ->assertDontSee('x-site.guarantor-request-popup', false);
    }

    public function test_guarantor_and_group_request_notification_ctas_are_actionable(): void
    {
        $this->assertSame('Angalia ombi la udhamini', __('borrower.guarantor_notifications.view_request', [], 'sw'));
        $this->assertSame('View guarantor request', __('borrower.guarantor_notifications.view_request', [], 'en'));
        $this->assertSame('Angalia ombi la kikundi', __('borrower.apply.group.notify_request_cta', [], 'sw'));
        $this->assertSame('View group request', __('borrower.apply.group.notify_request_cta', [], 'en'));

        $listUrl = route('site.borrower.loans', ['tab' => 'guarantor']);
        $this->assertStringContainsString('tab=guarantor', $listUrl);
    }

    public function test_application_view_guarantor_actions_follow_progress(): void
    {
        $progress = file_get_contents(resource_path('views/site/borrower/loan-profile/_guarantor_progress.blade.php'));
        $progressPos = strpos($progress, 'x-site.invitee-progress');
        $whatsappPos = strpos($progress, 'guarantor_nudge_whatsapp');
        $this->assertNotFalse($progressPos);
        $this->assertNotFalse($whatsappPos);
        $this->assertGreaterThan($progressPos, $whatsappPos);
    }
}
