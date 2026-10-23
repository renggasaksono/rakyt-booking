<?php

namespace App\Audit;

/**
 * P0 audited actions (F-009 Data & rules, F-001 B8). Add a case before auditing anything new.
 */
enum AuditAction: string
{
    case BookingCreated = 'booking.created';
    case BookingStatusChanged = 'booking.status_changed';
    case BookingHoldExpired = 'booking.hold_expired';
    // F-007 AC-29: the seller cancels a holding booking.
    case BookingCancelled = 'booking.cancelled';

    case PaymentEvidenceUploaded = 'payment.evidence_uploaded';
    // F-006 AC-17/AC-18: the seller verifies a payment.
    case PaymentConfirmed = 'payment.confirmed';
    case PaymentRejected = 'payment.rejected';

    case DepartureCreated = 'departure.created';
    case DeparturePriceChanged = 'departure.price_changed';
    case DepartureCapacityChanged = 'departure.capacity_changed';
    case DepartureStatusChanged = 'departure.status_changed';
    case DepartureDeadlineChanged = 'departure.deadline_changed';
    case DepartureDeleted = 'departure.deleted';

    case TripBasePriceChanged = 'trip.base_price_changed';
    case TripPublished = 'trip.published';
    case TripUnpublished = 'trip.unpublished';

    case PaymentInstructionsChanged = 'payment_instructions.changed';

    case OrganizationCreated = 'organization.created';
    case OrganizationSlugChanged = 'organization.slug_changed';
    case OrganizationMemberAdded = 'organization.member_added';
    case OrganizationMemberRemoved = 'organization.member_removed';

    case AuthPasswordReset = 'auth.password_reset';
    case AuthPasswordChanged = 'auth.password_changed';
    case AuthEmailChanged = 'auth.email_changed';
    case AuthAccountRecovered = 'auth.account_recovered';

    // F-010 AC-4: platform-admin access granted or revoked (by seeder/command).
    case PlatformAdminGranted = 'platform_admin.granted';
    case PlatformAdminRevoked = 'platform_admin.revoked';

    /**
     * Platform-level actions belong to no organization (F-010): the platform
     * admin is not a member of any seller. Their audit events are stored with a
     * null organization_id; every other action must resolve one.
     */
    public function isPlatformLevel(): bool
    {
        return in_array($this, [self::PlatformAdminGranted, self::PlatformAdminRevoked], true);
    }
}
