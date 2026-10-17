<?php

namespace App\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class OrganizationIdImmutable extends LogicException
{
    public static function forModel(Model $model): self
    {
        return new self(sprintf('organization_id of %s cannot change after create.', $model::class));
    }
}
