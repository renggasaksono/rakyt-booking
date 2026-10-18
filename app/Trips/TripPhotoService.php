<?php

namespace App\Trips;

use App\Media\MediaPath;
use App\Media\PublicImage;
use App\Models\Trip;
use App\Models\TripPhoto;
use App\Trips\Jobs\GenerateTripPhotoVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Trip photos (F-003 AC-7, ADR-0015): EXIF stripped before storage, at most
 * 10 per trip, lowest position = cover, files removed with the row.
 */
final class TripPhotoService
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @param  UploadedFile  $file  already validated with UploadRules::tripPhoto()
     */
    public function add(Trip $trip, UploadedFile $file): TripPhoto
    {
        $max = (int) config('rakyt.media.trip_photo.max_per_trip');

        // Cheap early refusal; the locked count below is the real check.
        if ($trip->photos()->count() >= $max) {
            throw $this->limitReached($max);
        }

        $extension = self::EXTENSIONS[$file->getMimeType()] ?? 'jpg';
        $path = MediaPath::for($trip->organization, 'trips/'.$trip->public_id, $extension);
        $disk = Storage::disk(config('rakyt.media.public_disk'));
        $disk->put($path, PublicImage::sanitize($file));

        try {
            $photo = DB::transaction(function () use ($trip, $path, $max) {
                Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

                $count = $trip->photos()->count();

                if ($count >= $max) {
                    throw $this->limitReached($max);
                }

                return TripPhoto::create([
                    'trip_id' => $trip->getKey(),
                    'path' => $path,
                    'position' => ((int) $trip->photos()->max('position')) + 1,
                ]);
            });
        } catch (\Throwable $e) {
            $disk->delete($path);

            throw $e;
        }

        GenerateTripPhotoVariants::dispatch($trip->organization_id, $photo->getKey());

        return $photo;
    }

    /**
     * @param  list<string>  $publicIds  every photo of the trip, in the new order
     */
    public function reorder(Trip $trip, array $publicIds): void
    {
        DB::transaction(function () use ($trip, $publicIds) {
            Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            /** @var Collection<string, TripPhoto> $photos */
            $photos = $trip->photos()->get()->keyBy('public_id');

            if (count($publicIds) !== $photos->count() || array_diff($publicIds, $photos->keys()->all()) !== []) {
                throw ValidationException::withMessages(['photos' => __('trips.errors.photos_mismatch')]);
            }

            foreach (array_values($publicIds) as $index => $publicId) {
                $photo = $photos[$publicId];
                $photo->position = $index + 1;
                $photo->save();
            }
        });
    }

    public function remove(TripPhoto $photo): void
    {
        $paths = DB::transaction(function () use ($photo) {
            $trip = Trip::query()->whereKey($photo->trip_id)->lockForUpdate()->firstOrFail();

            // A published trip keeps its cover photo (checklist item).
            if ($trip->isPublished() && $trip->photos()->count() <= 1) {
                throw ValidationException::withMessages(['photo' => __('trips.errors.photo_last_published')]);
            }

            $paths = $photo->allPaths();
            $photo->delete();

            return $paths;
        });

        Storage::disk(config('rakyt.media.public_disk'))->delete($paths);
    }

    private function limitReached(int $max): ValidationException
    {
        return ValidationException::withMessages(['photo' => __('trips.errors.photo_limit', ['max' => $max])]);
    }
}
