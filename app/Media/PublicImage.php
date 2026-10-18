<?php

namespace App\Media;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Re-encodes an uploaded public image so EXIF metadata (incl. GPS) is gone
 * before it is stored (F-009 A22, ADR-0015). Orientation is applied first so
 * photos taken sideways stay upright.
 */
final class PublicImage
{
    public static function sanitize(UploadedFile|string $source, int $quality = 85): string
    {
        $manager = new ImageManager(Driver::class, autoOrientation: true, strip: true);
        $path = $source instanceof UploadedFile ? $source->getRealPath() : $source;

        $image = $manager->decodePath($path);

        return (string) $image->encodeUsingMediaType($image->origin()->mediaType(), quality: $quality);
    }
}
