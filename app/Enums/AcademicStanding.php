<?php

namespace App\Enums;

enum AcademicStanding: string
{
    case Good = 'good';
    case Probation = 'probation';
    case Withdrawal = 'withdrawal'; // advised to withdraw from programme
}
