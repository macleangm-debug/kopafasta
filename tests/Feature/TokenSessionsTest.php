<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_tokens(): void
    {
        $user = User::factory()->create(['role' => 'officer']);

        $user->createToken('mobile');
        $user->createToken('web');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/tokens');

        $response->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'current']]]);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_revoke_a_non_current_token(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        $other = $user->createToken('mobile');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/tokens/'.$other->accessToken->id)->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertTrue(AuditLog::where('event', 'auth.token_revoked')->where('user_id', $user->id)->exists());
    }

    public function test_user_cannot_revoke_their_current_token(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        $created = $user->createToken('cli');
        $plainToken = $created->plainTextToken;
        $tokenId = $created->accessToken->id;

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->deleteJson('/api/auth/tokens/'.$tokenId)
            ->assertStatus(422);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_revoking_unknown_token_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'officer']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/tokens/999999')->assertStatus(404);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $alice = User::factory()->create(['role' => 'officer']);
        $bob = User::factory()->create(['role' => 'officer']);
        $bobToken = $bob->createToken('bob-phone');

        Sanctum::actingAs($alice);

        $this->deleteJson('/api/auth/tokens/'.$bobToken->accessToken->id)->assertStatus(404);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $bobToken->accessToken->id]);
    }
}
