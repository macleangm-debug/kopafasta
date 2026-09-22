<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\CrbCreditCheckService;
use App\Services\CrbService;
use App\Services\Crb\DnbLiveCrbClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrbLiveActivationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_of_birth_surrogates_block_uses_real_dob_never_fabricated(): void
    {
        $client = app(DnbLiveCrbClient::class);

        $withDob = $client->describeSearchCriteria(
            '19960815-12107-00005-21',
            'Steward Alphonce Amuli',
            '1996-08-15',
            '255686398843',
        );
        $this->assertTrue($withDob['includes_dob']);
        $this->assertSame('15-Aug-1996', $withDob['date_of_birth']);
        $this->assertFalse($withDob['fabricated_dob']);
        $this->assertSame('TZ', $withDob['nationality']);

        $withoutDob = $client->describeSearchCriteria(
            '19960815-12107-00005-21',
            'Steward Alphonce Amuli',
            null,
            '255686398843',
        );
        $this->assertFalse($withoutDob['includes_dob']);
        $this->assertNull($withoutDob['date_of_birth']);
        $this->assertFalse($withoutDob['fabricated_dob']);
        $this->assertNull($withoutDob['nationality']);
    }

    public function test_production_never_uses_stub_even_when_sandbox_setting_on(): void
    {
        Setting::set('kyc.crb_sandbox', true);
        config(['crb.driver' => 'stub']);

        $this->assertTrue(app(CrbService::class)->usesStub());

        app()->detectEnvironment(fn () => 'production');
        $this->assertFalse(app(CrbService::class)->usesStub());

        $ops = app(CrbService::class)->operationalStatus();
        $this->assertSame('D&B LIVE', $ops['mode']);
        $this->assertTrue($ops['production_stub_blocked']);
        $this->assertArrayNotHasKey('password', $ops);
        $this->assertArrayNotHasKey('email', $ops);
    }

    public function test_install_stub_report_refuses_production(): void
    {
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-LIVE-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Live',
            'last_name' => 'Guard',
            'phone' => '25571'.random_int(1000000, 9999999),
            'national_id' => '19900101-12345-00001-23',
            'date_of_birth' => '1990-01-01',
        ]);

        app()->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stub CRB fixtures cannot be installed in production');
        app(CrbCreditCheckService::class)->installStubReport($customer, 'clean');
    }

    public function test_operational_status_distinguishes_stub_and_live_without_secrets(): void
    {
        Setting::set('kyc.crb_sandbox', true);
        $ops = app(CrbService::class)->operationalStatus();
        $this->assertSame('TEST / CRB STUB', $ops['mode']);
        $this->assertSame('STUB', $ops['mode_short']);
        $json = json_encode($ops);
        $this->assertStringNotContainsString('CRB_PASSWORD', (string) $json);
        $this->assertDoesNotMatchRegularExpression('/"password"\s*:/', (string) $json);
    }
}
