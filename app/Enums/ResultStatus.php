<?php

namespace App\Enums;

enum ResultStatus: string
{
    case Pending = 'pending';                  // entered by lecturer
    case HodApproved = 'hod_approved';         // departmental board
    case FacultyApproved = 'faculty_approved'; // faculty/school board
    case SenateApproved = 'senate_approved';   // senate / academic board — visible to student
}
