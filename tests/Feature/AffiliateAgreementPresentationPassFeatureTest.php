<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateTermsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateAgreementPresentationPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_agreement_strips_asterisks_and_numbers_sections_sequentially(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-DOC-1',
            'name' => 'Doc Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712377001',
            'affiliate_code' => 'DOC001',
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
        ]);

        $parts = app(AffiliateTermsService::class)->parseDocument($affiliate);
        $this->assertNotEmpty($parts['meta']);
        $this->assertTrue(collect($parts['meta'])->contains(fn ($row) => str_contains((string) $row['label'], 'Effective') || str_contains((string) $row['label'], 'Tarehe')));
        foreach ($parts['meta'] as $row) {
            $this->assertStringNotContainsString('**', $row['label']);
            $this->assertStringNotContainsString('**', $row['value']);
        }
        $this->assertGreaterThan(5, count($parts['sections']));
        foreach ($parts['sections'] as $index => $section) {
            $this->assertDoesNotMatchRegularExpression('/^\d+\.\s/', $section['title']);
            $this->assertStringNotContainsString('**', $section['body']);
            $this->assertSame($index, $index); // sequential by array order
        }
        $this->assertSame('Appointment and relationship', $parts['sections'][0]['title']);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.profile', ['section' => 'agreement']))
            ->assertOk()
            ->assertSee('1.', false)
            ->assertSee('Appointment and relationship', false)
            ->assertDontSee('1. 1.', false)
            ->assertDontSee('**Effective date:**', false)
            ->assertDontSee('**Affiliate name:**', false)
            ->getContent();

        $this->assertStringNotContainsString('**', strip_tags($html));
        $this->assertStringNotContainsString(__('site.affiliate_portal.doc_code'), $html);
        $this->assertStringNotContainsString('DOC001', $html);
    }
}
