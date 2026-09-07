<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case PendingConfirmation = 'pending_confirmation';
    case Paid = 'paid';
    case CreditPendingApproval = 'credit_pending_approval';
    case OnCredit = 'on_credit';
    case CreditRejected = 'credit_rejected';
    case WrittenOff = 'written_off';
}
