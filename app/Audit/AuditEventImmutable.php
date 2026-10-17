<?php

namespace App\Audit;

use LogicException;

class AuditEventImmutable extends LogicException
{
    public static function make(): self
    {
        return new self('Audit events are append-only and cannot be updated or deleted.');
    }
}
