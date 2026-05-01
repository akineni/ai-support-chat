<?php

namespace Database\Factories;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name'     => fake()->name(),
            'customer_email'    => fake()->safeEmail(),
            'session_token'     => (string) Str::uuid(),
            'status'            => ConversationStatus::OPEN,
            'mode'              => ConversationMode::AI,
            'assigned_agent_id' => null,
            'taken_over_at'     => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => ConversationStatus::CLOSED]);
    }

    public function pendingHandover(): static
    {
        return $this->state(['status' => ConversationStatus::PENDING_HANDOVER]);
    }

    public function humanMode(?User $agent = null): static
    {
        return $this->state(function () use ($agent) {
            $agent ??= User::factory()->create();

            return [
                'mode'              => ConversationMode::HUMAN,
                'assigned_agent_id' => $agent->id,
                'taken_over_at'     => now(),
            ];
        });
    }
}