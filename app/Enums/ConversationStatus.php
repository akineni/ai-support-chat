<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case OPEN             = 'open';
    case PENDING_HANDOVER = 'pending_handover';
    case CLOSED           = 'closed';
}