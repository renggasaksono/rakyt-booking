<?php

namespace App\Policies;

use App\Tenancy\OrganizationOwnedPolicy;

/**
 * Sellers see only their own bookings (F-005 AC-38, F-007). Customers reach a
 * booking through its access token, never through this policy (ADR-0027).
 */
class BookingPolicy extends OrganizationOwnedPolicy {}
