<?php

/*
| Simulates a customer booking seats from a second database connection, the way
| F-005 will (ADR-0006: atomic conditional update under the row lock). Used by
| the F-003 AC-39 concurrency test; not part of the application.
|
| php take_seats.php <departure id> <organization id> <seats> <hold ms>
|
| Prints "started", then "locked:<rows>" once its UPDATE holds the row lock,
| keeps the transaction open for <hold ms>, commits and prints "booked:<rows>".
*/

use App\Models\Organization;
use App\Models\TripDeparture;
use App\Tenancy\CurrentOrganization;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $departureId, $organizationId, $seats, $holdMs] = $argv;

$say = function (string $line) {
    fwrite(STDOUT, $line."\n");
    fflush(STDOUT);
};

$organization = Organization::query()->findOrFail((int) $organizationId);

$booked = app(CurrentOrganization::class)->runAs($organization, fn () => DB::transaction(function () use ($departureId, $seats, $holdMs, $say) {
    $say('started');

    $rows = TripDeparture::query()
        ->whereKey((int) $departureId)
        ->whereRaw('seats_taken + ? <= capacity', [(int) $seats])
        ->increment('seats_taken', (int) $seats);

    $say('locked:'.$rows);
    usleep((int) $holdMs * 1000);

    return $rows;
}));

$say('booked:'.$booked);
