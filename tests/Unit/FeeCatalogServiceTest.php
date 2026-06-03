<?php

namespace Tests\Unit;

use App\Models\ChargesFee;
use App\Services\FeeCatalogService;
use Tests\TestCase;

class FeeCatalogServiceTest extends TestCase
{
    public function test_maps_percentage_basis_to_percent_fee_type(): void
    {
        $fee = new ChargesFee([
            'code' => 'ORIG_FEE',
            'name' => 'Origination',
            'basis' => 'percentage',
            'amount' => 2,
            'charge_when' => 'post_approval',
        ]);

        $snapshot = app(FeeCatalogService::class)->snapshotForProduct($fee);

        $this->assertSame('percent', $snapshot['fee_type']);
        $this->assertEqualsWithDelta(2.0, $snapshot['amount'], 0.001);
    }
}
