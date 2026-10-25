<?php

namespace App\Support;

use App\Enums\InstitutionType;

/**
 * Academic rank ladders, lowest to highest. Universities run CONUASS ranks
 * up to Professor; polytechnics run CONPCASS ranks up to Chief Lecturer.
 * Non-academic staff progress by grade level (CONTISS) rather than rank.
 */
final class StaffRanks
{
    public const UNIVERSITY = [
        'Graduate Assistant',
        'Assistant Lecturer',
        'Lecturer II',
        'Lecturer I',
        'Senior Lecturer',
        'Reader',
        'Professor',
    ];

    public const POLYTECHNIC = [
        'Graduate Assistant',
        'Assistant Lecturer',
        'Lecturer III',
        'Lecturer II',
        'Lecturer I',
        'Senior Lecturer',
        'Principal Lecturer',
        'Chief Lecturer',
    ];

    /**
     * @return list<string>
     */
    public static function ladder(?InstitutionType $type): array
    {
        return $type === InstitutionType::Polytechnic ? self::POLYTECHNIC : self::UNIVERSITY;
    }

    public static function academicStructure(?InstitutionType $type): string
    {
        return $type === InstitutionType::Polytechnic ? 'CONPCASS' : 'CONUASS';
    }

    /**
     * The next rank above the given one, or null at the top / off-ladder.
     */
    public static function next(?InstitutionType $type, ?string $designation): ?string
    {
        $ladder = self::ladder($type);
        $index = array_search($designation, $ladder, true);

        return $index === false ? null : ($ladder[$index + 1] ?? null);
    }
}
