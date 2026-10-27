<?php

/*
| A customer uploading payment evidence from a second database connection,
| through the real BookingService (F-006; F-014 AC-6). Not part of the
| application.
|
| php upload.php <booking id> <organization id> <hold ms>
|
| Prints "locked:1" as soon as the upload transaction holds its shared lock on
| the organization row (BookingService's own query, seen through a query
| listener), keeps the transaction open for <hold ms>, commits and prints
| "uploaded:1". A refused upload prints "uploaded:0".
*/

use App\Bookings\BookingService;
use App\Models\Booking;
use App\Models\Organization;
use App\Tenancy\CurrentOrganization;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $bookingId, $organizationId, $holdMs] = $argv;

$say = function (string $line) {
    fwrite(STDOUT, $line."\n");
    fflush(STDOUT);
};

Storage::fake(config('rakyt.media.private_disk'));

// The listener runs after the query, inside the transaction: the lock is held.
DB::listen(function (QueryExecuted $query) use ($holdMs, $say) {
    if (str_contains(strtolower($query->sql), 'for share')) {
        $say('locked:1');
        usleep((int) $holdMs * 1000);
    }
});

$organization = Organization::query()->findOrFail((int) $organizationId);

$record = app(CurrentOrganization::class)->runAs($organization, fn () => app(BookingService::class)->uploadEvidence(
    Booking::query()->findOrFail((int) $bookingId),
    UploadedFile::fake()->image('bukti.jpg'),
));

$say('uploaded:'.($record === null ? '0' : '1'));
