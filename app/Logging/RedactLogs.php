<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Tap for every log channel: secrets and personal data are scrubbed (F-009 A24).
 */
class RedactLogs
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new RedactSensitiveData);
    }
}
