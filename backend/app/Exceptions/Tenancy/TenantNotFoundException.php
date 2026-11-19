<?php

namespace App\Exceptions\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantNotFoundException extends HttpException
{
    public function __construct(string $message = 'School organization or tenant not found.', ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}

