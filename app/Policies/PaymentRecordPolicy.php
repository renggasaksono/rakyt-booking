<?php

namespace App\Policies;

use App\Tenancy\OrganizationOwnedPolicy;

/**
 * Sellers see only their own organization's payment records and evidence
 * (F-006 AC-19, AC-27). Cross-tenant access is 404 (OrganizationOwnedPolicy).
 * Customers never reach a record through this policy — only through their
 * booking access token (ADR-0027).
 */
class PaymentRecordPolicy extends OrganizationOwnedPolicy {}
