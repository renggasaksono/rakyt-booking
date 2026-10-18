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
        return ['bail', ...self::rules('trip_photo'), self::pixelCap()];
    }

    /**
     * @return list<string>
     */
    public static function paymentEvidence(): array
    {
        return self::rules('payment_evidence');
    }

    /**
     * Public photos are decoded by GD (EXIF stripping, downscaling, variants).
     * Read the dimensions from the header first and refuse images over the
     * cap derived from the memory budget (F-009 R3, PhotoBudget).
     */
    private static function pixelCap(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                return;
            }

            $size = @getimagesize($value->getRealPath());

            if ($size === false) {
                $fail(__('trips.errors.photo_type'));

                return;
            }

            [$width, $height] = $size;

            if ($width > PhotoBudget::maxSide() || $height > PhotoBudget::maxSide()) {
                $fail(__('trips.errors.photo_dimensions', ['px' => PhotoBudget::maxSide()]));
            } elseif ($width * $height > PhotoBudget::maxPixels()) {
                $fail(__('trips.errors.photo_megapixels', ['mp' => PhotoBudget::maxMegapixels()]));
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
