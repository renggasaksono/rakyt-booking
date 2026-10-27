<?php

namespace App\Admin\Console;

use App\Admin\TwoFactorAuthenticator;
use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\User;
use App\Tenancy\Middleware\ResolveCurrentOrganization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Grant platform-admin access and enrol TOTP two-factor (F-010, ADR-0011).
 * The only way the flag is ever set; it can never be set over HTTP. The grant
 * is audited (F-010 AC-4). The secret is shown once, for the admin to add to
 * their authenticator app. A seller's account is refused: the admin is never a
 * member of any organization (F-010).
 */
class GrantPlatformAdminCommand extends Command
{
    protected $signature = 'admin:grant {email : The email of the user to make a platform admin}';

    protected $description = 'Grant platform-admin access to a user and enrol TOTP two-factor (F-010).';

    public function handle(TwoFactorAuthenticator $totp, AuditLog $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if (ResolveCurrentOrganization::membershipOf($user) !== null) {
            $this->error("{$email} is a member of an organization. The platform admin must be a separate account.");

            return self::FAILURE;
        }

        $secret = $totp->generateSecret();

        DB::transaction(function () use ($user, $secret, $audit) {
            $before = ['is_platform_admin' => (bool) $user->is_platform_admin];

            // forceFill: the flag and secret are intentionally not mass-assignable.
            $user->forceFill([
                'is_platform_admin' => true,
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ])->save();

            $audit->record(
                AuditAction::PlatformAdminGranted,
                $user,
                $before,
                ['is_platform_admin' => true],
                Actor::system('admin:grant'),
            );
        });

        $url = $totp->enrolmentUrl((string) config('app.name'), $user->email, $secret);

        $this->info("Granted platform-admin access to {$user->email}.");
        $this->newLine();
        $this->line('Add this to an authenticator app (Google Authenticator, 1Password, …):');
        $this->line('  Secret:      '.$secret);
        $this->line('  otpauth URL: '.$url);
        $this->newLine();
        $this->warn('The secret is shown once. Store it in the authenticator now.');

        return self::SUCCESS;
    }
}
