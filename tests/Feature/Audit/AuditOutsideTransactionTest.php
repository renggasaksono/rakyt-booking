<?php

namespace Tests\Feature\Audit;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\Organization;
use LogicException;
use Tests\TestCase;

/**
 * F-009 A10: an audit write outside a transaction is a bug, so it throws.
 * No database trait: RefreshDatabase wraps every test in a transaction, and
 * the check fires before anything is written.
 */
class AuditOutsideTransactionTest extends TestCase
{
    public function test_ac_a10_recording_outside_a_transaction_throws(): void
    {
        $organization = (new Organization)->forceFill(['id' => 1]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('inside the transaction');

        app(AuditLog::class)->record(AuditAction::OrganizationSlugChanged, $organization, [], ['slug' => 'x'], Actor::system('test'));
    }
}
