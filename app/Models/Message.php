<?php

namespace App\Models;

use App\Enums\MessageSenderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_type',
        'agent_id',
        'body',
        'is_read',
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'sender_type' => MessageSenderType::class,
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}