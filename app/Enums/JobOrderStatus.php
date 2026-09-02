<?php

namespace App\Enums;

enum JobOrderStatus: string
{
    case Intake = 'intake';
    case ValidationFailed = 'validation_failed';
    case ReadyForProduction = 'ready_for_production';
    case Assigned = 'assigned';
    case InConsultation = 'in_consultation';
}
