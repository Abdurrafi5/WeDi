<?php

namespace App\Exceptions;

use RuntimeException;

/** Error AI yang pesannya aman ditampilkan ke pengguna. */
class AiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502, public readonly string $errorCode = 'ai_error')
    {
        parent::__construct($message);
    }
}
