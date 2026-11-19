<?php

namespace App\Exceptions\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantInactiveException extends HttpException
{
    public function __construct(string $message = 'This school tenant subscription is inactive or expired.', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}

