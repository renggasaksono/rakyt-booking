<?php

/*
| The seller cancelling a booking from a second database connection, through
| the real BookingService (F-007 AC-31). Not part of the application.
|
| php cancel_booking.php <booking id> <organization id> <user id> <hold ms>
|
| Prints "started", then "locked:<0|1>" once the cancellation holds its locks
| (departure, booking) and has decided, keeps the transaction open for
| <hold ms>, commits and prints "cancelled:<0|1>".
*/

use App\Bookings\BookingService;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $bookingId, $organizationId, $userId, $holdMs] = $argv;

$say = function (string $line) {
    fwrite(STDOUT, $line."\n");
    fflush(STDOUT);
};

$organization = Organization::query()->findOrFail((int) $organizationId);
$seller = User::query()->findOrFail((int) $userId);

$cancelled = app(CurrentOrganization::class)->runAs($organization, fn () => DB::transaction(function () use ($bookingId, $seller, $holdMs, $say) {
    $say('started');
    $cancelled = app(BookingService::class)->cancelBySeller(Booking::query()->findOrFail((int) $bookingId), $seller);
    $say('locked:'.(int) $cancelled);
    usleep((int) $holdMs * 1000);

    return $cancelled;
}));

$say('cancelled:'.(int) $cancelled);
