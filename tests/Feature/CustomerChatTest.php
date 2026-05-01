<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerChatTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------
    // Start Conversation
    // -------------------------------------------------------

    public function test_customer_can_start_a_conversation(): void
    {
        $response = $this->postJson('/api/v1/chat', [
            'customer_name'  => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'session_token',
                    'conversation' => ['uuid', 'customer_name', 'customer_email', 'status', 'mode'],
                ],
            ])
            ->assertJsonPath('data.conversation.mode', 'ai')
            ->assertJsonPath('data.conversation.status', 'open');

        $this->assertDatabaseHas('conversations', [
            'customer_name'  => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);
    }

    public function test_customer_can_start_a_conversation_without_email(): void
    {
        $response = $this->postJson('/api/v1/chat', [
            'customer_name' => 'Jane Doe',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation.customer_email', null);
    }

    public function test_start_conversation_requires_customer_name(): void
    {
        $response = $this->postJson('/api/v1/chat', []);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_start_conversation_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/chat', [
            'customer_name'  => 'John Doe',
            'customer_email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------
    // Message History
    // -------------------------------------------------------

    public function test_customer_can_retrieve_message_history(): void
    {
        $conversation = Conversation::factory()->create();

        $response = $this->getJson("/api/v1/chat/{$conversation->session_token}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_message_history_returns_404_for_invalid_token(): void
    {
        $response = $this->getJson('/api/v1/chat/invalid-token/messages');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }

    // -------------------------------------------------------
    // Send Message
    // -------------------------------------------------------

    public function test_customer_can_send_a_message_and_receives_ai_reply(): void
    {
        Event::fake([MessageSent::class]);

        $this->mockAiReply('Hello! How can I help you today?');

        $conversation = Conversation::factory()->create();

        $response = $this->postJson("/api/v1/chat/{$conversation->session_token}/messages", [
            'body' => 'Hi there!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_type'     => 'customer',
            'body'            => 'Hi there!',
        ]);

        // AI reply should also be persisted (queue is sync in tests)
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_type'     => 'ai',
        ]);
    }

    public function test_send_message_returns_404_for_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/chat/invalid-token/messages', [
            'body' => 'Hello',
        ]);

        $response->assertStatus(404);
    }

    public function test_send_message_requires_body_when_no_attachments(): void
    {
        $conversation = Conversation::factory()->create();

        $response = $this->postJson("/api/v1/chat/{$conversation->session_token}/messages", []);

        $response->assertStatus(422);
    }

    public function test_cannot_send_message_to_closed_conversation(): void
    {
        $conversation = Conversation::factory()->closed()->create();

        $response = $this->postJson("/api/v1/chat/{$conversation->session_token}/messages", [
            'body' => 'Hello?',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This conversation is closed.');
    }

    public function test_message_in_human_mode_does_not_trigger_ai_reply(): void
    {
        $agent        = User::factory()->create();
        $conversation = Conversation::factory()->humanMode($agent)->create();

        $this->mockAiReply('This should not be called.');

        $response = $this->postJson("/api/v1/chat/{$conversation->session_token}/messages", [
            'body' => 'Hi, is anyone there?',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'sender_type'     => 'ai',
        ]);
    }

    // -------------------------------------------------------
    // Typing Indicator
    // -------------------------------------------------------

    public function test_customer_can_broadcast_typing_indicator(): void
    {
        Event::fake();

        $conversation = Conversation::factory()->create();

        $response = $this->postJson("/api/v1/chat/{$conversation->session_token}/typing", [
            'is_typing' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    // -------------------------------------------------------
    // Rate Limiting
    // -------------------------------------------------------

    public function test_start_conversation_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/chat', ['customer_name' => 'User ' . $i]);
        }

        $response = $this->postJson('/api/v1/chat', ['customer_name' => 'One Too Many']);

        $response->assertStatus(429)
            ->assertJsonPath('status', 'error');
    }

    public function test_send_message_is_rate_limited(): void
    {
        $this->mockAiReply('OK');

        $conversation = Conversation::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/v1/chat/{$conversation->session_token}/messages", [
                'body' => 'Message ' . $i,
            ]);
        }

        $response = $this->postJson("/api/v1/chat/{$conversation->session_token}/messages", [
            'body' => 'One too many',
        ]);

        $response->assertStatus(429);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function mockAiReply(string $reply): void
    {
        $stub = $this->createStub(AiChatService::class);
        $stub->method('generateReply')->willReturn($reply);
        $stub->method('shouldEscalate')->willReturn(false);
        $stub->method('cleanResponse')->willReturn($reply);

        $this->app->instance(AiChatService::class, $stub);
    }
}
