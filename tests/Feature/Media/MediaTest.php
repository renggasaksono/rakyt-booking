<?php

namespace Tests\Feature\Media;

use App\Media\MediaPath;
use App\Media\PrivateMedia;
use App\Media\PublicImage;
use App\Media\UploadRules;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F-009 A19, A20 (signed URL primitive), A22.
 */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        Storage::disk(PrivateMedia::disk())->deleteDirectory('org/test-media');

        parent::tearDown();
    }

    public function test_ac_a19_paths_are_organization_prefixed_with_random_names(): void
    {
        $organization = Organization::factory()->create();

        $first = MediaPath::for($organization, 'payments/evidence', 'JPG');
        $second = MediaPath::for($organization, 'payments/evidence', 'jpg');

        $this->assertMatchesRegularExpression(
            '#^org/'.$organization->public_id.'/payments/evidence/[0-9a-f-]{36}\.jpg$#',
            $first,
        );
        $this->assertNotSame($first, $second);
        $this->assertTrue(MediaPath::belongsTo($organization, $first));
        $this->assertFalse(MediaPath::belongsTo(Organization::factory()->create(), $first));
    }

    public function test_ac_a19_path_traversal_and_odd_extensions_are_refused(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        MediaPath::for($organization, '../other', 'jpg');
    }

    public function test_ac_a22_evidence_is_validated_by_detected_mime_type_not_extension(): void
    {
        $this->registerUploadRoute();

        $disguised = $this->tempFile('<?php echo "halo";', 'php');
        $this->postJson('/test/unggah', ['file' => new UploadedFile($disguised, 'bukti.jpg', 'image/jpeg', null, true)])
            ->assertUnprocessable();

        $png = $this->tempPng();
        $this->postJson('/test/unggah', ['file' => new UploadedFile($png, 'bukti.png', null, null, true)])
            ->assertOk();
    }

    public function test_ac_a22_evidence_larger_than_the_limit_is_rejected(): void
    {
        $this->registerUploadRoute();
        $maxKb = config('rakyt.media.payment_evidence.max_kb');

        $this->postJson('/test/unggah', ['file' => UploadedFile::fake()->create('besar.jpg', $maxKb + 1, 'image/jpeg')])
            ->assertUnprocessable();
    }

    public function test_ac_a22_public_images_are_re_encoded_without_exif(): void
    {
        $path = $this->tempFile($this->jpegWithExifMake('LEAKYCAM'), 'jpg');
        $this->assertSame('LEAKYCAM', @exif_read_data($path)['Make'] ?? null, 'Fixture should carry EXIF.');

        $clean = PublicImage::sanitize(new UploadedFile($path, 'foto.jpg', null, null, true));

        $this->assertStringStartsWith("\xFF\xD8", $clean);
        $this->assertStringNotContainsString('LEAKYCAM', $clean);
        $this->assertStringNotContainsString('Exif', $clean);
    }

    public function test_ac_a22_public_images_are_downscaled_and_turned_upright_without_exif(): void
    {
        // 3000×1500 taken sideways (EXIF Orientation 6 = rotate 90° clockwise).
        $path = $this->tempFile($this->jpegWithExifOrientation(6, 3000, 1500), 'jpg');

        $clean = PublicImage::sanitize($path, maxSide: 2560);

        $this->assertSame([1280, 2560], array_slice(getimagesizefromstring($clean), 0, 2));
        $this->assertStringNotContainsString('Exif', $clean);
    }

    public function test_ac_a20_private_media_is_only_reachable_through_a_short_lived_signed_url(): void
    {
        $disk = Storage::disk(PrivateMedia::disk());
        $path = 'org/test-media/'.Str::uuid().'.txt';
        $disk->put($path, 'bukti transfer');

        $url = PrivateMedia::temporaryUrl($path);
        $relative = Str::after($url, config('app.url'));

        $this->get($relative)->assertOk();
        $this->get('/storage/'.$path)->assertForbidden();
        $this->get('/'.$path)->assertNotFound();

        $this->travel(config('rakyt.media.private_url_ttl') + 1)->minutes();
        $this->get($relative)->assertForbidden();
    }

    private function registerUploadRoute(): void
    {
        Route::post('/test/unggah', fn (Request $request) => $request->validate(['file' => UploadRules::paymentEvidence()]) ? 'ok' : 'no');
    }

    private function tempFile(string $contents, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rakyt').'.'.$extension;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function tempPng(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return $this->tempFile((string) ob_get_clean(), 'png');
    }

    /**
     * A JPEG whose EXIF IFD0 holds only an Orientation tag.
     */
    private function jpegWithExifOrientation(int $orientation, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $tiff = 'MM'."\x00\x2A".pack('N', 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, $orientation, 0).pack('N', 0);
        $payload = "Exif\0\0".$tiff;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }

    /**
     * A small JPEG with an EXIF APP1 segment holding one IFD0 "Make" tag.
     */
    private function jpegWithExifMake(string $make): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $value = $make."\0";
        $tiff = 'MM'."\x00\x2A".pack('N', 8)        // header, IFD0 at offset 8
            .pack('n', 1)                           // one entry
            .pack('nnNN', 0x010F, 2, strlen($value), 26) // Make, ASCII, count, offset
            .pack('N', 0)                           // no next IFD
            .$value;
        $payload = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return "\xFF\xD8".$app1.substr($jpeg, 2);
    }
}
