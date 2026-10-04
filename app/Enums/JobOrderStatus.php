<?php

namespace App\Enums;

enum JobOrderStatus: string
{
    case Intake = 'intake';
    case ValidationFailed = 'validation_failed';
    case ReadyForProduction = 'ready_for_production';
    case Assigned = 'assigned';
    case InConsultation = 'in_consultation';
    case InDesign = 'in_design';
    case PendingReview = 'pending_review';
    case DesignApproved = 'design_approved';
    case ForProduction = 'for_production';
    case Printing = 'printing';
    case ReadyForPickup = 'ready_for_pickup';

    /**
     * The stages at which the Cashier may price a job order and take payment
     * for it.
     *
     * @var array<int, self>
     */
    public const PAYABLE = [
        self::ReadyForProduction,
        self::DesignApproved,
        self::ForProduction,
        self::Printing,
        self::ReadyForPickup,
    ];
}
