<?php

namespace App\Tenancy\Exceptions;

use LogicException;

class BulkWriteBypassesTenancy extends LogicException
{
    public static function for(string $method): self
    {
        return new self(sprintf(
            '%s() skips model events and the organization fill. Use create()/save(), or an allowlisted system query (config/rakyt.php).',
            $method,
        ));
    }
}
