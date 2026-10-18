<?php

namespace App\Security;

/**
 * Request fields that are never flashed back into the session as old input
 * (sessions are stored unencrypted in the database).
 */
final class SensitiveInput
{
    public const FIELDS = [
        '_token',
        'current_password',
        'password',
        'password_confirmation',
        'token',
    ];
}
