<?php

namespace App\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Maternity = 'maternity';
    case Study = 'study';
    case Casual = 'casual';
}
