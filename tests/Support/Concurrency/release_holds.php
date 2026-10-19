<?php

/*
| The expiry job from a second database connection (F-005 AC-22, AC-24). Not
| part of the application.
|
| php release_holds.php <hold ms>
|
| Prints "started", then "locked:<n>" once the job has released <n> holds and
| still holds their locks, keeps the transaction open for <hold ms>, commits
| and prints "released:<n>".
*/

use App\Bookings\Jobs\ReleaseExpiredHolds;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $holdMs] = $argv;

$say = function (string $line) {
    fwrite(STDOUT, $line."\n");
    fflush(STDOUT);
};

$released = DB::transaction(function () use ($holdMs, $say) {
    $say('started');
    $released = app(ReleaseExpiredHolds::class)();
    $say('locked:'.$released);
    usleep((int) $holdMs * 1000);

    return $released;
});

$say('released:'.$released);
