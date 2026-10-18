<?php

namespace App\Organizations;

use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Media\MediaPath;
use App\Media\PublicImage;
use App\Models\Organization;
use App\Trips\DepartureService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\ImageException;

/**
 * Pengaturan → Profil usaha (F-001 F1, F2, F4).
 */
final class OrganizationProfileService
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private AuditLog $audit,
        private DepartureService $departures,
    ) {}

    /**
     * @param  array<string, mixed>  $data  normalized and validated
     * @return int departures whose deadline was recomputed for a new time zone
     */
    public function update(Organization $organization, array $data): int
    {
        try {
            return DB::transaction(function () use ($organization, $data) {
                $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();

                // F2: the link is fixed after the first publish (Q3).
                if (array_key_exists('slug', $data) && $locked->slugLocked() && $data['slug'] !== $locked->slug) {
                    throw ValidationException::withMessages(['slug' => __('profile.slug_locked')]);
                }

                $locked->fill($data);
                $slugChanged = $locked->isDirty('slug');
                $timezoneChanged = $locked->isDirty('timezone');
                $locked->save();

                if ($slugChanged) {
                    $this->audit->record(AuditAction::OrganizationSlugChanged, $locked, ['slug' => $locked->getPrevious()['slug']], ['slug' => $locked->slug]);
                }

                $organization->setRawAttributes($locked->getAttributes(), true);

                // F4: deadlines that follow a preset move to the new zone.
                return $timezoneChanged ? $this->departures->rezoneDeadlines($locked) : 0;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => __('onboarding.organization.slug_taken')]);
        }
    }

    /**
     * Stored downscaled, EXIF stripped, under the organization's prefix (T4).
     *
     * @param  UploadedFile  $file  validated with UploadRules::logo()
     */
    public function replaceLogo(Organization $organization, UploadedFile $file): void
    {
        $disk = Storage::disk(config('rakyt.media.public_disk'));
        $path = MediaPath::for($organization, 'logo', self::EXTENSIONS[$file->getMimeType()] ?? 'jpg');

        try {
            $contents = PublicImage::sanitize($file, maxSide: (int) config('rakyt.media.logo.stored_side_px'));
        } catch (ImageException) {
            throw ValidationException::withMessages(['logo' => __('profile.logo_type')]);
        }

        $disk->put($path, $contents);

        $old = DB::transaction(function () use ($organization, $path) {
            $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $old = $locked->logo_path;
            $locked->logo_path = $path;
            $locked->save();
            $organization->setRawAttributes($locked->getAttributes(), true);

            return $old;
        });

        $this->deleteFile($organization, $old);
    }

    public function removeLogo(Organization $organization): void
    {
        $old = DB::transaction(function () use ($organization) {
            $locked = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $old = $locked->logo_path;
            $locked->logo_path = null;
            $locked->save();
            $organization->setRawAttributes($locked->getAttributes(), true);

            return $old;
        });

        $this->deleteFile($organization, $old);
    }

    private function deleteFile(Organization $organization, ?string $path): void
    {
        // Only ever delete inside the organization's own prefix (T4).
        if ($path !== null && MediaPath::belongsTo($organization, $path)) {
            Storage::disk(config('rakyt.media.public_disk'))->delete($path);
        }
    }
}
