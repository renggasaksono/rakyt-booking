<?php

namespace App\Organizations;

/**
 * An organization's account status (F-012 shows and filters it; F-014
 * suspends and reactivates). Set only through forceFill — never mass-assigned.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('admin.sellers.statuses.'.$this->value);
    }
}
