<?php

namespace Tests\Feature\Trips;

use App\Models\TripPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-003 AC-7 (server side), ADR-0015, F-009 R3.
 */
class TripPhotoTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('rakyt.media.public_disk'));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function disk()
    {
        return Storage::disk(config('rakyt.media.public_disk'));
    }

    public function test_ac_7_a_photo_is_stored_under_the_organization_with_a_random_name_and_without_exif(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $response = $this->actingAsTenant($a)->post(route('trips.photos.store', $trip), [
            'photo' => $this->upload($this->jpegWithExif('LEAKYCAM', 2000, 1500), 'IMG_0001 rumah saya.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $photo = TripPhoto::acrossOrganizations()->sole();
        $this->assertSame($response->json('photo_id'), $photo->public_id);
        $this->assertMatchesRegularExpression(
            '#^org/'.$a->organization->public_id.'/trips/'.$trip->public_id.'/[0-9a-f-]{36}\.jpg$#',
            $photo->path,
        );

        $stored = $this->disk()->get($photo->path);
        $this->assertStringNotContainsString('LEAKYCAM', $stored);
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertSame(1, $response->json('checklist.items.0.done') ? 1 : 0);
    }

    public function test_ac_7_variants_are_generated_without_exif_and_never_upscaled(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), [
            'photo' => $this->upload($this->jpegWithExif('LEAKYCAM', 1000, 750), 'foto.jpg'),
        ])->assertCreated();

        $photo = TripPhoto::acrossOrganizations()->sole();
        $this->assertSame([400, 800], array_keys($photo->variants));

        foreach ($photo->variants as $width => $path) {
            $contents = $this->disk()->get($path);
            $this->assertStringNotContainsString('LEAKYCAM', $contents);
            $this->assertSame((int) $width, getimagesizefromstring($contents)[0]);
        }
    }

    public function test_ac_7_the_first_photo_is_the_cover_and_reordering_changes_it(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        foreach ([1, 2, 3] as $n) {
            $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => $this->upload($this->png(), "foto{$n}.png")])->assertCreated();
        }

        $ids = TripPhoto::acrossOrganizations()->orderBy('position')->pluck('public_id')->all();

        $this->actingAsTenant($a)->putJson(route('trips.photos.reorder', $trip), ['photos' => [$ids[2], $ids[0], $ids[1]]])
            ->assertOk()
            ->assertJsonPath('photos.0.id', $ids[2])
            ->assertJsonPath('photos.2.id', $ids[1]);

        $this->assertSame([$ids[2], $ids[0], $ids[1]], TripPhoto::acrossOrganizations()->orderBy('position')->pluck('public_id')->all());
    }

    public function test_ac_7_reorder_must_list_exactly_the_trips_photos(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $other = $this->tripFor($a);
        $mine = $this->photoFor($a, $trip, 1);
        $elsewhere = $this->photoFor($a, $other, 1);

        $this->actingAsTenant($a)->putJson(route('trips.photos.reorder', $trip), ['photos' => [$mine->public_id, $elsewhere->public_id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos' => __('trips.errors.photos_mismatch')]);
    }

    public function test_ac_7_a_photo_can_be_removed_with_its_files(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => $this->upload($this->jpegWithExif('X', 900, 600), 'a.jpg')]);
        $photo = TripPhoto::acrossOrganizations()->sole();
        $paths = $photo->allPaths();
        $this->assertCount(3, $paths);

        $this->actingAsTenant($a)->deleteJson(route('trips.photos.destroy', [$trip, $photo]))->assertOk()->assertJsonPath('photos', []);

        $this->assertDatabaseCount('trip_photos', 0);
        foreach ($paths as $path) {
            $this->disk()->assertMissing($path);
        }
    }

    public function test_ac_7_a_published_trip_keeps_its_last_photo(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a, [], published: true);
        $photo = $this->photoFor($a, $trip, 1);

        $this->actingAsTenant($a)->deleteJson(route('trips.photos.destroy', [$trip, $photo]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => __('trips.errors.photo_last_published')]);
    }

    public function test_ac_7_wrong_type_too_large_or_eleventh_photo_is_rejected_and_others_are_unaffected(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => __('trips.errors.photo_type')]);

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => UploadedFile::fake()->image('besar.jpg')->size(10 * 1024 + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => __('trips.errors.photo_size')]);

        foreach (range(1, 10) as $n) {
            $this->photoFor($a, $trip, $n);
        }

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => $this->upload($this->png(), 'ke11.png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => 'Maksimal 10 foto per trip.']);

        $this->assertSame(10, TripPhoto::acrossOrganizations()->count());
        $this->assertSame([], array_values(array_filter($this->disk()->allFiles(), fn ($f) => str_ends_with($f, '.png'))));
    }

    public function test_r3_images_with_too_many_pixels_are_refused_before_decoding(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        config(['rakyt.media.trip_photo.max_side_px' => 100]);

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => $this->upload($this->png(101, 10), 'lebar.png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => __('trips.errors.photo_dimensions', ['px' => 100])]);

        // Shrink the memory budget so a 40×30 image is over the derived cap.
        config([
            'rakyt.media.trip_photo.max_side_px' => 8000,
            'rakyt.media.trip_photo.memory_limit_mb' => 1,
            'rakyt.media.trip_photo.memory_headroom_mb' => 0.999,
        ]);
        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => $this->upload($this->png(40, 30), 'banyak.png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => __('trips.errors.photo_megapixels', ['mp' => 0])]);

        $this->assertDatabaseCount('trip_photos', 0);
    }

    public function test_ac_7_the_stored_original_is_at_most_2560_px_on_the_longest_side(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), [
            'photo' => $this->upload($this->jpegWithExif('LEAKYCAM', 3000, 2000), 'besar.jpg'),
        ])->assertCreated();

        $photo = TripPhoto::acrossOrganizations()->sole();
        $stored = $this->disk()->get($photo->path);
        $this->assertSame([2560, 1707], array_slice(getimagesizefromstring($stored), 0, 2));
        $this->assertStringNotContainsString('LEAKYCAM', $stored);
        $this->assertSame([400, 800, 1600], array_keys($photo->variants));
    }

    public function test_ac_7_a_file_gd_cannot_decode_is_rejected_with_the_bahasa_message(): void
    {
        [$a] = $this->createTenants();
        $trip = $this->tripFor($a);
        // A valid PNG header (so the MIME and size checks pass) with a broken body.
        $broken = substr($this->png(20, 20), 0, 40).str_repeat("\0", 200);

        $this->actingAsTenant($a)->postJson(route('trips.photos.store', $trip), ['photo' => $this->upload($broken, 'rusak.png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo' => __('trips.errors.photo_type')]);

        $this->assertDatabaseCount('trip_photos', 0);
        $this->assertSame([], $this->disk()->allFiles());
    }

    private function photoFor($tenant, $trip, int $position): TripPhoto
    {
        return $this->asTenant($tenant, fn () => TripPhoto::create([
            'trip_id' => $trip->id,
            'path' => "org/{$tenant->organization->public_id}/trips/{$trip->public_id}/{$position}.jpg",
            'position' => $position,
        ]));
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rakyt');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(int $width = 8, int $height = 8): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * A JPEG with an EXIF APP1 segment holding one IFD0 "Make" tag.
     */
    private function jpegWithExif(string $make, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $value = $make."\0";
        $tiff = 'MM'."\x00\x2A".pack('N', 8).pack('n', 1).pack('nnNN', 0x010F, 2, strlen($value), 26).pack('N', 0).$value;
        $payload = "Exif\0\0".$tiff;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }
}
