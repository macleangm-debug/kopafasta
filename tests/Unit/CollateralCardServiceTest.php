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

    public function test_borrower_card_hides_market_and_forced_sale_values(): void
    {
        $html = view('components.site.collateral-card', [
            'selected' => [
                'label' => 'Toyota Rav4',
                'type_label' => 'Vehicle',
                'viewer' => CollateralCardService::VIEWER_BORROWER,
                'registration_number' => 'T123ABC',
                'make' => 'Toyota',
                'year' => '2025',
                'show' => [
                    'identity' => true,
                    'ownership' => false,
                    'insurance' => false,
                    'valuation' => false,
                    'ltv' => false,
                    'valuer' => false,
                ],
                'badges' => [
                    ['label' => 'Insured', 'tone' => 'emerald'],
                    ['label' => 'Waiting for valuer', 'tone' => 'amber'],
                ],
                'valuation' => [
                    'market_value' => 20_000_000,
                    'forced_sale_value' => 15_000_000,
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Toyota Rav4', $html);
        $this->assertStringContainsString('Waiting for valuer', $html);
        $this->assertStringContainsString('T123ABC', $html);
        $this->assertStringContainsString('size-12', $html);
        $this->assertStringNotContainsString('Insured', $html);
        $this->assertStringNotContainsString(__('borrower.profile.collateral_fields.registration_number'), $html);
        $this->assertStringNotContainsString(__('borrower.profile.collateral_fields.chassis_number'), $html);
        $this->assertStringNotContainsString('Forced sale value', $html);
        $this->assertStringNotContainsString('Market value', $html);
    }
}
