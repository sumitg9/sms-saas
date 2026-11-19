<?php

namespace App\Exceptions\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantAccessDeniedException extends HttpException
{
    public function __construct(string $message = 'You do not have active membership in the requested school organization.', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}

