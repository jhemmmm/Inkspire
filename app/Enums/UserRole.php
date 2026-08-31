<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case FrontlineStaff = 'frontline_staff';
    case Artist = 'artist';
    case Cashier = 'cashier';
    case ProductionStaff = 'production_staff';
    case AccountingStaff = 'accounting_staff';

    /**
     * Get the named route for this role's dedicated portal dashboard.
     */
    public function portalRoute(): string
    {
        return match ($this) {
            self::Owner, self::Admin => 'owner.dashboard',
            self::FrontlineStaff => 'frontline-staff.dashboard',
            self::Artist => 'artist.dashboard',
            self::Cashier => 'cashier.dashboard',
            self::ProductionStaff => 'production-staff.dashboard',
            self::AccountingStaff => 'accounting-staff.dashboard',
        };
    }
}
