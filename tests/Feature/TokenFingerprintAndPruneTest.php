<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TokenFingerprintAndPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_ip_and_user_agent_on_issued_token(): void
    {
        User::factory()->create([
            'email' => 'fp@example.com',
            'password' => bcrypt('correct-password'),
            'role' => 'officer',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('User-Agent', 'TestAgent/9.9')
            ->postJson('/api/auth/login', [
                'email' => 'fp@example.com',
                'password' => 'correct-password',
            ])->assertOk();

        $row = DB::table('personal_access_tokens')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('203.0.113.7', $row->created_ip);
        $this->assertSame('TestAgent/9.9', $row->created_user_agent);
    }

    public function test_register_records_ip_on_issued_token(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])
            ->postJson('/api/auth/register', [
                'name' => 'Jane',
                'email' => 'jane2@example.com',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
            ])->assertCreated();

        $row = DB::table('personal_access_tokens')->latest('id')->first();
        $this->assertSame('198.51.100.4', $row->created_ip);
    }

    public function test_prune_command_removes_expired_tokens(): void
    {
        $user = User::factory()->create(['role' => 'officer']);

        $kept = $user->createToken('current')->accessToken;
        $expired = $user->createToken('old')->accessToken;
        $expired->forceFill(['expires_at' => now()->subDays(10)])->save();

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $kept->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $expired->id]);
    }

    public function test_prune_command_is_scheduled_daily(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $events = collect($schedule->events())->filter(
            fn ($e) => str_contains($e->command ?? '', 'sanctum:prune-expired')
        );

        $this->assertTrue($events->isNotEmpty(), 'sanctum:prune-expired should be scheduled');
        $this->assertTrue($events->contains(fn ($e) => $e->expression === '0 0 * * *'));
    }
}
