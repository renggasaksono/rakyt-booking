<?php

/*
| Processes one photo the way an upload does, inside the booted app, so the
| framework's own memory counts. Run with memory_limit set to the configured
| photo budget (App\Media\PhotoBudget); a fatal "memory exhausted" exits non-zero.
|
| php sanitize_photo.php <file>      → prints "<width>x<height> peak=<MB>"
| php sanitize_photo.php <file> raw  → decode only; prints peak bytes per pixel
*/

use App\Media\PhotoBudget;
use App\Media\PublicImage;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $file] = $argv;
$mode = $argv[2] ?? 'sanitize';

if ($mode === 'raw') {
    [$width, $height] = getimagesize($file);
    $before = memory_get_usage();
    $image = imagecreatefromstring(file_get_contents($file));
    printf("%.3f\n", (memory_get_peak_usage() - $before - filesize($file)) / ($width * $height));

    exit(0);
}

$encoded = PublicImage::sanitize($file, maxSide: PhotoBudget::storedMaxSide());
[$width, $height] = getimagesizefromstring($encoded);

printf("%dx%d peak=%.1f\n", $width, $height, memory_get_peak_usage() / 1048576);
