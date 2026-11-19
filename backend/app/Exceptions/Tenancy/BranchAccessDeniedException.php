<?php

namespace App\Exceptions\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class BranchAccessDeniedException extends HttpException
{
    public function __construct(string $message = 'You do not have permission to access the requested branch.', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}

