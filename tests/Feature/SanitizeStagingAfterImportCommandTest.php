<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SanitizeStagingAfterImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_refuses_when_not_staging(): void
    {
        $this->artisan('staging:sanitize-after-import', ['--force' => true])
            ->assertFailed();
    }

    public function test_staging_strips_payin_and_sms_secrets_without_changing_fees(): void
    {
        config(['app.env' => 'staging']);
        app()->instance('env', 'staging');

        Setting::set('payin.enabled', true);
        Setting::set('payin.environment', 'production');
        Setting::set('payin.api_key', 'pk_live_should_not_copy');
        Setting::set('payin.api_secret', 'sk_live_should_not_copy');
        Setting::set('gateway.sms_api_key', 'sms-live');
        Setting::set('membership.registration_fee', 2500);

        if (Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'payload')) {
            try {
                DB::table('jobs')->insert([
                    'queue' => 'default',
                    'payload' => '{}',
                    'attempts' => 0,
                    'reserved_at' => null,
                    'available_at' => time(),
                    'created_at' => time(),
                ]);
            } catch (\Throwable) {
                // Jobs schema varies; secret stripping is the assertion that matters.
            }
        }

        $this->artisan('staging:sanitize-after-import', ['--force' => true])
            ->assertSuccessful();

        $this->assertFalse((bool) Setting::get('payin.enabled'));
        $this->assertSame('sandbox', Setting::get('payin.environment'));
        $this->assertSame('', (string) Setting::get('payin.api_key'));
        $this->assertSame('', (string) Setting::get('payin.api_secret'));
        $this->assertSame('', (string) Setting::get('gateway.sms_api_key'));
        $this->assertEquals(2500, (float) Setting::get('membership.registration_fee'));

        if (Schema::hasTable('jobs')) {
            $this->assertSame(0, DB::table('jobs')->count());
        }
    }
}
