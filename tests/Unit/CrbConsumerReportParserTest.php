<?php

namespace Tests\Unit;

use App\Services\Crb\CrbConsumerReportParser;
use Tests\TestCase;

class CrbConsumerReportParserTest extends TestCase
{
    public function test_parses_personal_and_credit_sections_from_dnb_sample(): void
    {
        $xmlPath = base_path('crb/Live Request Manual/D&B Live Integration -Response XML Tags.txt');
        $this->assertFileExists($xmlPath);

        $raw = file_get_contents($xmlPath);
        preg_match('/-- consumer hit response\s*(<DATAPACKET>.*?<\/DATAPACKET>)/s', $raw, $m);
        $this->assertNotEmpty($m[1] ?? null, 'Consumer hit sample XML missing');

        $parsed = app(CrbConsumerReportParser::class)->parse($m[1]);

        $this->assertSame('Single', $parsed['personal']['marital_status']);
        $this->assertSame('Male', $parsed['personal']['gender']);
        $this->assertNotEmpty($parsed['personal']['full_name']);
        $this->assertNotEmpty($parsed['personal']['address_history']);
        $this->assertNotEmpty($parsed['personal']['contact_history']);
        $this->assertGreaterThan(0, $parsed['credit']['existing_loans']);
        $this->assertGreaterThan(0, $parsed['credit']['outstanding_balance']);
        $this->assertNotEmpty($parsed['credit']['open_accounts']);
        $this->assertSame('AZANIA', $parsed['credit']['open_accounts'][0]['lender']);
        $this->assertNotEmpty($parsed['credit']['closed_accounts']);
        $this->assertNotEmpty($parsed['credit']['inquiries']);
        $this->assertNotEmpty($parsed['credit']['overdue_buckets']);
        $this->assertNotEmpty($parsed['report_meta']['cir_number']);
    }
}
