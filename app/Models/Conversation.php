<?php

namespace App\Models;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $fillable = [
        'uuid',
        'customer_name',
        'customer_email',
        'session_token',
        'status',
        'mode',
        'assigned_agent_id',
        'taken_over_at',
    ];

    protected $casts = [
        'taken_over_at' => 'datetime',
        'status'        => ConversationStatus::class,
        'mode'          => ConversationMode::class,
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function isAiMode(): bool
    {
        return $this->mode === ConversationMode::AI;
    }

    public function isHumanMode(): bool
    {
        return $this->mode === ConversationMode::HUMAN;
    }

    public function isClosed(): bool
    {
        return $this->status === ConversationStatus::CLOSED;
    }
}