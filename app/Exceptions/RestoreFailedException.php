<?php

namespace App\Exceptions;

use Exception;

class RestoreFailedException extends Exception
{
    protected string $type;

    public function __construct(string $message = "", string $type = "unknown", int $code = 0, Exception $previous = null)
    {
        $this->type = $type;
        parent::__construct($message, $code, $previous);
    }

    public function getType(): string
    {
        return $this->type;
    }
}
