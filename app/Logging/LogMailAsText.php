<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Tap for the `mail` channel (F-001 A13): keep only the headers and the
 * plain-text part of a logged email. The HTML part is quoted-printable
 * (`signature=3D…`), which URL redaction cannot read reliably.
 */
class LogMailAsText
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $message = preg_replace(
                '/Content-Type: text\/html.*?(?=\R--[^\r\n]+--)/s',
                '[html part not logged]',
                $record->message,
            );

            return $record->with(message: $message ?? $record->message);
        });
    }
}
