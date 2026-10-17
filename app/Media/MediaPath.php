<?php

namespace App\Media;

use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * Storage paths for uploaded files: organization-prefixed, random file name,
 * never the uploader's original name (F-009 A19, ADR-0015).
 *
 *     MediaPath::for($organization, 'trips/'.$trip->public_id, 'jpg')
 *     // org/{organization public id}/trips/{trip public id}/{uuid}.jpg
 */
final class MediaPath
{
    public static function for(Organization $organization, string $folder, string $extension): string
    {
        $folder = trim($folder, '/');
        $extension = strtolower(ltrim($extension, '.'));

        if (str_contains($folder, '..') || ! preg_match('/^[a-z0-9]{2,5}$/', $extension)) {
            throw new \InvalidArgumentException('Invalid media folder or extension.');
        }

        return sprintf('org/%s/%s/%s.%s', $organization->public_id, $folder, Str::uuid(), $extension);
    }

    public static function belongsTo(Organization $organization, string $path): bool
    {
        return str_starts_with($path, 'org/'.$organization->public_id.'/');
    }
}
