<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Completed = 'completed';
    case PendingConfirmation = 'pending_confirmation';
    case Failed = 'failed';
}
