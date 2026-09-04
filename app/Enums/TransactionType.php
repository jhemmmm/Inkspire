<?php

namespace App\Enums;

enum TransactionType: string
{
    case DownPayment = 'down_payment';
    case BalancePayment = 'balance_payment';
    case FullPayment = 'full_payment';
    case CancellationFee = 'cancellation_fee';
}
