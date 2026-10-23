<?php

namespace App\Admin;

use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor for the platform admin (F-010, ADR-0011). Hand-rolled on
 * pragmarx/google2fa to sit alongside the project's hand-rolled auth (F-009),
 * rather than pulling in Laravel Fortify's own login stack. Enrolment happens
 * when the admin flag is granted (admin:grant); the secret is stored encrypted.
 */
final class TwoFactorAuthenticator
{
    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Verify a 6-digit code, tolerating spaces the authenticator app may show.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '') {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * The current code for a secret. Used by tests and never in production flow.
     */
    public function currentCode(string $secret): string
    {
        return $this->google2fa->getCurrentOtp($secret);
    }

    /**
     * The otpauth:// URL an authenticator app imports (shown by admin:grant).
     */
    public function enrolmentUrl(string $issuer, string $account, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($issuer, $account, $secret);
    }
}
