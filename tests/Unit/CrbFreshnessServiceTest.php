<?php

namespace Tests\Unit;

use App\Models\CreditHistory;
use App\Services\CrbFreshnessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrbFreshnessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_report_within_window_is_not_expired(): void
    {
        $service = app(CrbFreshnessService::class);
        $history = new CreditHistory([
            'checked_at' => CarbonImmutable::now()->subDays(30),
        ]);

        $this->assertTrue($service->isFresh($history));
        $this->assertFalse($service->isExpired($history));
        $this->assertSame(90, $service->freshnessDays());
    }

    public function test_report_older_than_freshness_window_is_expired(): void
    {
        $service = app(CrbFreshnessService::class);
        $history = new CreditHistory([
            'checked_at' => CarbonImmutable::now()->subDays(91),
        ]);

        $this->assertFalse($service->isFresh($history));
        $this->assertTrue($service->isExpired($history));
    }

    public function test_missing_checked_at_is_not_fresh(): void
    {
        $service = app(CrbFreshnessService::class);

        $this->assertFalse($service->isFresh(null));
        $this->assertFalse($service->isFresh(new CreditHistory([])));
    }
}
