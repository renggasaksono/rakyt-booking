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

    case PaymentEvidenceUploaded = 'payment.evidence_uploaded';
    case PaymentVerified = 'payment.verified';
    case PaymentRejected = 'payment.rejected';
    case PaymentRecordAmountChanged = 'payment_record.amount_changed';

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
}
