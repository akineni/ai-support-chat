<?php

namespace App\Exceptions;

use Exception;

class ConversationClosedException extends Exception
{
    public function __construct()
    {
        parent::__construct('This conversation is closed.');
    }
}