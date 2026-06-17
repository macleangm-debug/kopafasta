<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Services\AssetReservationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDepositPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_payment_marks_reservation_paid(): void
    {
        $asset = MarketplaceAsset::create([
            'slug'                => 'test-motorbike-001',
            'title'               => 'Test Motorbike',
            'category'            => 'motorbike',
            'supplier_name'       => 'Test Supplier',
            'weekly_installment'  => 50_000,
            'max_tenure_months'   => 12,
            'asset_value'         => 2_000_000,
            'supplier_deposit'    => 400_000,
            'customer_deposit'    => 500_000,
            'availability_status' => 'available',
            'is_active'           => true,
        ]);

        $customer = Customer::create([
            'customer_number' => 'CU-DEP-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Deposit',
            'last_name'       => 'Tester',
            'phone'           => '255712345678',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $reservation = AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => 'reservation_fee_paid',
            'reservation_fee_amount' => 50_000,
            'reservation_fee_status' => 'paid',
            'deposit_amount'         => 500_000,
            'deposit_status'         => 'pending',
        ]);

        $paymentService = app(AssetReservationPaymentService::class);

        $payment = $paymentService->submit($customer, $reservation, AssetReservationPaymentService::STEP_DEPOSIT, [
            'payment_method' => 'mobile_money',
            'mobile_number'  => '255712345678',
            'reference'      => $paymentService->paymentReference($reservation, AssetReservationPaymentService::STEP_DEPOSIT),
        ]);

        $reservation->refresh();

        $this->assertTrue($payment->isVerified());
        $this->assertSame('paid', $reservation->deposit_status);
        $this->assertSame('deposit_paid', $reservation->status);
    }
}
