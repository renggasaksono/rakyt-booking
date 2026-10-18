<?php

namespace App\Media;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Re-encodes an uploaded public image so EXIF metadata (incl. GPS) is gone
 * before it is stored (F-009 A22, ADR-0015).
 *
 * With $maxSide the image is first scaled down, then turned upright from its
 * EXIF orientation. Orienting the small copy instead of the decoded original
 * avoids a second full-size image in memory (ADR-0022 §5). PHP's memory limit
 * is raised to the photo budget; callers check the size against PhotoBudget
 * (UploadRules::tripPhoto) before calling this.
 */
final class PublicImage
{
    public static function sanitize(UploadedFile|string $source, int $quality = 85, ?int $maxSide = null): string
    {
        PhotoBudget::ensureMemoryLimit();

        $manager = new ImageManager(Driver::class, autoOrientation: false, strip: true);
        $path = $source instanceof UploadedFile ? $source->getRealPath() : $source;

        $image = $manager->decodePath($path);

        if ($maxSide !== null) {
            $image->scaleDown($maxSide, $maxSide);
        }

        $image->orient();

        return (string) $image->encodeUsingMediaType($image->origin()->mediaType(), quality: $quality);
    }
}
