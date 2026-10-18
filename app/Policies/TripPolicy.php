<?php

namespace App\Policies;

use App\Tenancy\OrganizationOwnedPolicy;

/**
 * Any member of the trip's organization may manage it in the MVP (F-003 Data & rules).
 */
class TripPolicy extends OrganizationOwnedPolicy {}
