<?php

namespace App\Media;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Server-side upload validation by detected MIME type (file contents, not the
 * extension) and size (F-009 A22, ADR-0015, F-006).
 */
final class UploadRules
{
    /**
     * @return list<string|Closure>
     */
    public static function tripPhoto(): array
    {
        return ['bail', ...self::rules('trip_photo'), self::pixelCap('trip_photo')];
    }

    /**
     * @return list<string>
     */
    public static function paymentEvidence(): array
    {
        return self::rules('payment_evidence');
    }

    /**
     * Public photos are decoded by GD (EXIF stripping, variants), which needs
     * about 4 bytes per pixel. Read the dimensions from the header first and
     * refuse images that would exhaust memory (F-009 R3).
     */
    private static function pixelCap(string $kind): Closure
    {
        $config = config("rakyt.media.{$kind}");

        return function (string $attribute, mixed $value, Closure $fail) use ($config) {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                return;
            }

            $size = @getimagesize($value->getRealPath());

            if ($size === false) {
                $fail(__('trips.errors.photo_type'));

                return;
            }

            [$width, $height] = $size;

            if ($width > $config['max_side_px'] || $height > $config['max_side_px'] || $width * $height > $config['max_megapixels'] * 1_000_000) {
                $fail(__('trips.errors.photo_dimensions', ['px' => $config['max_side_px']]));
            }
        };
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
