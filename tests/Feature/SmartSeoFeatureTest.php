<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\PlusSubject;
use App\Models\PlusSubjectCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartSeoFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function allowIndexing(): void
    {
        config(['seo.allow_indexing' => true]);
        Setting::set('seo.default_index', true);
        Setting::set('seo.canonical_domain', 'https://seo.example.test');
    }

    private function product(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'code' => 'IL-SEO',
            'name' => 'Individual Loan',
            'name_sw' => 'Mkopo Binafsi',
            'description' => 'Flexible personal and business financing in Tanzania.',
            'status' => 'active',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
            'requires_collateral' => false,
            'seo_indexable' => true,
        ], $overrides));
    }

    public function test_public_home_has_locale_title_description_and_canonical(): void
    {
        $this->allowIndexing();

        $en = $this->withSession(['locale' => 'en'])->get('/');
        $en->assertOk();
        $en->assertSee(__('seo.home_title', [], 'en'), false);
        $en->assertSee(__('seo.home_description', [], 'en'), false);
        $en->assertSee('<link rel="canonical" href="https://seo.example.test/">', false);
        $en->assertSee('<meta name="robots" content="index, follow">', false);
        $en->assertSee('"@type":"Organization"', false);
        $this->assertStringNotContainsString('hreflang', $en->getContent());

        $sw = $this->withSession(['locale' => 'sw'])->get('/');
        $sw->assertOk();
        $sw->assertSee(__('seo.home_title', [], 'sw'), false);
        $sw->assertSee(__('seo.home_description', [], 'sw'), false);
        $this->assertStringNotContainsString(__('seo.home_title', [], 'en'), $sw->getContent());
    }

    public function test_production_public_page_can_be_indexable_and_staging_cannot(): void
    {
        Setting::set('seo.canonical_domain', 'https://seo.example.test');
        Setting::set('seo.default_index', true);

        config(['seo.allow_indexing' => true]);
        $this->withSession(['locale' => 'en'])->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false);

        config(['seo.allow_indexing' => false]);
        $this->withSession(['locale' => 'en'])->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_login_and_registration_are_noindex(): void
    {
        $this->allowIndexing();

        $this->get(route('site.login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get(route('site.register.borrower'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_borrower_and_admin_workspaces_are_noindex(): void
    {
        $this->allowIndexing();

        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-SEO-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Private',
            'last_name' => 'Borrower',
            'phone' => '255700111222',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('site.borrower.dashboard'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('CU-SEO-1', false);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->followingRedirects()
            ->get(route('admin.teams.screening'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_query_string_variants_are_not_indexable(): void
    {
        $this->allowIndexing();

        $this->withSession(['locale' => 'en'])->get('/?doc=secret-file')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('<link rel="canonical" href="https://seo.example.test/">', false);
    }

    public function test_product_seo_uses_product_and_settings_source_of_truth(): void
    {
        $this->allowIndexing();
        Setting::set('loan.collateral_requirement_mode', 'above_amount');
        Setting::set('loan.collateral_required_above', 750_000);
        $product = $this->product();

        $en = $this->withSession(['locale' => 'en'])->get(route('site.product', $product->code));
        $en->assertOk();
        $en->assertSee('Individual Loan', false);
        $en->assertSee('kopafasta', false);
        $en->assertSee('750,000', false);
        $en->assertDontSee('200,000', false);
        $en->assertSee('<meta name="robots" content="index, follow">', false);
        $en->assertSee('<link rel="canonical" href="https://seo.example.test/loans/product/IL-SEO">', false);
        $en->assertSee('"@type":"FAQPage"', false);

        $sw = $this->withSession(['locale' => 'sw'])->get(route('site.product', $product->code));
        $sw->assertOk();
        $sw->assertSee('Mkopo Binafsi', false);
    }

    public function test_article_seo_indexes_published_and_excludes_drafts(): void
    {
        $this->allowIndexing();
        $category = PlusSubjectCategory::create([
            'slug' => 'money',
            'title_en' => 'My money',
            'title_sw' => 'Pesa zangu',
            'sort' => 1,
            'status' => 'published',
        ]);
        $published = PlusSubject::create([
            'plus_subject_category_id' => $category->id,
            'slug' => 'money-cash-flow',
            'title_en' => 'Understand business cash flow',
            'title_sw' => 'Elewa mtiririko wa pesa',
            'intro_en' => 'Cash flow is the money that comes in and goes out of a small business.',
            'intro_sw' => 'Mtiririko wa pesa ni pesa inayoingia na kutoka kwenye biashara ndogo.',
            'body_en' => "Track sales.\n\nTrack spending.",
            'status' => 'published',
            'published_at' => now()->subDay(),
            'seo_indexable' => true,
        ]);
        PlusSubject::create([
            'plus_subject_category_id' => $category->id,
            'slug' => 'money-draft',
            'title_en' => 'Draft article about a borrower named Amina',
            'title_sw' => 'Rasimu',
            'intro_en' => 'Draft only',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('site.learn.show', [$category->slug, $published->slug]))
            ->assertOk()
            ->assertSee('Understand business cash flow', false)
            ->assertSee('"@type":"Article"', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);

        $this->get(route('site.learn.show', [$category->slug, 'money-draft']))
            ->assertNotFound();
    }

    public function test_sitemap_includes_public_pages_and_excludes_private_and_drafts(): void
    {
        $this->allowIndexing();
        $this->product();
        $category = PlusSubjectCategory::create([
            'slug' => 'loans',
            'title_en' => 'Borrowing',
            'title_sw' => 'Mikopo',
            'status' => 'published',
        ]);
        PlusSubject::create([
            'plus_subject_category_id' => $category->id,
            'slug' => 'loans-affordability',
            'title_en' => 'Affordability',
            'title_sw' => 'Uwezo',
            'intro_en' => 'How much you can repay.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        PlusSubject::create([
            'plus_subject_category_id' => $category->id,
            'slug' => 'loans-draft',
            'title_en' => 'Hidden draft',
            'title_sw' => 'Rasimu',
            'status' => 'draft',
        ]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://seo.example.test/</loc>', $xml);
        $this->assertStringContainsString('/loans</loc>', $xml);
        $this->assertStringContainsString('/loans/product/IL-SEO</loc>', $xml);
        $this->assertStringContainsString('/learn/loans/loans-affordability</loc>', $xml);
        $this->assertStringNotContainsString('/login', $xml);
        $this->assertStringNotContainsString('/register', $xml);
        $this->assertStringNotContainsString('/borrower', $xml);
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertStringNotContainsString('loans-draft', $xml);
        $this->assertStringNotContainsString('Hidden draft', $xml);
    }

    public function test_robots_txt_and_canonical_domain_come_from_configuration(): void
    {
        $this->allowIndexing();

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: https://seo.example.test/sitemap.xml', false)
            ->assertSee('Disallow: /login', false)
            ->assertSee('Disallow: /borrower', false)
            ->assertSee('Disallow: /admin', false);

        config(['seo.allow_indexing' => false]);
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nDisallow: /", false);
    }

    public function test_settings_hub_seo_page_saves_canonical_domain(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.seo'))
            ->assertOk()
            ->assertSee('Website SEO', false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.seo.save'), [
                'site_name' => 'Kopafasta',
                'title_pattern' => '{page} — {site}',
                'canonical_domain' => 'https://kopafasta.example',
                'default_index' => '1',
                'default_description' => 'Loans in Tanzania for business and assets.',
            ])
            ->assertRedirect();

        $this->assertSame('https://kopafasta.example', Setting::get('seo.canonical_domain'));
    }
}
