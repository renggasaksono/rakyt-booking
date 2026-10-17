<?php

namespace App\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class MissingOrganizationContext extends LogicException
{
    public static function forModel(Model $model): self
    {
        return new self(sprintf(
            'Cannot create %s without a current organization or an explicit organization_id.',
            $model::class,
        ));
    }
}
