<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\PartnerSettlement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\PartnerSettlementService;
use Database\Seeders\DefaultChartOfAccountsSeeder;
use Database\Seeders\FinanceDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeVendor(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'vendor_number' => 'V-TEST'.random_int(100, 999),
            'name'          => 'Test Partner',
            'category'      => 'supplier',
            'status'        => 'active',
        ], $overrides));
    }

    public function test_weekly_queue_groups_approved_payments(): void
    {
        $vendor = $this->makeVendor();
        $admin = User::factory()->create(['role' => 'admin']);

        $service = app(PartnerSettlementService::class);

        $first = $service->accrue($vendor, 50_000, 'vendor_task', 1, 'Task fee');
        $second = $service->accrue($vendor, 25_000, 'affiliate_commission', 2, 'Affiliate commission');

        $service->approvePayment($first, $admin);
        $service->approvePayment($second, $admin);

        $created = $service->queueWeeklySettlements();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('partner_settlements', [
            'partner_id'   => $vendor->id,
            'total_amount' => 75_000,
            'status'       => 'pending',
        ]);

        $this->assertSame(2, VendorPayment::query()->whereNotNull('partner_settlement_id')->count());
    }

    public function test_mark_settlement_paid_updates_vendor_payments(): void
    {
        $vendor = $this->makeVendor();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(PartnerSettlementService::class);

        $payment = $service->accrue($vendor, 10_000, 'vendor_task', 5, 'Task payout');
        $service->approvePayment($payment, $admin);
        $service->queueWeeklySettlements();

        $settlement = PartnerSettlement::query()->where('partner_id', $vendor->id)->first();

        $this->assertNotNull($settlement);

        $service->markSettlementPaid($settlement, $admin, 'bank', 'TXN-123');

        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_mark_payment_paid_posts_cash_out_journal(): void
    {
        $this->seed(DefaultChartOfAccountsSeeder::class);
        $this->seed(FinanceDefaultsSeeder::class);

        $vendor = $this->makeVendor();
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(PartnerSettlementService::class);

        $payment = $service->accrue($vendor, 10_000, 'valuation_fee', 9, 'Asset valuation APP-TEST');
        $this->actingAs($admin, 'admin');
        $paid = $service->markPaymentPaid($payment, $admin, 'bank', 'TXN-PAY-1');

        $this->assertSame('paid', $paid->status);
        $this->assertSame('bank', $paid->channel);
        $this->assertSame('TXN-PAY-1', $paid->reference);

        $this->assertTrue(JournalEntry::query()
            ->where('source_type', VendorPayment::class)
            ->where('source_id', $payment->id)
            ->where('status', 'posted')
            ->exists());
    }

    public function test_admin_payout_details_page_opens(): void
    {
        $vendor = $this->makeVendor();
        $admin = User::factory()->create(['role' => 'admin']);
        $payment = app(PartnerSettlementService::class)->accrue($vendor, 350, 'insurance_premium', 3, 'Collateral insurance premium');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partner-payments.show', $payment))
            ->assertOk()
            ->assertSee('What this payout is for', false)
            ->assertSee('Collateral insurance premium', false)
            ->assertSee('Record payout', false);
    }
}
