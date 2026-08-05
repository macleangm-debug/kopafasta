<?php

namespace Tests\Unit;

use App\Services\CustomerPaymentService;
use App\Services\PayInService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayInDescriptionTest extends TestCase
{
    #[Test]
    public function it_builds_human_readable_descriptions_without_underscores(): void
    {
        $service = app(CustomerPaymentService::class);

        $this->assertSame(
            'Membership Fee PAY-ABC123',
            $service->payInDescription('registration_fee', 'PAY-ABC123')
        );
        $this->assertSame(
            'Application Fee PAY-APP1',
            $service->payInDescription('application_fee', 'PAY-APP1')
        );
        $this->assertSame(
            'Post-approval Fee PAY-PA1',
            $service->payInDescription('post_approval_fee', 'PAY-PA1')
        );
        $this->assertSame(
            'Loan Repayment PAY-REP1',
            $service->payInDescription('loan_repayment', 'PAY-REP1')
        );
    }

    #[Test]
    public function it_sanitizes_payin_descriptions_for_all_fee_keys(): void
    {
        $payIn = app(PayInService::class);

        foreach ([
            'registration_fee PAY-1',
            'application_fee PAY-2',
            'post_approval_fee PAY-3',
            'valuation_fee PAY-4',
            'asset_reservation_fee PAY-5',
            'loan_repayment PAY-6',
        ] as $raw) {
            $clean = $payIn->sanitizeDescription($raw);
            $this->assertNotNull($clean);
            $this->assertStringNotContainsString('_', $clean);
        }

        $this->assertSame(
            'Membership Fee PAY-ABC123',
            $payIn->sanitizeDescription('Membership Fee PAY-ABC123')
        );
    }
}
