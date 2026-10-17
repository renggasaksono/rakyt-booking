<?php

namespace App\Logging;

use App\Security\PhoneNumber;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Keeps secrets and personal data out of logs (F-009 A24, Data & rules "Logging & PII"):
 * passwords, reset/session/access tokens and signed-URL signatures are
 * replaced; Indonesian phone numbers are masked.
 */
class RedactSensitiveData implements ProcessorInterface
{
    public const REDACTED = '[redacted]';

    private const SECRET_KEY_PATTERN = '/pass(word)?|token|secret|signature|authorization|cookie|session|api[_-]?key/i';

    private const PHONE_PATTERN = '/(?<!\d)(?:\+62|62|0)8\d{7,11}(?!\d)/';

    private const URL_SECRET_PARAM_PATTERN = '/([?&](?:signature|token|access_token)=)[^&\s"\']+/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrubArray($record->context),
            extra: $this->scrubArray($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function scrubArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            $values[$key] = match (true) {
                is_array($value) => $this->scrubArray($value),
                is_string($value) => $this->scrubString($value),
                default => $value,
            };
        }

        return $values;
    }

    private function scrubString(string $value): string
    {
        $value = preg_replace(self::URL_SECRET_PARAM_PATTERN, '$1'.self::REDACTED, $value) ?? $value;

        return preg_replace_callback(
            self::PHONE_PATTERN,
            fn (array $match) => PhoneNumber::mask((string) PhoneNumber::normalize($match[0])),
            $value,
        ) ?? $value;
    }
}
