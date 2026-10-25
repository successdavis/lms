<?php

namespace App\Services\Academics;

use App\Models\Institution;
use App\Models\Student;

class MatricNumberGenerator
{
    /**
     * Generate a matriculation number from the institution's template.
     *
     * Supported tokens: {SHORT} institution short name, {YY} 2-digit entry
     * year, {YYYY} 4-digit entry year, {FAC} faculty code, {DEPT} department
     * code, {PROG} programme code, {SEQ:n} zero-padded sequence of width n.
     *
     * e.g. "{SHORT}/{YY}/{DEPT}/{SEQ:4}" -> "FUTO/25/CSC/0042"
     */
    public function generate(Student $student, int $sequence): string
    {
        $template = Institution::current()?->matric_format ?? '{SHORT}/{YY}/{DEPT}/{SEQ:4}';

        $entryYear = (int) explode('/', $student->entrySession->name)[0];
        $department = $student->programme->department;

        $value = strtr($template, [
            '{SHORT}' => Institution::current()?->short_name ?? 'INST',
            '{YYYY}' => (string) $entryYear,
            '{YY}' => substr((string) $entryYear, -2),
            '{FAC}' => $department->faculty->code,
            '{DEPT}' => $department->code,
            '{PROG}' => $student->programme->code,
        ]);

        return preg_replace_callback(
            '/\{SEQ:(\d+)\}/',
            fn ($m) => str_pad((string) $sequence, (int) $m[1], '0', STR_PAD_LEFT),
            $value,
        );
    }
}
