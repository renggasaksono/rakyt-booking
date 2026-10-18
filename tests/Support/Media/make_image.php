<?php

/*
| Writes a blank image of the given size, for the photo memory-budget test.
| Run with memory_limit=-1: building a cap-sized image needs more than the budget.
|
| php make_image.php <width> <height> <png|jpg|jpg-rotated|webp> <out>
*/

[, $width, $height, $format, $out] = $argv;

$image = imagecreatetruecolor((int) $width, (int) $height);
imagefilledrectangle($image, 0, 0, 40, 40, 0xA1281E);

match ($format) {
    'png' => imagepng($image, $out, 1),
    'webp' => imagewebp($image, $out, 80),
    default => imagejpeg($image, $out, 85),
};

// "jpg-rotated": taken sideways (EXIF Orientation 6), so it is also turned upright.
if ($format === 'jpg-rotated') {
    $jpeg = file_get_contents($out);
    $tiff = 'MM'."\x00\x2A".pack('N', 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, 6, 0).pack('N', 0);
    $payload = "Exif\0\0".$tiff;
    file_put_contents($out, "\xFF\xD8\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2));
}
