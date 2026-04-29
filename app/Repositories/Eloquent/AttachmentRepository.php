<?php

namespace App\Repositories\Eloquent;

use App\Models\Attachment;
use App\Models\Message;
use App\Repositories\Contracts\AttachmentRepositoryInterface;

class AttachmentRepository implements AttachmentRepositoryInterface
{
    public function createForMessage(Message $message, array $data): Attachment
    {
        return $message->attachments()->create($data);
    }

    public function bulkCreateForMessage(Message $message, array $attachments): void
    {
        $message->attachments()->createMany($attachments);
    }
}