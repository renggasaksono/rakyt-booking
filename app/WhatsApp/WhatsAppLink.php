<?php

namespace App\WhatsApp;

use App\Models\Organization;
use App\Security\PhoneNumber;

/**
 * The one builder for every prefilled WhatsApp deep link (F-008). It turns an
 * E.164 number and an already-interpolated Bahasa message into
 * `https://wa.me/<digits>?text=<rawurlencode(text)>`, encoding the text exactly
 * once, server-side (AC-A7, AC-B10). It returns null — so the caller hides the
 * button instead of linking to `https://wa.me/?text=…` — when the number is not
 * a valid Indonesian mobile (AC-A6/A9/D3, AC-B5). Message bodies live only in
 * `lang/id/whatsapp.php` (AC-B1/D4); this class never reads a template itself.
 */
final class WhatsAppLink
{
    /**
     * Customer → seller link on the organization's own number (AC-A9, AC-B3/B4).
     */
    public static function to(Organization $organization, string $text): ?string
    {
        return self::toNumber($organization->whatsapp, $text);
    }

    /**
     * Seller → customer link on a booking's own customer number (AC-B5); also
     * the shared digits-only builder behind to(). Null for an invalid number.
     */
    public static function toNumber(?string $e164, string $text): ?string
    {
        if (! PhoneNumber::isMobile($e164)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $e164);

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    /**
     * A text-free chat link ("Chat WhatsApp" on the seller booking detail,
     * F-007 AC-25) on the booking's own customer number, guarded like the rest.
     */
    public static function chatNumber(?string $e164): ?string
    {
        if (! PhoneNumber::isMobile($e164)) {
            return null;
        }

        return 'https://wa.me/'.preg_replace('/\D+/', '', (string) $e164);
    }
}
