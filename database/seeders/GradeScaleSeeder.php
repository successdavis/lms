<?php

namespace Database\Seeders;

use App\Models\GradeScale;
use Illuminate\Database\Seeder;

class GradeScaleSeeder extends Seeder
{
    /**
     * Seeds the two national grading standards:
     *  - NUC 5-point scale (universities, restored 2018/2019 session)
     *  - NBTE 4-point unified scale (polytechnics, from 2015/2016 session)
     * Both remain editable per institution.
     */
    public function run(): void
    {
        $nuc = GradeScale::updateOrCreate(
            ['slug' => 'nuc-5-point'],
            [
                'name' => 'NUC 5-Point Scale (Universities)',
                'max_point' => 5.00,
                'pass_mark' => 40.00,
                'probation_cgpa' => 1.50,
                'withdrawal_cgpa' => 1.00,
                'description' => 'Standard NUC university grading. Pass mark 40% (grade E). '
                    .'Probation below CGPA 1.50; withdrawal below 1.00 after probation.',
            ],
        );

        $nucBands = [
            ['A', 70, 100, 5.00, true],
            ['B', 60, 69.99, 4.00, true],
            ['C', 50, 59.99, 3.00, true],
            ['D', 45, 49.99, 2.00, true],
            ['E', 40, 44.99, 1.00, true],
            ['F', 0, 39.99, 0.00, false],
        ];

        foreach ($nucBands as $i => [$letter, $min, $max, $point, $pass]) {
            $nuc->bands()->updateOrCreate(
                ['letter' => $letter],
                ['min_score' => $min, 'max_score' => $max, 'point' => $point, 'is_pass' => $pass, 'sort' => $i],
            );
        }

        // NUC degree classes. Note the authentic 2:2 lower bound of 2.40 (not 2.50).
        $nucClasses = [
            ['First Class Honours', 4.50, 5.00],
            ['Second Class Honours (Upper Division)', 3.50, 4.49],
            ['Second Class Honours (Lower Division)', 2.40, 3.49],
            ['Third Class Honours', 1.50, 2.39],
            ['Pass', 1.00, 1.49],
        ];

        foreach ($nucClasses as $i => [$name, $min, $max]) {
            $nuc->classificationBands()->updateOrCreate(
                ['name' => $name],
                ['min_cgpa' => $min, 'max_cgpa' => $max, 'sort' => $i],
            );
        }

        $nbte = GradeScale::updateOrCreate(
            ['slug' => 'nbte-4-point'],
            [
                'name' => 'NBTE 4-Point Scale (Polytechnics)',
                'max_point' => 4.00,
                'pass_mark' => 40.00,
                'probation_cgpa' => 2.00,
                'withdrawal_cgpa' => null, // withdrawal = two consecutive semesters below 2.00
                'description' => 'NBTE unified polytechnic grading (2015/2016). Pass mark 40% '
                    .'(grade E). Probation below CGPA 2.00; withdrawal after two consecutive '
                    .'semesters below 2.00.',
            ],
        );

        $nbteBands = [
            ['A', 75, 100, 4.00, true],
            ['AB', 70, 74.99, 3.50, true],
            ['B', 65, 69.99, 3.25, true],
            ['BC', 60, 64.99, 3.00, true],
            ['C', 55, 59.99, 2.75, true],
            ['CD', 50, 54.99, 2.50, true],
            ['D', 45, 49.99, 2.25, true],
            ['E', 40, 44.99, 2.00, true],
            ['F', 0, 39.99, 0.00, false],
        ];

        foreach ($nbteBands as $i => [$letter, $min, $max, $point, $pass]) {
            $nbte->bands()->updateOrCreate(
                ['letter' => $letter],
                ['min_score' => $min, 'max_score' => $max, 'point' => $point, 'is_pass' => $pass, 'sort' => $i],
            );
        }

        $nbteClasses = [
            ['Distinction', 3.50, 4.00],
            ['Upper Credit', 3.00, 3.49],
            ['Lower Credit', 2.50, 2.99],
            ['Pass', 2.00, 2.49],
        ];

        foreach ($nbteClasses as $i => [$name, $min, $max]) {
            $nbte->classificationBands()->updateOrCreate(
                ['name' => $name],
                ['min_cgpa' => $min, 'max_cgpa' => $max, 'sort' => $i],
            );
        }
    }
}
