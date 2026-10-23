<?php

namespace Tests\Support;

use App\Admin\TwoFactorAuthenticator;
use App\Models\User;

/**
 * Platform-admin test harness (F-010). The flag and TOTP secret are not
 * mass-assignable, so they are set with forceFill — exactly as the
 * admin:grant command does.
 */
trait BuildsAdmins
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function platformAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $user->forceFill([
            'is_platform_admin' => true,
            'two_factor_secret' => app(TwoFactorAuthenticator::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->refresh();
    }

    protected function currentTwoFactorCode(User $admin): string
    {
        return app(TwoFactorAuthenticator::class)->currentCode($admin->two_factor_secret);
    }

    /**
     * Act as an admin who has already passed the TOTP challenge this session.
     */
    protected function actingAsConfirmedAdmin(User $admin): static
    {
        return $this->actingAs($admin)->withSession([
            'admin.2fa.confirmed_at' => now()->getTimestamp(),
            'admin.2fa.last_seen' => now()->getTimestamp(),
        ]);
    }
}
