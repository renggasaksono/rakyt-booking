<?php

namespace Tests\Feature\Audit;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\Organization;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use LogicException;
use Tests\TestCase;

/**
 * F-009 A10: an audit write outside a transaction is a bug, so it throws.
 * Uses truncation instead of RefreshDatabase, which wraps every test in a transaction.
 */
class AuditOutsideTransactionTest extends TestCase
{
    use DatabaseTruncation;

    public function test_ac_a10_recording_outside_a_transaction_throws(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(LogicException::class);

        app(AuditLog::class)->record(AuditAction::OrganizationSlugChanged, $organization, [], ['slug' => 'x'], Actor::system('test'));
    }
}
