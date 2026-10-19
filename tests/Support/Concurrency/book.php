<?php

/*
| A customer booking seats from a second database connection, through the real
| BookingService (F-005 AC-19–AC-22; F-003 AC-39). Not part of the application.
|
| php book.php <departure id> <organization id> <participants> <unit price> <hold ms> [submission key]
|
| Prints "started", then "locked:1" once the booking transaction holds its
| locks (organization, departure), keeps the transaction open for <hold ms>,
| commits and prints "booked:1 <reference>". A refused booking prints
| "booked:0 <field>: <message>".
*/

use App\Bookings\BookingService;
use App\Models\Organization;
use App\Models\TripDeparture;
use App\Tenancy\CurrentOrganization;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $departureId, $organizationId, $participants, $price, $holdMs] = $argv;
$submissionKey = $argv[6] ?? (string) Str::uuid();

$say = function (string $line) {
    fwrite(STDOUT, $line."\n");
    fflush(STDOUT);
};

$organization = Organization::query()->findOrFail((int) $organizationId);

try {
    $booking = app(CurrentOrganization::class)->runAs($organization, fn () => DB::transaction(function () use ($departureId, $participants, $price, $holdMs, $submissionKey, $say) {
        $say('started');

        $booking = app(BookingService::class)->create(TripDeparture::query()->findOrFail((int) $departureId), [
            'name' => 'Pelanggan Kedua',
            'phone' => '+6281200000002',
            'participants' => (int) $participants,
            'notes' => null,
            'submission_key' => $submissionKey,
            'price' => (int) $price,
        ]);

        $say('locked:1');
        usleep((int) $holdMs * 1000);

        return $booking;
    }));

    $say('booked:1 '.$booking->reference);
} catch (ValidationException $e) {
    $field = array_key_first($e->errors());
    $say('booked:0 '.$field.': '.$e->errors()[$field][0]);
}
