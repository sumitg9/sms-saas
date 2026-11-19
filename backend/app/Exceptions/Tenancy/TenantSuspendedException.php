<?php

namespace App\Exceptions\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantSuspendedException extends HttpException
{
    public function __construct(string $message = 'This school tenant account has been suspended. Please contact platform support.', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}

