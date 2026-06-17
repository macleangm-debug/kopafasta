<?php

namespace Tests\Feature;

use App\Models\PartnerSettlement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\PartnerSettlementService;
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
            'vendor_id'    => $vendor->id,
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

        $settlement = PartnerSettlement::query()->where('vendor_id', $vendor->id)->first();

        $this->assertNotNull($settlement);

        $service->markSettlementPaid($settlement, $admin, 'bank', 'TXN-123');

        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
    }
}
