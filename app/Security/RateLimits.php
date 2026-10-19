<?php

namespace App\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Named limiters for public and auth endpoints (F-009 A14, A15). Values come
 * from config/rakyt.php. Use as route middleware, e.g. `throttle:seller-login`.
 *
 * Indonesian carriers use carrier-grade NAT, so per-IP limits are loose; the
 * main key is the email for sellers and the phone number or token for customers.
 */
final class RateLimits
{
    public static function register(): void
    {
        RateLimiter::for('seller-login', fn (Request $request) => [
            self::limit('seller_login.per_email_ip', 'login:'.self::email($request).'|'.$request->ip()),
            self::limit('seller_login.per_ip', 'login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            self::limit('password_reset.per_email', 'reset:'.self::email($request)),
            self::limit('password_reset.per_ip', 'reset-ip:'.$request->ip()),
        ]);

        RateLimiter::for('signup', fn (Request $request) => [
            self::limit('signup.per_ip', 'signup-ip:'.$request->ip()),
        ]);

        RateLimiter::for('public-booking', fn (Request $request) => [
            self::limit('public_booking.per_phone', 'booking-phone:'.PhoneNumber::normalize($request->input('phone'))),
            self::limit('public_booking.per_ip', 'booking-ip:'.$request->ip()),
        ]);

        RateLimiter::for('evidence-upload', fn (Request $request) => [
            // Hash so the raw access token never lands in the cache store.
            self::limit('evidence_upload.per_token', 'evidence:'.hash('sha256', (string) $request->route('token'))),
        ]);

        // F-002 AC-9: public seller and trip pages.
        RateLimiter::for('public-page', fn (Request $request) => [
            self::limit('public_page.per_ip', 'public-page-ip:'.$request->ip()),
        ]);

        RateLimiter::for('slug-check', fn (Request $request) => [
            self::limit('slug_check.per_ip', 'slug-ip:'.$request->ip()),
        ]);

        // Signed-in routes only: keyed on the user (F-001 A10–A12).
        RateLimiter::for('verification-resend', fn (Request $request) => [
            self::limit('verification_resend.per_user', 'verify:'.$request->user()?->getKey()),
        ]);

        RateLimiter::for('account-change', fn (Request $request) => [
            self::limit('account_change.per_user', 'account:'.$request->user()?->getKey()),
        ]);

        RateLimiter::for('payment-change', fn (Request $request) => [
            self::limit('payment_change.per_user', 'payment:'.$request->user()?->getKey()),
        ]);
    }

    /**
     * Lower-cased, trimmed email so `Budi@Mail.com` and ` budi@mail.com` share a bucket (A15).
     */
    public static function email(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email')));
    }

    private static function limit(string $key, string $by): Limit
    {
        [$max, $minutes] = config("rakyt.rate_limits.{$key}");

        return Limit::perMinutes($minutes, $max)->by($key.'|'.$by);
    }
}
