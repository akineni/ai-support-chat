<?php

namespace App\Exceptions;

use Exception;

class ConflictException extends Exception
{
    public function __construct(string $message = 'Conflict.')
    {
        parent::__construct($message);
    }
}