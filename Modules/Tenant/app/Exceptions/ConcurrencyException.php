<?php

namespace Modules\Tenant\app\Exceptions;

use Exception;

class ConcurrencyException extends Exception
{
    public function __construct($message = 'A concurrency conflict occurred. The resource has been updated by another user. Please refresh and try again.', $code = 409, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
