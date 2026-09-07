<?php

namespace App\Enums;

/**
 * D-10's collection status — independent of AccountsReceivableStatus, which
 * models the credit lifecycle rather than how chasing is going.
 */
enum AccountsReceivableCollectionStatus: string
{
    case Pending = 'pending';
    case FollowUp = 'follow_up';
    case WarningSent = 'warning_sent';
    case Collections = 'collections';
    case Paid = 'paid';
    case WrittenOff = 'written_off';
}
