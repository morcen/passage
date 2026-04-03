<?php

namespace Morcen\Passage\Exceptions;

use Exception;

class PassageRequestAbortedException extends Exception
{
    /**
     * @param  string  $message  Error message returned to the caller.
     * @param  int  $status  HTTP status code for the response (default 400).
     */
    public function __construct(string $message = 'Request aborted.', int $status = 400)
    {
        parent::__construct($message, $status);
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
