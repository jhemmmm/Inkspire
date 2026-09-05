<?php

namespace App\Enums;

enum AccountsReceivableStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
}
