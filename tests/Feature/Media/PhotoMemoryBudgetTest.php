<?php

namespace Tests\Feature\Media;

use App\Media\PhotoBudget;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * F-009 R3 / F-003 AC-7 (ADR-0022 §5): the megapixel cap is derived from an
 * explicit memory budget, and a photo of exactly the cap is processed within
 * that budget. Anything larger is refused before GD decodes it.
 */
class PhotoMemoryBudgetTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_r3_the_cap_times_bytes_per_pixel_plus_headroom_fits_the_limit(): void
    {
        $this->assertSame(320 * 1048576, PhotoBudget::memoryLimitBytes());
        $this->assertLessThanOrEqual(
            PhotoBudget::memoryLimitBytes(),
            PhotoBudget::maxPixels() * PhotoBudget::bytesPerPixel() + PhotoBudget::headroomBytes(),
        );
        // A 12 MP phone photo (4000×3000) and a 24 MP one (6000×4000) are accepted.
        $this->assertGreaterThanOrEqual(24_000_000, PhotoBudget::maxPixels());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formats(): array
    {
        return ['png' => ['png'], 'jpeg' => ['jpg'], 'jpeg taken sideways' => ['jpg-rotated'], 'webp' => ['webp']];
    }

    #[DataProvider('formats')]
    public function test_r3_the_configured_bytes_per_pixel_covers_gd_decoding(string $format): void
    {
        $file = $this->image(2000, 1500, $format);

        $measured = (float) trim($this->php(['-d', 'memory_limit=-1'], [$file, 'raw'])->getOutput());

        $this->assertLessThanOrEqual(PhotoBudget::bytesPerPixel(), $measured, "GD needs {$measured} bytes per pixel for {$format}.");
    }

    #[DataProvider('formats')]
    public function test_r3_a_photo_at_the_cap_is_processed_within_the_memory_budget_and_stored_at_most_2560_px(string $format): void
    {
        $width = (int) floor(sqrt(PhotoBudget::maxPixels() * 4 / 3));
        $height = intdiv(PhotoBudget::maxPixels(), $width);
        $file = $this->image($width, $height, $format);

        $process = $this->php(['-d', 'memory_limit='.PhotoBudget::memoryLimitBytes()], [$file]);

        $this->assertTrue($process->isSuccessful(), "{$width}×{$height} {$format} did not fit: ".$process->getErrorOutput().$process->getOutput());
        $expected = $format === 'jpg-rotated' ? '/^\d+x2560 /' : '/^2560x\d+ /';
        $this->assertMatchesRegularExpression($expected, $process->getOutput());
    }

    public function test_r3_the_upload_raises_a_lower_memory_limit_to_the_budget_but_never_lowers_it(): void
    {
        $original = ini_get('memory_limit');

        try {
            ini_set('memory_limit', '128M');
            PhotoBudget::ensureMemoryLimit();
            $this->assertSame((string) PhotoBudget::memoryLimitBytes(), ini_get('memory_limit'));

            ini_set('memory_limit', '1G');
            PhotoBudget::ensureMemoryLimit();
            $this->assertSame('1G', ini_get('memory_limit'));

            ini_set('memory_limit', '-1');
            PhotoBudget::ensureMemoryLimit();
            $this->assertSame('-1', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $original);
        }
    }

    private function image(int $width, int $height, string $format): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rakyt-budget').'.'.($format === 'jpg-rotated' ? 'jpg' : $format);
        $this->files[] = $file;

        $make = new Process([PHP_BINARY, '-d', 'memory_limit=-1', base_path('tests/Support/Media/make_image.php'), (string) $width, (string) $height, $format, $file]);
        $make->setTimeout(120)->mustRun();

        return $file;
    }

    /**
     * @param  list<string>  $ini
     * @param  list<string>  $arguments
     */
    private function php(array $ini, array $arguments): Process
    {
        $process = new Process(
            [PHP_BINARY, ...$ini, base_path('tests/Support/Media/sanitize_photo.php'), ...$arguments],
            base_path(),
            ['APP_ENV' => 'testing', 'LOG_CHANNEL' => 'null', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'],
            timeout: 120,
        );
        $process->run();

        return $process;
    }
}
