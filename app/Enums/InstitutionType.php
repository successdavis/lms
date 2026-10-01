<?php

namespace App\Enums;

enum InstitutionType: string
{
    case University = 'university';
    case Polytechnic = 'polytechnic';

    /**
     * Label used for the top-level academic unit grouping departments.
     */
    public function facultyLabel(): string
    {
        return match ($this) {
            self::University => 'Faculty',
            self::Polytechnic => 'School',
        };
    }

    /**
     * Body that gives final approval to results.
     */
    public function resultApprovalBody(): string
    {
        return match ($this) {
            self::University => 'Senate',
            self::Polytechnic => 'Academic Board',
        };
    }
}
