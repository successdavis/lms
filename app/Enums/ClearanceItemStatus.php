<?php

namespace App\Enums;

enum ClearanceItemStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Rejected = 'rejected'; // student must resolve, then the unit re-reviews
}
