<?php

namespace Tests\Feature;

use App\Events\ConversationReleased;
use App\Events\ConversationTakenOver;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgentChatTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // Authentication Guard
    // -------------------------------------------------------

    public function test_unauthenticated_agent_cannot_access_conversations(): void
    {
        $response = $this->getJson('/api/v1/agent/conversations');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------
    // List Conversations
    // -------------------------------------------------------

    public function test_agent_can_list_all_conversations(): void
    {
        $agent = User::factory()->create();
        Conversation::factory()->count(3)->create();

        $response = $this->actingAs($agent)
            ->getJson('/api/v1/agent/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_agent_can_filter_conversations_by_status(): void
    {
        $agent = User::factory()->create();
        Conversation::factory()->count(2)->create(['status' => 'open']);
        Conversation::factory()->count(1)->create(['status' => 'pending_handover']);

        $response = $this->actingAs($agent)
            ->getJson('/api/v1/agent/conversations?status=pending_handover');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_agent_filter_rejects_invalid_status(): void
    {
        $agent = User::factory()->create();

        $response = $this->actingAs($agent)
            ->getJson('/api/v1/agent/conversations?status=invalid');

        $response->assertStatus(400)
            ->assertJsonPath('status', 'error');
    }

    // -------------------------------------------------------
    // Conversation Messages
    // -------------------------------------------------------

    public function test_agent_can_retrieve_conversation_messages(): void
    {
        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $response = $this->actingAs($agent)
            ->getJson("/api/v1/agent/conversations/{$conversation->uuid}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'meta']);
    }

    public function test_agent_gets_404_for_nonexistent_conversation(): void
    {
        $agent = User::factory()->create();

        $response = $this->actingAs($agent)
            ->getJson('/api/v1/agent/conversations/nonexistent-uuid/messages');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // Takeover
    // -------------------------------------------------------

    public function test_agent_can_take_over_a_conversation(): void
    {
        Event::fake([ConversationTakenOver::class]);

        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/takeover");

        $response->assertStatus(200)
            ->assertJsonPath('data.mode', 'human')
            ->assertJsonPath('data.assigned_agent.uuid', $agent->uuid);

        $this->assertDatabaseHas('conversations', [
            'uuid' => $conversation->uuid,
            'mode' => 'human',
        ]);

        Event::assertDispatched(ConversationTakenOver::class);
    }

    public function test_agent_cannot_take_over_already_assigned_conversation(): void
    {
        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->humanMode($agent)->create();

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/takeover");

        $response->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    // -------------------------------------------------------
    // Release
    // -------------------------------------------------------

    public function test_agent_can_release_conversation_back_to_ai(): void
    {
        Event::fake([ConversationReleased::class]);

        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->humanMode($agent)->create();

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/release");

        $response->assertStatus(200)
            ->assertJsonPath('data.mode', 'ai');

        $this->assertDatabaseHas('conversations', [
            'uuid' => $conversation->uuid,
            'mode' => 'ai',
        ]);

        Event::assertDispatched(ConversationReleased::class);
    }

    public function test_agent_cannot_release_conversation_already_in_ai_mode(): void
    {
        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->create(); // AI mode by default

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/release");

        $response->assertStatus(409);
    }

    // -------------------------------------------------------
    // Reply
    // -------------------------------------------------------

    public function test_agent_can_reply_to_a_conversation_in_human_mode(): void
    {
        Event::fake([MessageSent::class]);

        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->humanMode($agent)->create();

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/reply", [
                'body' => 'Hi, I am here to help!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sender_type', 'agent')
            ->assertJsonPath('data.body', 'Hi, I am here to help!');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_type'     => 'agent',
            'body'            => 'Hi, I am here to help!',
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_agent_cannot_reply_without_taking_over_first(): void
    {
        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->create(); // AI mode

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/reply", [
                'body' => 'Hello!',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Take over the conversation before replying.');
    }

    public function test_agent_reply_requires_body(): void
    {
        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->humanMode($agent)->create();

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/reply", []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------
    // Typing Indicator
    // -------------------------------------------------------

    public function test_agent_can_broadcast_typing_indicator(): void
    {
        Event::fake();

        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->humanMode($agent)->create();

        $response = $this->actingAs($agent)
            ->postJson("/api/v1/agent/conversations/{$conversation->uuid}/typing", [
                'is_typing' => true,
            ]);

        $response->assertStatus(200);
    }
}