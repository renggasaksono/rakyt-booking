<?php

namespace App\Admin\Console;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revoke platform-admin access and clear the TOTP enrolment (F-010, ADR-0011).
 * The revoke is audited (F-010 AC-4).
 */
class RevokePlatformAdminCommand extends Command
{
    protected $signature = 'admin:revoke {email : The email of the platform admin to revoke}';

    protected $description = 'Revoke platform-admin access from a user (F-010).';

    public function handle(AuditLog $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if (! $user->is_platform_admin) {
            $this->warn("{$user->email} is not a platform admin; nothing to revoke.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $audit) {
            $user->forceFill([
                'is_platform_admin' => false,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $audit->record(
                AuditAction::PlatformAdminRevoked,
                $user,
                ['is_platform_admin' => true],
                ['is_platform_admin' => false],
                Actor::system('admin:revoke'),
            );
        });

        $this->info("Revoked platform-admin access from {$user->email}.");

        return self::SUCCESS;
    }
}
