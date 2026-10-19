<?php

use App\Bookings\Jobs\ReleaseExpiredHolds;
use App\Trips\Jobs\CloseDeparturesPastDeadline;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(CloseDeparturesPastDeadline::NAME, function (CloseDeparturesPastDeadline $close) {
    $this->info('Closed '.$close().' departure(s).');
})->purpose('Close open departures whose booking deadline has passed (F-003 AC-37)');

// F-003 AC-37: every minute; never two runs at once (F-009 B4).
Schedule::command(CloseDeparturesPastDeadline::NAME)->everyMinute()->withoutOverlapping()->onOneServer();

Artisan::command(ReleaseExpiredHolds::NAME, function (ReleaseExpiredHolds $release) {
    $this->info('Released '.$release().' expired seat hold(s).');
})->purpose('Release unpaid seat holds past their 2 hours (F-005 AC-24)');

// F-005 AC-24: every minute; never two runs at once (F-009 B4).
Schedule::command(ReleaseExpiredHolds::NAME)->everyMinute()->withoutOverlapping()->onOneServer();
