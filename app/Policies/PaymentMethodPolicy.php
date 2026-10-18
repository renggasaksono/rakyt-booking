<?php

namespace App\Policies;

use App\Tenancy\OrganizationOwnedPolicy;

class PaymentMethodPolicy extends OrganizationOwnedPolicy {}
