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
use App\Services\BorrowerDashboardHeroService;
use App\Services\GuarantorInvitationService;
use App\Services\NotificationService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuarantorInviteUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $suffix, array $overrides = []): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        return Customer::create(array_merge([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-GUX-'.$suffix,
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Gux'.$suffix,
            'last_name'             => 'Member',
            'phone'                 => '25570088'.str_pad($suffix, 4, '0', STR_PAD_LEFT),
            'membership_status'     => 'active',
            'membership_issued_at'  => now(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }

    private function loanProduct(): LoanProduct
    {
        return LoanProduct::create([
            'code'              => 'IL-GUX',
            'name'              => 'Guarantor UX Product',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
    }

    public function test_public_guarantor_pages_use_premium_shell(): void
    {
        $borrower = $this->makeCustomer('01');
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-01',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
        ]);

        GuarantorInvitation::create([
            'customer_id'         => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id'     => $product->id,
            'type'                => 'external',
            'channel'             => 'sms',
            'token'               => 'gux-pending-token',
            'short_code'          => 'GUXPND',
            'contact'             => '+255700880002',
            'invitee_name'        => 'Invitee',
            'status'              => 'pending',
            'expires_at'          => now()->addDays(7),
        ]);

        $this->get(route('site.guarantor.show', 'gux-pending-token'))
            ->assertOk()
            ->assertSee('premium-gradient', false)
            ->assertSee(__('borrower.guarantor_invite.heading'), false)
            ->assertSee(__('borrower.guarantor_invite.accept'), false);

        GuarantorInvitation::create([
            'customer_id'         => $borrower->id,
            'loan_application_id' => $application->id,
            'loan_product_id'     => $product->id,
            'type'                => 'external',
            'channel'             => 'sms',
            'token'               => 'gux-declined-token',
            'short_code'          => 'GUXDCL',
            'contact'             => '+255700880003',
            'status'              => 'rejected',
            'expires_at'          => now()->addDays(7),
        ]);

        $this->get(route('site.guarantor.declined', 'gux-declined-token'))
            ->assertOk()
            ->assertSee('premium-gradient', false)
            ->assertSee(__('borrower.guarantor_invite.declined_cta_member'), false)
            ->assertSee(__('borrower.guarantor_invite.member_benefit'), false)
            ->assertSee(route('site.register.borrower'), false);
    }

    public function test_guarantor_notifications_include_action_paths(): void
    {
        $borrower = $this->makeCustomer('10');
        $member = $this->makeCustomer('11', [
            'first_name' => 'Member',
            'last_name'  => 'Guarantor',
        ]);
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-10',
            'requested_amount'        => 400_000,
            'requested_tenure_months' => 6,
            'status'                  => 'submitted',
        ]);

        $guarantor = Guarantor::create([
            'first_name'   => $member->first_name,
            'last_name'    => $member->last_name,
            'phone'        => $member->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $application->id,
            'status'              => 'pending',
        ]);

        app(NotificationService::class)->notifyInApp(
            $member,
            'Request body',
            'guarantor',
            'guarantor_request',
            __('borrower.guarantor_invite.notify_request_title'),
            route('site.borrower.guarantor-requests.show', $link),
            __('borrower.guarantor_notifications.view_request'),
        );

        $log = NotificationLog::query()
            ->where('customer_id', $member->id)
            ->where('template', 'guarantor_request')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringStartsWith('/borrower/guarantor-requests/', (string) $log->recipient);

        app(GuarantorInvitationService::class)->reject($link);

        $declined = NotificationLog::query()
            ->where('customer_id', $borrower->id)
            ->where('template', 'guarantor_declined')
            ->first();

        $this->assertNotNull($declined);
        $this->assertStringStartsWith('/borrower/applications/', (string) $declined->recipient);
    }

    public function test_dashboard_hero_surfaces_pending_guarantor_request(): void
    {
        $borrower = $this->makeCustomer('20', [
            'first_name' => 'Alice',
            'last_name'  => 'Borrower',
        ]);
        $guarantorCustomer = $this->makeCustomer('21');
        $product = $this->loanProduct();

        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-20',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'submitted',
        ]);

        $guarantor = Guarantor::create([
            'first_name'   => $guarantorCustomer->first_name,
            'last_name'    => $guarantorCustomer->last_name,
            'phone'        => $guarantorCustomer->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $application->id,
            'status'              => 'pending',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $guarantorCustomer->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'gux-hero-token',
            'short_code'              => 'GUXHER',
            'contact'                 => $guarantorCustomer->phone,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        $hero = app(BorrowerDashboardHeroService::class)->forCustomer($guarantorCustomer);

        $this->assertSame('guarantor_request', $hero['variant']);
        $this->assertSame(
            route('site.borrower.guarantor-requests.show', $link),
            $hero['cta_url']
        );
        $this->assertStringContainsString('Alice', (string) $hero['subtitle']);
    }

    public function test_wizard_external_guarantor_invite_succeeds_without_application(): void
    {
        $borrower = $this->makeCustomer('30');
        $product = $this->loanProduct();

        $response = $this->actingAs($borrower->user)
            ->postJson(route('site.borrower.apply.guarantor-invite'), [
                'loan_product_id'       => $product->id,
                'external_first_name'   => 'Jane',
                'external_last_name'    => 'Guarantor',
                'external_phone'        => '0712345699',
                'external_relationship' => 'friend',
                'external_region'       => 'Dar es Salaam',
                'external_district'     => 'Kinondoni',
                'external_channel'      => 'sms',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['share' => ['invitation_url', 'invitation_id', 'short_url']]);

        $this->assertDatabaseHas('guarantor_invitations', [
            'customer_id'         => $borrower->id,
            'loan_application_id' => null,
            'type'                => 'external',
            'status'              => 'pending',
            'invitee_name'        => 'Jane Guarantor',
        ]);

        $sent = NotificationLog::query()
            ->where('customer_id', $borrower->id)
            ->where('template', 'guarantor_sent')
            ->first();

        $this->assertNotNull($sent);
        $this->assertStringContainsString('Jane Guarantor', (string) $sent->message);
        $this->assertStringStartsWith('/borrower/loans', (string) $sent->recipient);
    }

    public function test_notification_preview_exposes_accept_and_decline_for_guarantor_request(): void
    {
        $borrower = $this->makeCustomer('40', [
            'first_name' => 'Borrow',
            'last_name'  => 'Four',
        ]);
        $member = $this->makeCustomer('41', [
            'first_name' => 'Guard',
            'last_name'  => 'Four',
        ]);
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-40',
            'requested_amount'        => 350_000,
            'requested_tenure_months' => 6,
            'status'                  => 'submitted',
        ]);

        $guarantor = Guarantor::create([
            'first_name'   => $member->first_name,
            'last_name'    => $member->last_name,
            'phone'        => $member->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $application->id,
            'status'              => 'pending',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $member->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'gux-preview-token',
            'short_code'              => 'GUXPRV',
            'contact'                 => $member->phone,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        app(NotificationService::class)->notifyInApp(
            $member,
            __('borrower.guarantor_invite.guarantor_received', [
                'borrower'  => 'Borrow Four',
                'reference' => 'APP-GUX-40',
            ]),
            'guarantor',
            'guarantor_request',
            __('borrower.guarantor_invite.notify_request_title'),
            route('site.borrower.guarantor-requests.show', $link),
            __('borrower.guarantor_notifications.view_request'),
            [
                'title_key' => 'borrower.guarantor_invite.notify_request_title',
                'body_key'  => 'borrower.guarantor_invite.guarantor_received',
                'params'    => [
                    'borrower'  => 'Borrow Four',
                    'reference' => 'APP-GUX-40',
                ],
                'customer_guarantor_id' => $link->id,
            ],
        );

        $preview = $this->actingAs($member->user)
            ->getJson(route('site.borrower.notifications.preview'))
            ->assertOk()
            ->json('items.0');

        $this->assertSame('guarantor_request', $preview['template']);
        $this->assertSame(route('site.borrower.guarantor-requests.show', $link), $preview['accept_url']);
        $this->assertSame(route('site.borrower.guarantor-requests.respond', $link), $preview['decline_url']);
        $this->assertSame(__('borrower.guarantor_notifications.accept_cta'), $preview['action_label']);
        $this->assertSame(__('borrower.guarantor_notifications.decline_cta'), $preview['decline_label']);

        $this->actingAs($member->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_notifications.accept_cta'), false)
            ->assertSee(__('borrower.guarantor_notifications.decline_cta'), false)
            ->assertSee('Borrow Four', false);
    }

    public function test_approved_guarantee_tracks_on_guarantor_tab_until_disbursed(): void
    {
        $borrower = $this->makeCustomer('50', [
            'first_name' => 'Borrow',
            'last_name'  => 'Five',
        ]);
        $member = $this->makeCustomer('51');
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-50',
            'requested_amount'        => 450_000,
            'requested_tenure_months' => 9,
            'status'                  => 'submitted',
        ]);

        $guarantor = Guarantor::create([
            'first_name'   => $member->first_name,
            'last_name'    => $member->last_name,
            'phone'        => $member->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $application->id,
            'status'              => 'pending',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $member->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'gux-approved-token',
            'short_code'              => 'GUXAPR',
            'contact'                 => $member->phone,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        app(GuarantorInvitationService::class)->approve($link);

        // Accepted guarantees stay on Guarantor requests until the loan itself is approved.
        $this->actingAs($member->user)
            ->get(route('site.borrower.loans', ['tab' => 'guarantor']))
            ->assertOk()
            ->assertSee('APP-GUX-50', false)
            ->assertSee('Borrow Five', false)
            ->assertSee(__('borrower.guaranteed.waiting_on_your_profile'), false)
            ->assertSee(__('borrower.guaranteed.view_details'), false);

        $this->actingAs($member->user)
            ->get(route('site.borrower.loans', ['tab' => 'guaranteed']))
            ->assertOk()
            ->assertDontSee('APP-GUX-50', false);

        $application->update([
            'status'        => 'approved',
            'current_stage' => 'approval',
        ]);

        $this->actingAs($member->user)
            ->get(route('site.borrower.loans', ['tab' => 'guarantor']))
            ->assertOk()
            ->assertDontSee('APP-GUX-50', false);

        $this->actingAs($member->user)
            ->get(route('site.borrower.loans', ['tab' => 'guaranteed']))
            ->assertOk()
            ->assertSee('APP-GUX-50', false)
            ->assertSee('Borrow Five', false)
            ->assertSee(__('borrower.guaranteed.view_details'), false);
    }

    public function test_member_can_accept_guarantee_without_signature(): void
    {
        $borrower = $this->makeCustomer('70', [
            'first_name' => 'Borrow',
            'last_name'  => 'Seven',
        ]);
        $member = $this->makeCustomer('71');
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-70',
            'requested_amount'        => 480_000,
            'requested_tenure_months' => 8,
            'status'                  => 'awaiting_guarantor',
            'current_stage'           => 'awaiting_guarantor',
        ]);

        $guarantor = Guarantor::create([
            'first_name'   => $member->first_name,
            'last_name'    => $member->last_name,
            'phone'        => $member->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $application->id,
            'status'              => 'pending',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $member->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'gux-accept-token',
            'short_code'              => 'GUXACC',
            'contact'                 => $member->phone,
            'status'                  => 'pending',
            'expires_at'              => now()->addDays(7),
        ]);

        $this->actingAs($member->user)
            ->post(route('site.borrower.guarantor-requests.respond', $link), [
                'action' => 'approve',
            ])
            ->assertRedirect(route('site.borrower.profile'));

        $this->assertSame('approved', $link->fresh()->status);
        $this->assertSame('awaiting_guarantor', $application->fresh()->status);
        $this->assertDatabaseHas('customer_guarantors', [
            'id'     => $link->id,
            'status' => 'approved',
        ]);
    }

    public function test_guarantor_receives_in_app_arrears_notification(): void
    {
        $borrower = $this->makeCustomer('60', [
            'first_name' => 'Borrow',
            'last_name'  => 'Six',
        ]);
        $member = $this->makeCustomer('61');
        $product = $this->loanProduct();
        $application = LoanApplication::create([
            'customer_id'             => $borrower->id,
            'loan_product_id'         => $product->id,
            'application_number'      => 'APP-GUX-60',
            'requested_amount'        => 500_000,
            'requested_tenure_months' => 12,
            'status'                  => 'disbursed',
        ]);

        $guarantor = Guarantor::create([
            'first_name'   => $member->first_name,
            'last_name'    => $member->last_name,
            'phone'        => $member->phone,
            'relationship' => 'member',
        ]);

        $link = CustomerGuarantor::create([
            'customer_id'         => $borrower->id,
            'guarantor_id'        => $guarantor->id,
            'loan_application_id' => $application->id,
            'status'              => 'approved',
        ]);

        GuarantorInvitation::create([
            'customer_id'             => $borrower->id,
            'loan_application_id'     => $application->id,
            'loan_product_id'         => $product->id,
            'customer_guarantor_id'   => $link->id,
            'guarantor_customer_id'   => $member->id,
            'type'                    => 'internal',
            'channel'                 => 'in_app',
            'token'                   => 'gux-arrears-token',
            'short_code'              => 'GUXARR',
            'contact'                 => $member->phone,
            'status'                  => 'accepted',
            'expires_at'              => now()->addDays(7),
        ]);

        $loan = \App\Models\Loan::create([
            'customer_id'          => $borrower->id,
            'loan_application_id'  => $application->id,
            'loan_product_id'      => $product->id,
            'loan_number'          => 'LN-GUX-60',
            'principal_amount'     => 500_000,
            'approved_amount'      => 500_000,
            'outstanding_balance'  => 420_000,
            'interest_rate'        => 0.15,
            'tenure_months'        => 12,
            'status'               => 'active',
            'disbursement_date'    => now()->subMonths(2),
        ]);

        app(\App\Services\GuarantorNotificationService::class)->notifyLoanArrears($loan);

        $log = NotificationLog::query()
            ->where('customer_id', $member->id)
            ->where('template', 'guarantor_loan_arrears')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('LN-GUX-60', (string) $log->message);

        $preview = $this->actingAs($member->user)
            ->getJson(route('site.borrower.notifications.preview'))
            ->assertOk()
            ->json();

        $templates = collect($preview['items'] ?? [])->pluck('template')->all();
        $this->assertContains('guarantor_loan_arrears', $templates);
    }

    public function test_validating_member_guarantor_sends_in_app_accept_decline_request(): void
    {
        $borrower = $this->makeCustomer('80', [
            'first_name' => 'Borrow',
            'last_name'  => 'Eight',
        ]);
        $member = $this->makeCustomer('81', [
            'first_name' => 'Upendo',
            'last_name'  => 'Ketto',
            'member_no'  => 'KPF-TZ-000081',
            'phone'      => '255700880081',
        ]);
        $product = $this->loanProduct();

        $response = $this->actingAs($borrower->user)
            ->postJson(route('site.borrower.apply.guarantor-lookup'), [
                'membership_no'   => '000081',
                'phone'           => '0700880081',
                'loan_product_id' => $product->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('invite.notified', true);

        $invitationId = (int) $response->json('invite.invitation_id');
        $linkId = (int) $response->json('invite.customer_guarantor_id');
        $this->assertGreaterThan(0, $invitationId);
        $this->assertGreaterThan(0, $linkId);

        $this->assertDatabaseHas('guarantor_invitations', [
            'id'                    => $invitationId,
            'customer_id'           => $borrower->id,
            'guarantor_customer_id' => $member->id,
            'type'                  => 'internal',
            'status'                => 'pending',
            'loan_application_id'   => null,
        ]);

        $log = NotificationLog::query()
            ->where('customer_id', $member->id)
            ->where('template', 'guarantor_request')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('/borrower/guarantor-requests/'.$linkId, (string) $log->recipient);

        $this->actingAs($member->user)
            ->get(route('site.borrower.dashboard'))
            ->assertOk()
            ->assertSee(__('borrower.guarantor_notifications.accept_cta'), false)
            ->assertSee(__('borrower.guarantor_notifications.decline_cta'), false)
            ->assertSee('Borrow Eight', false);
    }
}
