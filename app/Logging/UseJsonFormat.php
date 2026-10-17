<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Tap for the `json` log channel: one JSON object per line, PII redacted.
 * Request id, user id and organization id arrive through Laravel's Context
 * (AssignRequestId, ResolveCurrentOrganization).
 */
class UseJsonFormat
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(includeStacktraces: true));
        }

        $logger->pushProcessor(new RedactSensitiveData);
    }
}
