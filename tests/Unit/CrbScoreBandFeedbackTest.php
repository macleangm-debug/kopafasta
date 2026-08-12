<?php

namespace Tests\Unit;

use App\Services\CrbCreditCheckService;
use Tests\TestCase;

class CrbScoreBandFeedbackTest extends TestCase
{
    public function test_score_602_is_moderate_refer(): void
    {
        $band = app(CrbCreditCheckService::class)->scoreBandFeedback(602);

        $this->assertSame('refer', $band['recommendation']);
        $this->assertSame('moderate', $band['band']);
        $this->assertStringContainsString('500–649', $band['detail']);
    }

    public function test_score_650_is_strong_approve(): void
    {
        $band = app(CrbCreditCheckService::class)->scoreBandFeedback(650);

        $this->assertSame('approve', $band['recommendation']);
        $this->assertSame('strong', $band['band']);
    }

    public function test_score_499_is_weak_reject(): void
    {
        $band = app(CrbCreditCheckService::class)->scoreBandFeedback(499);

        $this->assertSame('reject', $band['recommendation']);
        $this->assertSame('weak', $band['band']);
    }
}
