<?php

namespace App\Enums;

enum AllocationStatus: string
{
    case Reserved = 'reserved';   // held pending payment, expires
    case Confirmed = 'confirmed'; // hostel invoice paid
    case Cancelled = 'cancelled';
}
