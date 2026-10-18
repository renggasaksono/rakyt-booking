<?php

namespace App\Tenancy\Exceptions;

use LogicException;

class BulkWriteBypassesTenancy extends LogicException
{
    public static function for(string $method): self
    {
        return new self(sprintf(
            '%s cannot be kept inside the current organization. Use create(), save() or updateOrCreate(), or an allowlisted system query (config/rakyt.php).',
            $method,
        ));
    }
}
