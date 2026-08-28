<?php

namespace Tests\Unit;

use App\Models\CustomerAsset;
use App\Models\LoanApplicationDocumentRequest;
use App\Services\ApplicationDocumentRequestService;
use App\Services\CollateralCardService;
use Tests\TestCase;

class CollateralCardServiceTest extends TestCase
{
    public function test_card_payload_hides_valuation_until_fsv_exists(): void
    {
        $asset = new CustomerAsset([
            'asset_type' => 'vehicle',
            'label' => 'Toyota RAV4',
            'registration_number' => 'T123ABC',
            'metadata' => [
                'details' => [
                    'make' => 'Toyota',
                    'year' => '2024',
                    'insurance_type' => 'comprehensive',
                    'insurance_expires_at' => now()->addYear()->toDateString(),
                ],
                'insurance_document_path' => 'insurance.pdf',
            ],
        ]);
        $asset->id = 1;

        $card = app(CollateralCardService::class)->forAsset($asset, null, CollateralCardService::VIEWER_SCREENING);

        $this->assertSame('Toyota RAV4', $card['label']);
        $this->assertFalse($card['valued']);
        $this->assertFalse($card['show']['valuation']);
        $this->assertTrue($card['show']['identity']);
        $this->assertTrue($card['show']['insurance']);
        $this->assertContains('Insured', collect($card['badges'])->pluck('label')->all());
    }

    public function test_operational_status_and_timing_phrases(): void
    {
        $service = app(ApplicationDocumentRequestService::class);
        $pending = new LoanApplicationDocumentRequest([
            'label' => 'Updated Bank Statement',
            'status' => 'pending',
        ]);
        $pending->created_at = now()->subHours(3);

        $this->assertTrue($service->isOutstanding($pending));
        $this->assertSame('Requested', $service->operationalStatusLabel($pending));
        $this->assertStringContainsString('Requested', $service->outstandingTimingPhrase($pending));
        $this->assertSame('Waiting for borrower', $service->waitingOnLabel($pending));

        $uploaded = new LoanApplicationDocumentRequest([
            'label' => 'Updated Bank Statement',
            'status' => 'uploaded',
        ]);
        $uploaded->updated_at = now()->subMinutes(35);
        $uploaded->setRelation('uploads', collect());
        $this->assertSame('Under review', $service->operationalStatusLabel($uploaded));
        $this->assertStringContainsString('Submitted', $service->outstandingTimingPhrase($uploaded));
        $this->assertStringContainsString('Awaiting review', $service->outstandingTimingPhrase($uploaded));

        $rejected = new LoanApplicationDocumentRequest([
            'label' => 'New collateral photo',
            'status' => 'rejected',
        ]);
        $rejected->updated_at = now()->subDay();
        $this->assertSame('Needs replacement', $service->operationalStatusLabel($rejected));
        $this->assertStringContainsString('Replacement requested', $service->outstandingTimingPhrase($rejected));

        $valuer = new LoanApplicationDocumentRequest([
            'label' => 'Collateral valuation document',
            'status' => 'pending',
        ]);
        $this->assertSame('Waiting for valuer', $service->operationalStatusLabel($valuer));

        $satisfied = new LoanApplicationDocumentRequest([
            'label' => 'Updated Bank Statement',
            'status' => 'satisfied',
        ]);
        $this->assertFalse($service->isOutstanding($satisfied));
        $this->assertSame('Accepted', $service->operationalStatusLabel($satisfied));
    }
}
