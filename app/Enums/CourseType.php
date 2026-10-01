<?php

namespace App\Enums;

enum CourseType: string
{
    case Core = 'core';          // compulsory, must pass to graduate
    case Required = 'required';  // must register, counts toward requirements
    case Elective = 'elective';  // chosen to meet minimum unit requirements
    case General = 'general';    // GST/GNS general studies
}
