<?php

namespace App\Media;

/**
 * Server-side upload validation by detected MIME type (file contents, not the
 * extension) and size (F-009 A22, ADR-0015, F-006).
 */
final class UploadRules
{
    /**
     * @return list<string>
     */
    public static function tripPhoto(): array
    {
        return self::rules('trip_photo');
    }

    /**
     * @return list<string>
     */
    public static function paymentEvidence(): array
    {
        return self::rules('payment_evidence');
    }

    /**
     * @return list<string>
     */
    private static function rules(string $kind): array
    {
        $config = config("rakyt.media.{$kind}");

        return [
            'required',
            'file',
            'mimetypes:'.implode(',', $config['mimetypes']),
            'max:'.$config['max_kb'],
        ];
    }
}
