<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOpenAi(string $text = 'Halo, aku WeDi AI.'): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.daily_limit' => 40]);
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => $text]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ])]);
    }

    public function test_guest_cannot_open_ai_page(): void
    {
        $this->get('/ai')->assertRedirect('/login');
    }

    public function test_user_can_create_new_chat_and_send_message(): void
    {
        $this->fakeOpenAi();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/ai')->assertRedirect();
        $conversation = $user->aiConversations()->first();
        $this->assertNotNull($conversation);

        $this->actingAs($user)->postJson("/ai/{$conversation->id}/messages", ['message' => 'Aku capek hari ini'])
            ->assertOk()->assertJson(['reply' => 'Halo, aku WeDi AI.']);

        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Aku capek hari ini']);
        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation->id, 'role' => 'assistant']);
        $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'feature' => 'chat', 'status' => 'success', 'total_tokens' => 15]);
        $this->assertSame('Aku capek hari ini', $conversation->fresh()->title);
        $this->assertSame('Bearer test-key', Http::recorded()[0][0]->header('Authorization')[0]);
    }

    public function test_conversations_are_private_to_their_owner(): void
    {
        $this->fakeOpenAi();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $owner->aiConversations()->create(['last_message_at' => now()]);

        $this->actingAs($other)->get("/ai/{$conversation->id}")->assertNotFound();
        $this->actingAs($other)->postJson("/ai/{$conversation->id}/messages", ['message' => 'hai'])->assertNotFound();
        $this->actingAs($other)->delete("/ai/{$conversation->id}")->assertNotFound();
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_user_can_delete_conversation_and_all_history(): void
    {
        $user = User::factory()->create();
        $a = $user->aiConversations()->create();
        $a->messages()->create(['role' => 'user', 'content' => 'x']);
        $user->aiConversations()->create();

        $this->actingAs($user)->delete("/ai/{$a->id}")->assertRedirect(route('ai.index'));
        $this->assertDatabaseMissing('ai_conversations', ['id' => $a->id]);
        $this->assertDatabaseCount('ai_messages', 0);

        $this->actingAs($user)->delete('/ai')->assertRedirect(route('ai.index'));
        $this->assertSame(0, AiConversation::where('user_id', $user->id)->count());
    }

    public function test_missing_api_key_returns_friendly_error(): void
    {
        config(['services.openai.key' => null]);
        $user = User::factory()->create();
        $c = $user->aiConversations()->create();

        $this->actingAs($user)->postJson("/ai/{$c->id}/messages", ['message' => 'hai'])->assertStatus(503)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_daily_limit_blocks_requests_without_calling_openai(): void
    {
        $this->fakeOpenAi();
        config(['services.openai.daily_limit' => 2]);
        $user = User::factory()->create();
        $c = $user->aiConversations()->create();
        foreach ([1, 2] as $i) {
            AiUsageLog::create(['user_id' => $user->id, 'feature' => 'chat', 'status' => 'success']);
        }

        $this->actingAs($user)->postJson("/ai/{$c->id}/messages", ['message' => 'hai'])->assertStatus(429);
        Http::assertNothingSent();
    }

    public function test_openai_failure_does_not_store_the_message(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'boom'], 500)]);
        $user = User::factory()->create();
        $c = $user->aiConversations()->create();

        $this->actingAs($user)->postJson("/ai/{$c->id}/messages", ['message' => 'hai'])->assertStatus(502);
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'status' => 'failed']);
    }

    public function test_message_validation(): void
    {
        $user = User::factory()->create();
        $c = $user->aiConversations()->create();
        $this->actingAs($user)->postJson("/ai/{$c->id}/messages", ['message' => ''])->assertStatus(422);
        $this->actingAs($user)->postJson("/ai/{$c->id}/messages", ['message' => str_repeat('a', 4001)])->assertStatus(422);
    }
}
