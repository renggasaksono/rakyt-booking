<?php

namespace App\Logging;

use App\Security\PhoneNumber;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Enumerable;
use JsonSerializable;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use PDOException;
use Throwable;

/**
 * Keeps secrets and personal data out of logs (F-009 A24, Data & rules "Logging & PII"):
 * passwords, reset/session/access tokens and signed-URL signatures are
 * replaced; names, emails and bank details are withheld by key; Indonesian
 * phone numbers are masked; Eloquent models are logged as class + key only;
 * other objects and exceptions are flattened and scrubbed; stack traces carry
 * no argument values; SQL errors never carry their bound values.
 */
class RedactSensitiveData implements ProcessorInterface
{
    public const REDACTED = '[redacted]';

    private const SECRET_KEY_PATTERN = '/password|passwd|passphrase|token|secret|signature|authorization|cookie|session|api[_-]?key/i';

    public const WITHHELD = '[pii]';

    /** Personal data that is never logged, whatever the value looks like. */
    private const PII_KEY_PATTERN = '/(^|_)(name|email|account_number|account_holder|ktp|nik|address)$/i';

    private const PHONE_PATTERN = '/(?<![\d+])(?:\+?62|0)[\s-]?8(?:[\s-]?\d){7,11}(?!\d)/';

    /**
     * `cari` is the seller's booking search — a customer name or phone (F-007 AC-37); `text` is a
     * prefilled WhatsApp message, which carries names and the customer link with its token (F-007 AC-25).
     * The `x-amz-*` parameters are S3 presigned-URL credentials (F-009 R19, F-006 AC-30).
     */
    private const URL_SECRET_PARAM_PATTERN = '/([?&](?:signature|token|access_token|cari|text|x-amz-signature|x-amz-security-token|x-amz-credential)=)[^&\s"\']+/i';

    /**
     * Private media paths — payment evidence and QRIS images (F-009 R19, F-006
     * AC-30, AC-19). Never belongs in a log, whatever carries it. Slashes may be
     * JSON-escaped (\/). Public trip photos (org/<id>/trips/…) are not private.
     */
    private const PRIVATE_MEDIA_PATH_PATTERN = '~org(?:\\\\?/)[\w.-]+(?:\\\\?/)(?:evidence|payments)(?:\\\\?/)[^\s"\'\\\\]+~i';

    /** The customer booking token is a path segment: /<slug>/pesanan/<token> (F-005 AC-40, F-009 R20, ADR-0027 §5). Also JSON-escaped. */
    private const BOOKING_TOKEN_PATH_PATTERN = '~(\\\\?/pesanan\\\\?/)[^/?#\s"\'\\\\]+~';

    /** The same path URL-encoded, e.g. inside a wa.me `text` that was logged on its own (F-007 AC-24). */
    private const ENCODED_BOOKING_TOKEN_PATTERN = '~(%2Fpesanan%2F)[A-Za-z0-9]+~i';

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

            if (is_string($key) && preg_match(self::PII_KEY_PATTERN, $key) && $value !== null) {
                $values[$key] = self::WITHHELD;

                continue;
            }

            $values[$key] = $this->scrubValue($value);
        }

        return $values;
    }

    private function scrubValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Throwable => $this->scrubThrowable($value),
            // Attributes hold names, emails and bank details; the key is enough to find the row.
            $value instanceof Model => ['model' => $value::class, 'key' => $value->getKey()],
            $value instanceof Enumerable => $this->scrubArray($value->all()),
            $value instanceof Arrayable => $this->scrubArray($value->toArray()),
            $value instanceof JsonSerializable => $this->scrubValue($value->jsonSerialize()),
            // Unknown objects would be serialised field by field, unscrubbed.
            is_object($value) => '[object '.$value::class.']',
            is_array($value) => $this->scrubArray($value),
            is_string($value) => $this->scrubString($value),
            default => $value,
        };
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
            'trace' => $this->traceWithoutArguments($e),
            'previous' => $e->getPrevious() ? $this->scrubThrowable($e->getPrevious()) : null,
        ];
    }

    /**
     * Frames only: argument values (e.g. a password passed to Hash::check) never reach the log.
     *
     * @return list<string>
     */
    private function traceWithoutArguments(Throwable $e): array
    {
        return array_map(
            fn (array $frame) => sprintf(
                '%s:%s %s%s%s()',
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? '?',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            ),
            $e->getTrace(),
        );
    }

    private function safeMessage(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            // Placeholders only: bindings hold customer names, phones and amounts.
            return sprintf('SQLSTATE[%s] on connection [%s]: %s', $e->getCode(), $e->getConnectionName(), $e->getSql());
        }

        if ($e instanceof PDOException) {
            // PostgreSQL's DETAIL line echoes row values: "Failing row contains (…)", "Key (email)=(…)".
            return (string) strtok($e->getMessage(), "\n");
        }

        return $e->getMessage();
    }

    private function scrubString(string $value): string
    {
        $value = preg_replace(self::URL_SECRET_PARAM_PATTERN, '$1'.self::REDACTED, $value) ?? $value;
        $value = preg_replace(self::PRIVATE_MEDIA_PATH_PATTERN, self::REDACTED, $value) ?? $value;
        $value = preg_replace(self::BOOKING_TOKEN_PATH_PATTERN, '$1'.self::REDACTED, $value) ?? $value;
        $value = preg_replace(self::ENCODED_BOOKING_TOKEN_PATTERN, '$1'.self::REDACTED, $value) ?? $value;

        return preg_replace_callback(
            self::PHONE_PATTERN,
            fn (array $match) => PhoneNumber::mask((string) PhoneNumber::normalize($match[0])),
            $value,
        ) ?? $value;
    }
}
