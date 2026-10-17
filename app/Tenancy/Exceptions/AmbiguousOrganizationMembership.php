<?php

namespace App\Tenancy\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * P0 allows one membership per user; with more, the app refuses to guess (F-009 Data & rules).
 */
class AmbiguousOrganizationMembership extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'User belongs to more than one organization.');
    }
}
