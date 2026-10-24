<?php

namespace App\Enums;

enum SiwesStatus: string
{
    case Pending = 'pending';   // placement submitted, awaiting coordinator
    case Approved = 'approved'; // on attachment
    case Rejected = 'rejected';
    case Graded = 'graded';     // assessed after the attachment
}
