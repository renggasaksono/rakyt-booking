<?php

namespace App\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use LogicException;

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

        // F-009 R1: an empty or unparseable phone adds no phone bucket (everyone
        // would share one); only the IP limit applies then (F-005 AC-39).
        RateLimiter::for('public-booking', fn (Request $request) => array_values(array_filter([
            ($phone = self::mobile($request->input('phone'))) === null
                ? null
                : self::limit('public_booking.per_phone', 'booking-phone:'.$phone),
            self::limit('public_booking.per_ip', 'booking-ip:'.$request->ip()),
        ])));

        RateLimiter::for('evidence-upload', function (Request $request) {
            $token = $request->route('token');

            // F-009 R1: without the route parameter every upload would share one bucket.
            if (! is_string($token) || $token === '') {
                throw new LogicException('throttle:evidence-upload needs a {token} route parameter.');
            }

            // Hash so the raw access token never lands in the cache store.
            return [self::limit('evidence_upload.per_token', 'evidence:'.hash('sha256', $token))];
        });

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

    /**
     * The normalised Indonesian mobile number (F-009 A15), or null when the
     * input is not one.
     */
    public static function mobile(mixed $input): ?string
    {
        $phone = is_string($input) ? PhoneNumber::normalize($input) : null;

        return $phone !== null && PhoneNumber::isMobile($phone) ? $phone : null;
    }

    private static function limit(string $key, string $by): Limit
    {
        [$max, $minutes] = config("rakyt.rate_limits.{$key}");

        return Limit::perMinutes($minutes, $max)->by($key.'|'.$by);
    }
}
