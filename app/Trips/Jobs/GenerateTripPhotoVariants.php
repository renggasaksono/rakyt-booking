<?php

namespace App\Trips\Jobs;

use App\Media\PhotoBudget;
use App\Models\TripPhoto;
use App\Tenancy\ScopedToOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Resized variants of a trip photo for F-002's 3G target (ADR-0015). Works
 * from the stored original, which already has no EXIF; variants are encoded
 * without metadata too. Ids only, loaded under the organization (F-009 R17).
 */
class GenerateTripPhotoVariants implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $organizationId,
        public int $photoId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ScopedToOrganization($this->organizationId)];
    }

    public function handle(): void
    {
        $photo = TripPhoto::query()->find($this->photoId);

        if ($photo === null) {
            return;
        }

        $disk = Storage::disk(config('rakyt.media.public_disk'));

        if (! $disk->exists($photo->path)) {
            return;
        }

        // The stored original is already upright and at most 2560 px (ADR-0022 §5).
        PhotoBudget::ensureMemoryLimit();
        $manager = new ImageManager(Driver::class, autoOrientation: false, strip: true);
        $image = $manager->decodeBinary($disk->get($photo->path));
        $mediaType = $image->origin()->mediaType();
        $variants = [];

        $widths = config('rakyt.media.trip_photo.variant_widths');
        rsort($widths);

        // Largest first, each scaled down from the previous one.
        foreach ($widths as $width) {
            // Never upscale: smaller originals are served as they are.
            if ($image->width() <= $width) {
                continue;
            }

            $image->scaleDown(width: $width);
            $path = Str::replaceLast('.', "-w{$width}.", $photo->path);
            $disk->put($path, (string) $image->encodeUsingMediaType($mediaType, quality: 80));
            $variants[(string) $width] = $path;
        }

        ksort($variants);

        $photo->variants = $variants;
        $photo->save();
    }
}
