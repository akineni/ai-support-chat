<?php

namespace App\Repositories\Contracts;

use App\Models\Attachment;
use App\Models\Message;

interface AttachmentRepositoryInterface
{
    public function createForMessage(Message $message, array $data): Attachment;

    public function bulkCreateForMessage(Message $message, array $attachments): void;
}