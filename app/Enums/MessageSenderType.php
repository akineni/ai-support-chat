<?php

namespace App\Enums;

enum MessageSenderType: string
{
    case CUSTOMER = 'customer';
    case AI       = 'ai';
    case AGENT    = 'agent';
}