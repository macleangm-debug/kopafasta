<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\SupportConversationService;
use Database\Seeders\BranchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportOpenConversationChooserFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BranchSeeder::class);
    }

    public function test_member_with_two_open_conversations_sees_chooser_and_cannot_auto_create(): void
    {
        $user = User::factory()->create(['role' => 'borrower', 'roles' => ['borrower'], 'is_active' => true]);
        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'customer_number' => 'C-CNVCHOOSE',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Chooser',
            'last_name' => 'Member',
            'phone' => '255700111222',
        ]);

        $older = SupportConversation::query()->create([
            'conversation_number' => 'CNV-OLD01',
            'customer_id' => $customer->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_WAITING,
            'topic' => 'PIN help',
            'last_message_at' => now()->subHour(),
        ]);
        $newer = SupportConversation::query()->create([
            'conversation_number' => 'CNV-NEW01',
            'customer_id' => $customer->id,
            'channel' => 'web_chat',
            'status' => SupportConversationService::STATUS_ACTIVE,
            'topic' => 'Payment stuck',
            'last_message_at' => now(),
        ]);

        $svc = app(SupportConversationService::class);
        $open = $svc->listOpenConversationsFor($customer, null);
        $this->assertCount(2, $open);
        $this->assertSame($newer->id, $open->first()->id);

        // openConversationFor must resume newest — never retire older history.
        $resumed = $svc->openConversationFor($customer, $user);
        $this->assertSame($newer->id, $resumed->id);
        $this->assertSame(SupportConversationService::STATUS_WAITING, $older->fresh()->status);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.support', ['section' => 'active']))
            ->assertOk()
            ->assertSee('Your open conversations', false)
            ->assertSee('CNV-NEW01', false)
            ->assertSee('CNV-OLD01', false)
            ->assertSee('Continue', false);

        $this->actingAs($user)
            ->get(route('site.borrower.support', [
                'chat' => 1,
                'conversation' => $older->id,
                'section' => 'active',
            ]))
            ->assertOk()
            ->assertSee('CNV-OLD01', false);
    }
}
