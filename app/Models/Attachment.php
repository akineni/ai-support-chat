<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_url',
        'file_name',
        'file_type',
        'file_extension',
        'file_size',
        'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}