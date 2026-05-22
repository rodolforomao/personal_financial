<?php

namespace App\Core\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Reconciled = 'reconciled';
}
