<?php

namespace App\Enums;

/**
 * Units that sign off a final-year student's clearance, and the role that
 * signs for each. Registrar and super-admin may act for any unit.
 */
enum ClearanceUnit: string
{
    case Department = 'department';
    case Faculty = 'faculty';
    case Library = 'library';
    case Bursary = 'bursary';
    case StudentAffairs = 'student_affairs';

    public function role(): string
    {
        return match ($this) {
            self::Department => 'hod',
            self::Faculty => 'dean',
            self::Library => 'librarian',
            self::Bursary => 'bursar',
            self::StudentAffairs => 'student-affairs',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Department => 'Department (HOD)',
            self::Faculty => 'Faculty / School (Dean)',
            self::Library => 'Library',
            self::Bursary => 'Bursary',
            self::StudentAffairs => 'Student Affairs (hostel & conduct)',
        };
    }
}
