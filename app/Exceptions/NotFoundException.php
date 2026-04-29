<?php

namespace App\Exceptions;

use Exception;

class NotFoundException extends Exception
{
    public function __construct(string $resource = 'Resource')
    {
        parent::__construct("{$resource} not found.");
    }
}