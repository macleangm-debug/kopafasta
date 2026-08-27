<?php

namespace Tests\Feature;

use App\Models\BrokenPage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionPagesAndBrokenPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/__kf-qa/abort/{code}', function (int $code) {
                abort($code);
            })->whereNumber('code');

            Route::get('/__kf-qa/forbidden', function () {
                throw new AuthorizationException('not allowed');
            });

            Route::get('/__kf-qa/csrf', function () {
                throw new TokenMismatchException;
            });

            Route::get('/__kf-qa/boom', function () {
                throw new \RuntimeException('secret-sql-trace-should-not-leak');
            });
        });
    }

    public function test_valid_routes_render_normally_and_are_not_logged(): void
    {
        $this->get('/')->assertOk()->assertDontSee('This page is not here', false);
        $this->get('/login')->assertOk()->assertDontSee('This page is not here', false);

        $this->get('/borrower/plus')->assertRedirect();
        $this->get('/admin/growth')->assertRedirect();
        $this->get('/partner/tasks/12')->assertRedirect();
        $this->get('/apply')->assertRedirect();

        $this->assertSame(0, BrokenPage::query()->count());
    }

    public function test_authenticated_valid_pages_are_not_logged(): void
    {
        $borrower = User::factory()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $borrower->id,
            'customer_number' => 'CU-EXC-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Plus',
            'phone' => '255700009992',
        ]);

        $this->actingAs($borrower)
            ->get('/borrower/plus')
            ->assertOk()
            ->assertDontSee('This page is not here', false);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->get('/admin/growth')
            ->assertOk()
            ->assertDontSee('This page is not here', false);

        $this->assertSame(0, BrokenPage::query()->count());
    }

    public function test_unknown_route_is_404_and_deduplicates(): void
    {
        $this->get('/this-page-should-not-exist-kf')
            ->assertNotFound()
            ->assertSee('This page is not here', false)
            ->assertDontSee('NotFoundHttpException', false)
            ->assertDontSee('SQLSTATE', false);

        $this->get('/this-page-should-not-exist-kf')->assertNotFound();

        $this->assertSame(1, BrokenPage::query()->count());
        $row = BrokenPage::query()->first();
        $this->assertSame(404, (int) $row->status);
        $this->assertSame('/this-page-should-not-exist-kf', $row->path);
        $this->assertSame(2, (int) $row->occurrence_count);
        $this->assertNotNull($row->first_seen_at);
        $this->assertNotNull($row->last_seen_at);
        $this->assertNotNull($row->fingerprint);
    }

    public function test_authorization_failure_is_403_page(): void
    {
        $this->get('/__kf-qa/abort/403')
            ->assertForbidden()
            ->assertSee('You cannot open this page', false)
            ->assertDontSee('You do not have permission to access this area.', false);

        $this->get('/__kf-qa/forbidden')
            ->assertForbidden()
            ->assertSee('You cannot open this page', false)
            ->assertDontSee('not allowed', false);

        $this->assertSame(2, BrokenPage::query()->where('status', 403)->count());
    }

    public function test_expired_csrf_is_419_page(): void
    {
        $this->get('/__kf-qa/csrf')
            ->assertStatus(419)
            ->assertSee('This session expired', false);

        $this->assertSame(1, BrokenPage::query()->where('status', 419)->count());
    }

    public function test_rate_limit_is_429_page(): void
    {
        $this->get('/__kf-qa/abort/429')
            ->assertStatus(429)
            ->assertSee('Please wait a moment', false);

        $this->assertSame(1, BrokenPage::query()->where('status', 429)->count());
    }

    public function test_unhandled_error_is_500_without_leaking_details(): void
    {
        config(['app.debug' => false]);

        $this->get('/__kf-qa/boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong', false)
            ->assertDontSee('secret-sql-trace-should-not-leak', false)
            ->assertDontSee('RuntimeException', false);

        $this->assertSame(1, BrokenPage::query()->where('status', 500)->count());
        $this->assertStringContainsString('secret-sql-trace-should-not-leak', (string) BrokenPage::query()->first()->message);
    }

    public function test_maintenance_is_503_page(): void
    {
        $this->get('/__kf-qa/abort/503')
            ->assertStatus(503)
            ->assertSee('We are updating the service', false);

        $this->assertSame(1, BrokenPage::query()->where('status', 503)->count());
    }

    public function test_noise_404s_are_not_logged(): void
    {
        $this->get('/favicon.ico');
        $this->get('/robots.txt');
        $this->get('/build/assets/missing.js');
        $this->get('/wp-admin/css/missing.css');
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
            ->get('/random-bot-probe-kf');

        $this->assertSame(0, BrokenPage::query()->count());
    }

    public function test_support_inventory_shows_incident_fields_not_a_route_catalog(): void
    {
        $this->get('/this-page-should-not-exist-kf')->assertNotFound();
        $this->get('/login')->assertOk();

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $incident = BrokenPage::query()->first();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.broken-pages.index'))
            ->assertOk()
            ->assertSee('Incident inventory', false)
            ->assertSee('/this-page-should-not-exist-kf', false)
            ->assertDontSee('/login', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.broken-pages.show', $incident))
            ->assertOk()
            ->assertSee('First seen', false)
            ->assertSee('Last seen', false)
            ->assertSee('Hits', false);
    }
}
