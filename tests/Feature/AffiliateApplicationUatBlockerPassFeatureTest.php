<?php

namespace Tests\Feature;

use App\Models\PartnerApplication;
use App\Models\Setting;
use App\Services\AffiliateTermsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AffiliateApplicationUatBlockerPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_page_exposes_carousel_nida_declarations_and_completeness_gate(): void
    {
        $html = $this->get(route('site.affiliate.apply'))->assertOk()->getContent();

        $this->assertStringContainsString('x-ref="stepRail"', $html);
        $this->assertStringContainsString('flex-nowrap', $html);
        $this->assertStringContainsString('scrollIntoView', $html);
        $this->assertStringContainsString('form-nida-capture', $html);
        $this->assertStringContainsString(__('site.affiliate_apply.nida_capture_cta'), $html);
        $this->assertStringContainsString(__('site.affiliate_apply.date_of_birth'), $html);
        $this->assertStringContainsString(__('site.affiliate_apply.gender'), $html);
        $this->assertStringContainsString(__('site.affiliate_apply.conduct_title'), $html);
        $this->assertStringContainsString(__('site.affiliate_apply.incomplete_title'), $html);
        $this->assertStringContainsString(':disabled="missing.length > 0"', $html);
        $this->assertStringContainsString(__('site.affiliate_apply.submit_payment'), $html);
        $this->assertStringContainsString(__('site.affiliate_apply.monthly_reach'), $html);
        $this->assertStringContainsString('data-date-trigger', $html);
        $this->assertStringContainsString(__('site.affiliate_apply.gender_male'), $html);
        $this->assertStringContainsString(__('site.affiliate_apply.gender_female'), $html);
        $this->assertStringNotContainsString('prefer not to say', strtolower($html));
        $this->assertStringNotContainsString('sipendii kusema', strtolower($html));
        $this->assertStringNotContainsString('name="date_of_birth" type="date"', $html);
        $this->assertStringNotContainsString('type="date" name="date_of_birth"', $html);
        $this->assertStringContainsString('affiliateApplyForm({', $html);
        $this->assertStringContainsString('function affiliateApplyForm', $html);
        $this->assertStringNotContainsString('scrollStepIntoView()); }); this.$watch', $html);
        $this->assertStringNotContainsString('data-step=\"', $html);
    }

    public function test_incomplete_application_cannot_reach_payment_server_side(): void
    {
        Storage::fake('public');
        Setting::set('affiliates.application_fee_required', true);
        Setting::set('affiliates.application_fee_amount', 5000);

        $this->from(route('site.affiliate.apply'))
            ->post(route('site.affiliate.apply.post'), [
                'applicant_category' => 'individual',
                'full_name' => 'Incomplete Applicant',
                'email' => 'incomplete@example.com',
                'phone' => '+255712345910',
                'region' => 'Dar es Salaam',
                'declaration_accepted' => '1',
            ])
            ->assertRedirect(route('site.affiliate.apply'))
            ->assertSessionHasErrors([
                'date_of_birth',
                'gender',
                'district',
                'ward',
                'occupation',
                'conduct_accepted',
                'doc_national_id_front',
                'doc_national_id_back',
            ]);

        $this->assertNull(PartnerApplication::query()->where('email', 'incomplete@example.com')->first());
    }

    public function test_other_occupation_requires_custom_value_server_side(): void
    {
        Storage::fake('public');

        $this->from(route('site.affiliate.apply'))
            ->post(route('site.affiliate.apply.post'), $this->completePayload([
                'occupation' => 'other',
                'occupation_other' => '',
            ]))
            ->assertRedirect(route('site.affiliate.apply'))
            ->assertSessionHasErrors('occupation_other');
    }

    public function test_nida_front_only_is_rejected(): void
    {
        Storage::fake('public');

        $payload = $this->completePayload();
        unset($payload['doc_national_id_back']);

        $this->from(route('site.affiliate.apply'))
            ->post(route('site.affiliate.apply.post'), $payload)
            ->assertRedirect(route('site.affiliate.apply'))
            ->assertSessionHasErrors('doc_national_id_back');
    }

    public function test_complete_application_stores_identity_declarations_and_informational_reach(): void
    {
        Storage::fake('public');
        Setting::set('affiliates.application_fee_required', false);
        Setting::set('affiliates.application_fee_amount', 0);

        $this->post(route('site.affiliate.apply.post'), $this->completePayload())
            ->assertRedirect(route('site.partners.apply.tracking', ['phone' => '+255712345911']));

        $application = PartnerApplication::query()->where('email', 'complete@example.com')->first();
        $this->assertNotNull($application);
        $this->assertSame('1990-05-12', $application->payload['identity']['date_of_birth'] ?? null);
        $this->assertSame('female', $application->payload['identity']['gender'] ?? null);
        $this->assertSame('11-30', $application->payload['monthly_reach'] ?? null);
        $this->assertTrue((bool) ($application->payload['declarations']['applicant']['accepted'] ?? false));
        $this->assertSame('application_conduct_v1', $application->payload['declarations']['conduct']['version'] ?? null);
        $this->assertNotEmpty($application->payload['declarations']['conduct']['accepted_at'] ?? null);
    }

    public function test_contract_revision_four_publishes_conduct_without_overwriting_history(): void
    {
        Setting::set('affiliates.terms.content_revision', 3);
        Setting::set('affiliates.terms.version', 3);
        Setting::set('affiliates.terms.body_en', 'STALE OVERRIDE');

        $terms = app(AffiliateTermsService::class);
        $this->assertGreaterThanOrEqual(4, $terms->agreementVersion());
        $this->assertSame(4, (int) Setting::get('affiliates.terms.content_revision'));
        $this->assertSame('', (string) Setting::get('affiliates.terms.body_en'));

        $body = $terms->template('en');
        $this->assertStringContainsString('Prohibited conduct and Affiliate obligations', $body);
        $this->assertStringContainsString('not authorized to represent themselves as a Kopafasta employee', $body);
        $premiumBody = (string) __('affiliate_terms.premium_body', [], 'en');
        $this->assertStringContainsString('Integrity and prohibited practices', $premiumBody);
        $this->assertStringContainsString('Premium status does not waive these conduct requirements', $premiumBody);
    }

    public function test_verify_card_body_reuses_affiliate_safety_copy(): void
    {
        $body = file_get_contents(resource_path('views/site/public/_card-verify-body.blade.php'));
        $this->assertStringContainsString('card_verify.affiliate_safety', $body);
        $this->assertStringContainsString('card_verify.affiliate_unauthorized', $body);
        $this->assertStringContainsString('card_verify.for_your_protection', $body);
        $this->assertStringContainsString("result['type'] ?? '') === 'affiliate'", $body);
    }

    public function test_sheet_select_other_hides_choices_on_mobile(): void
    {
        $sheet = file_get_contents(resource_path('views/components/site/sheet-select.blade.php'));
        $this->assertStringContainsString("x-show=\"selected !== otherValue\"", $sheet);
        $this->assertStringContainsString('confirmOther()', $sheet);
        $this->assertStringContainsString('cancelOther()', $sheet);
    }

    /** @param  array<string, mixed>  $overrides */
    private function completePayload(array $overrides = []): array
    {
        return array_merge([
            'applicant_category' => 'individual',
            'full_name' => 'Complete Applicant',
            'email' => 'complete@example.com',
            'phone' => '+255712345911',
            'date_of_birth' => '1990-05-12',
            'gender' => 'female',
            'district' => 'Ilala',
            'ward' => 'Kariakoo',
            'region' => 'Dar es Salaam',
            'occupation' => 'shop_owner',
            'sales_experience' => 'I sell airtime and assist customers daily.',
            'languages' => ['sw', 'en'],
            'previous_agent' => 'no',
            'why_affiliate' => 'I already advise customers on mobile money.',
            'acquisition_methods' => ['existing_customers', 'community'],
            'channels' => ['whatsapp'],
            'monthly_reach' => '11-30',
            'how_heard' => 'Friend',
            'first_10_customers' => 'I will start with my regular shop customers this month.',
            'registered_business' => 'no',
            'declaration_accepted' => '1',
            'conduct_accepted' => '1',
            'doc_national_id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'doc_national_id_back' => UploadedFile::fake()->image('id-back.jpg'),
        ], $overrides);
    }
}
