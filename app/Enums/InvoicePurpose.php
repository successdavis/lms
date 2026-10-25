<?php

namespace App\Enums;

enum InvoicePurpose: string
{
    case SchoolFees = 'school_fees';
    case Transcript = 'transcript';
    case Hostel = 'hostel';
}
