<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PartnerPayment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateService;
use App\Services\AffiliateTermsService;
use App\Services\CustomerPaymentService;
use App\Services\PartnerPayoutRequestService;
use App\Services\PaymentGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateCommissionWalletPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_benefit_banner_requires_a_real_discount_not_attribution_alone(): void
    {
        $affiliate = $this->affiliate(['application_discount_percent' => 0]);
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id]);
        $this->borrowerPin($customer->user);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 10000,
            'currency' => 'TZS',
            'reference' => 'PAY-ZERO01',
            'provider_meta' => ['pricing' => ['gross' => 10000]],
        ]);

        $quote = app(PaymentGateService::class)->quote($customer, 10000, 'application_fee');
        $this->assertTrue($quote['has_affiliate']);
        $this->assertSame(0.0, (float) $quote['affiliate_discount']);
        $this->assertFalse($quote['affiliate_auto_applied']);

        $this->actingAs($customer->user)
            ->get(route('site.borrower.payments.show', $payment))
            ->assertOk()
            ->assertDontSee(__('site.affiliate_portal.benefit_applied'), false);
    }

    public function test_verified_plus_payment_posts_commission_once_on_the_ledger(): void
    {
        $affiliate = $this->affiliate();
        $customer = $this->customer([
            'affiliate_vendor_id' => $affiliate->id,
            'member_no' => 'KPF-TZ-WLN5',
        ]);
        $this->borrowerPin($customer->user);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 900,
            'currency' => 'TZS',
            'reference' => 'PAY-PLUS90',
            'provider_meta' => ['pricing' => ['gross' => 1000, 'net_payable' => 900]],
        ]);

        $verified = app(CustomerPaymentService::class)->verify($payment);
        $this->assertTrue($verified->isVerified());

        $wallet = PartnerPayment::query()
            ->where('partner_id', $affiliate->id)
            ->where('source_type', 'affiliate_commission')
            ->get();
        $this->assertCount(1, $wallet);
        $this->assertSame('PAY-PLUS90', $wallet->first()->reference);
        $this->assertSame(90, (int) $wallet->first()->amount);
        $this->assertSame('approved', $wallet->first()->status);

        app(AffiliateService::class)->accrueCommission(
            $customer->fresh(),
            1000,
            'kopafasta_plus',
            CustomerPayment::class,
            $payment->id,
        );
        $this->assertSame(1, PartnerPayment::query()->where('source_type', 'affiliate_commission')->count());

        $html = $this->actingAs($affiliate->user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.wallet'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.commission_transactions', [], 'en'), false)
            ->assertSee(__('site.affiliate_portal.tab_withdrawals', [], 'en'), false)
            ->assertSee('PAY-PLUS90', false)
            ->assertSee('KPF-TZ-WLN5', false)
            ->assertSee('Kopafasta Plus', false)
            ->assertSee(__('site.affiliate_portal.commission_status_complete', [], 'en'), false)
            ->assertSee(__('site.affiliate_portal.remaining_to_withdraw', ['amount' => format_money(49910)], 'en'), false)
            ->assertDontSee(__('site.affiliate_portal.commission_status_pending', [], 'en'), false)
            ->assertDontSee('INV-', false)
            ->getContent();

        $this->assertStringNotContainsString($customer->first_name, $html);
        $this->assertStringNotContainsString($customer->phone, $html);
    }

    public function test_complete_commission_increases_available_and_settlement_gets_a_payment_id(): void
    {
        Setting::set('affiliates.minimum_payout_amount', 50);
        $affiliate = $this->affiliate();
        $customer = $this->customer(['affiliate_vendor_id' => $affiliate->id, 'member_no' => 'KPF-TZ-AVL1']);
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'status' => 'awaiting_payment',
            'amount' => 900,
            'currency' => 'TZS',
            'reference' => 'PAY-AVL001',
            'provider_meta' => ['pricing' => ['gross' => 1000, 'net_payable' => 900]],
        ]);
        app(CustomerPaymentService::class)->verify($payment);

        $payouts = app(PartnerPayoutRequestService::class);
        $this->assertSame(90.0, $payouts->availableBalance($affiliate, 'affiliate_commission'));

        $line = \App\Models\VendorPayment::query()->where('reference', 'PAY-AVL001')->first();
        $this->assertNotNull($line);
        $this->assertSame('approved', $line->status);

        $affiliate->update(['metadata' => ['payout_account' => [
            'type' => 'mobile_money',
            'mobile_provider' => 'M-Pesa',
            'mobile_number' => '255700000001',
        ]]]);

        $this->actingAs($affiliate->user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.wallet'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.review_withdrawal', [], 'en'), false);

        $request = $payouts->request($affiliate->fresh(), 'affiliate_commission', 90);
        $this->assertSame('pending', $request->status);
        $this->assertStringStartsWith('WDR-', $request->requestNumber());
        $this->assertSame(0.0, $payouts->availableBalance($affiliate->fresh(), 'affiliate_commission'));

        try {
            $payouts->request($affiliate->fresh(), 'affiliate_commission', 90);
            $this->fail('A second pending withdrawal must be blocked');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('already', strtolower($e->getMessage()));
        }

        $paid = $payouts->markPaid($request, User::factory()->create(['role' => 'admin']));
        $this->assertSame('paid', $paid->status);
        $this->assertStringStartsWith('PAY-', $paid->payoutPaymentId());
        $this->assertNotSame($request->requestNumber(), $paid->payoutPaymentId());
        $this->assertSame(0.0, $payouts->availableBalance($affiliate->fresh(), 'affiliate_commission'));

        $this->actingAs($affiliate->user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.wallet'))
            ->assertOk()
            ->assertSee($paid->requestNumber(), false)
            ->assertSee($paid->payoutPaymentId(), false);
    }

    public function test_share_cards_stretch_together_on_desktop_only(): void
    {
        $share = file_get_contents(resource_path('views/site/affiliate/share.blade.php'));
        $this->assertStringContainsString('lg:items-stretch', $share);
        $this->assertStringContainsString('lg:h-full', $share);
        $this->assertStringNotContainsString('min-h-[28rem]', $share);
    }

    public function test_settings_do_not_define_a_one_time_benefit_cap(): void
    {
        $settings = app(\App\Services\AffiliateSettingsService::class)->forForm();

        $this->assertTrue($settings['applies_to']['application_fee']);
        $this->assertTrue($settings['applies_to']['kopafasta_plus']);
        $this->assertFalse($settings['applies_to']['registration_fee']);
        $this->assertSame(30, $settings['attribution']['window_days']);
        $this->assertSame('application_created', $settings['attribution']['lock_at']);
        $this->assertArrayNotHasKey('benefit_consumption', $settings);
        $this->assertSame('discounted_amount', $settings['commission_calculation_base']);
        $this->assertSame('percentage', $settings['commission_mode']);
    }

    private function affiliate(array $overrides = []): Vendor
    {
        $affiliate = Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-WL-'.random_int(100, 999),
            'name' => 'Wallet Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'WL'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
            'application_discount_percent' => 10,
            'metadata' => ['plus_discount_percent' => 10],
        ], $overrides));
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        return $affiliate->fresh();
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'C-WL'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Halima',
            'last_name' => 'Khamis',
            'phone' => '+255600'.random_int(100000, 999999),
            'country_code' => 'TZ',
        ], $overrides));
    }

    private function borrowerPin(User $user): void
    {
        app(\App\Services\PinService::class)->setPin($user, '1234');
        app(\App\Services\PinRecoveryChallengeService::class)->enroll($user, [
            'mother_first_name' => 'Amina',
            'birth_village' => 'Moshi',
            'primary_school' => 'Uhuru',
        ]);
    }
}
