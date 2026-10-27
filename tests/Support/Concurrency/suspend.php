<?php

/*
| The platform admin suspends an organization from a second database
| connection, through the real OrganizationSuspension (F-014). Not part of the
| application.
|
| php suspend.php <organization id> <admin user id> <hold ms>
|
| Prints "locked:1" once the suspension holds the organization row lock (the
| status already changed, not committed yet), keeps the transaction open for
| <hold ms>, commits and prints "suspended:1".
*/

use App\Models\Organization;
use App\Models\User;
use App\Organizations\OrganizationSuspension;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $organizationId, $adminId, $holdMs] = $argv;

$say = function (string $line) {
    fwrite(STDOUT, $line."\n");
    fflush(STDOUT);
};

$organization = Organization::query()->findOrFail((int) $organizationId);
$admin = User::query()->findOrFail((int) $adminId);

$changed = DB::transaction(function () use ($organization, $admin, $holdMs, $say) {
    $changed = app(OrganizationSuspension::class)->suspend($organization, $admin, 'Laporan penipuan');

    $say('locked:1');
    usleep((int) $holdMs * 1000);

    return $changed;
});

$say('suspended:'.($changed ? '1' : '0'));
