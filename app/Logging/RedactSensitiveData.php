<?php

namespace App\Logging;

use App\Security\PhoneNumber;
use Illuminate\Database\QueryException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Keeps secrets and personal data out of logs (F-009 A24, Data & rules "Logging & PII"):
 * passwords, reset/session/access tokens and signed-URL signatures are
 * replaced; Indonesian phone numbers are masked; exceptions are flattened
 * and scrubbed, and SQL errors never carry their bound values.
 */
class RedactSensitiveData implements ProcessorInterface
{
    public const REDACTED = '[redacted]';

    private const SECRET_KEY_PATTERN = '/password|passwd|passphrase|token|secret|signature|authorization|cookie|session|api[_-]?key/i';

    private const PHONE_PATTERN = '/(?<![\d+])(?:\+?62|0)[\s-]?8(?:[\s-]?\d){7,11}(?!\d)/';

    private const URL_SECRET_PARAM_PATTERN = '/([?&](?:signature|token|access_token)=)[^&\s"\']+/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $record->message;
        $exception = $record->context['exception'] ?? null;

        // The handler logs $e->getMessage() as the message; a QueryException's message embeds the bindings.
        if ($exception instanceof Throwable && $message === $exception->getMessage()) {
            $message = $this->safeMessage($exception);
        }

        return $record->with(
            message: $this->scrubString($message),
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
                $value instanceof Throwable => $this->scrubThrowable($value),
                is_array($value) => $this->scrubArray($value),
                is_string($value) => $this->scrubString($value),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function scrubThrowable(Throwable $e): array
    {
        return [
            'class' => $e::class,
            'message' => $this->scrubString($this->safeMessage($e)),
            'code' => $e->getCode(),
            'file' => $e->getFile().':'.$e->getLine(),
            'trace' => $this->scrubString($e->getTraceAsString()),
            'previous' => $e->getPrevious() ? $this->scrubThrowable($e->getPrevious()) : null,
        ];
    }

    private function safeMessage(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            // Placeholders only: bindings hold customer names, phones and amounts.
            return sprintf('SQLSTATE[%s] on connection [%s]: %s', $e->getCode(), $e->getConnectionName(), $e->getSql());
        }

        return $e->getMessage();
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
