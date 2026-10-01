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
}
