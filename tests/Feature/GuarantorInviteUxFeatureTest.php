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
            ->assertSee(__('borrower.guarantor_invite.declined_benefit_rewards'), false)
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
        $this->assertStringContainsString('sent successfully', (string) $sent->message);
        $this->assertStringStartsWith('/borrower/loans', (string) $sent->recipient);
    }
}
