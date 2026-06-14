<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\CapitalPartnerAllocationService;
use Tests\TestCase;

class CapitalPartnerInterestSplitTest extends TestCase
{
    public function test_default_interest_split_is_sixty_forty(): void
    {
        Setting::set('finance.capital_partner_interest_share_percent', 60);

        $service = app(CapitalPartnerAllocationService::class);

        $this->assertEqualsWithDelta(60.0, $service->partnerInterestSharePercent(), 0.001);
        $this->assertEqualsWithDelta(40.0, $service->companyInterestSharePercent(), 0.001);
    }

    public function test_interest_components_split_using_configured_percent(): void
    {
        Setting::set('finance.capital_partner_interest_share_percent', 60);

        $interest = 1000.0;
        $partnerPct = app(CapitalPartnerAllocationService::class)->partnerInterestSharePercent();
        $partnerShare = round($interest * ($partnerPct / 100), 2);
        $companyShare = round($interest - $partnerShare, 2);

        $this->assertEqualsWithDelta(600.0, $partnerShare, 0.001);
        $this->assertEqualsWithDelta(400.0, $companyShare, 0.001);
        $this->assertEqualsWithDelta($interest, $partnerShare + $companyShare, 0.001);
    }
}
