<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Enums\EntryMode;
use App\Enums\InstitutionType;
use App\Enums\StaffType;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\GradeScale;
use App\Models\Institution;
use App\Models\Level;
use App\Models\Programme;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A small but realistic demo dataset: a federal university with one faculty,
 * two departments, a B.Sc Computer Science programme with a 100L curriculum,
 * a current session with fee structures, plus demo admin/lecturer/student
 * accounts (password for all: "password").
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::firstOrCreate(
            ['name' => 'Federal University of Technology, Demo'],
            [
                'short_name' => 'FUTD',
                'type' => InstitutionType::University,
                'motto' => 'Technology for Development',
                'matric_format' => '{SHORT}/{YY}/{DEPT}/{SEQ:4}',
                'min_fee_percent_for_registration' => 100,
            ],
        );

        $session = AcademicSession::updateOrCreate(
            ['name' => '2025/2026'],
            ['starts_on' => '2025-10-13', 'ends_on' => '2026-09-25', 'is_current' => true],
        );

        $first = $session->semesters()->updateOrCreate(
            ['number' => 1],
            [
                'name' => 'First Semester (Harmattan)',
                'starts_on' => '2025-10-13',
                'ends_on' => '2026-02-20',
                'registration_opens_at' => '2025-10-13 08:00:00',
                'registration_closes_at' => '2025-11-14 23:59:59',
                'late_registration_closes_at' => '2025-11-28 23:59:59',
                'is_current' => true,
            ],
        );

        $session->semesters()->updateOrCreate(
            ['number' => 2],
            [
                'name' => 'Second Semester (Rain)',
                'starts_on' => '2026-03-09',
                'ends_on' => '2026-07-17',
                'registration_opens_at' => '2026-03-09 08:00:00',
                'registration_closes_at' => '2026-04-10 23:59:59',
            ],
        );

        $faculty = Faculty::updateOrCreate(
            ['code' => 'SICT'],
            ['name' => 'School of Information and Communication Technology'],
        );

        $csc = Department::updateOrCreate(
            ['code' => 'CSC'],
            ['faculty_id' => $faculty->id, 'name' => 'Computer Science'],
        );

        $gst = Department::updateOrCreate(
            ['code' => 'GST'],
            ['faculty_id' => $faculty->id, 'name' => 'General Studies'],
        );

        $nuc = GradeScale::where('slug', 'nuc-5-point')->firstOrFail();
        $level100 = Level::where('code', '100')->firstOrFail();

        $bscCsc = Programme::updateOrCreate(
            ['code' => 'BSC-CSC'],
            [
                'department_id' => $csc->id,
                'grade_scale_id' => $nuc->id,
                'name' => 'B.Sc Computer Science',
                'award' => 'BSC',
                'duration_semesters' => 8,
                'entry_level_id' => $level100->id,
                'min_units_per_semester' => 15,
                'max_units_per_semester' => 24,
            ],
        );

        // 100 Level, First Semester curriculum
        $courses = [
            // [dept, code, title, units, semester, type]
            [$csc, 'CSC 101', 'Introduction to Computer Science', 3, 1, CourseType::Core],
            [$csc, 'MTH 101', 'Elementary Mathematics I', 3, 1, CourseType::Core],
            [$csc, 'PHY 101', 'General Physics I', 3, 1, CourseType::Core],
            [$csc, 'STA 111', 'Descriptive Statistics', 3, 1, CourseType::Required],
            [$gst, 'GST 111', 'Communication in English', 2, 1, CourseType::General],
            [$gst, 'GST 112', 'Nigerian Peoples and Culture', 2, 1, CourseType::General],
            [$csc, 'CSC 102', 'Introduction to Problem Solving', 3, 2, CourseType::Core],
            [$csc, 'MTH 102', 'Elementary Mathematics II', 3, 2, CourseType::Core],
            [$gst, 'GST 121', 'Use of Library, Study Skills and ICT', 2, 2, CourseType::General],
        ];

        foreach ($courses as [$dept, $code, $title, $units, $semesterNumber, $type]) {
            $course = Course::updateOrCreate(
                ['code' => $code],
                [
                    'department_id' => $dept->id,
                    'title' => $title,
                    'credit_units' => $units,
                    'semester_number' => $semesterNumber,
                    'ca_weight' => 30,
                    'exam_weight' => 70,
                ],
            );

            $bscCsc->curriculum()->updateOrCreate(
                ['course_id' => $course->id, 'level_id' => $level100->id],
                ['semester_number' => $semesterNumber, 'type' => $type],
            );
        }

        // Fees
        $schoolFees = FeeType::updateOrCreate(
            ['code' => 'SCH'],
            ['name' => 'School Fees', 'blocks_registration' => true, 'is_recurring' => true],
        );
        $acceptance = FeeType::updateOrCreate(
            ['code' => 'ACC'],
            ['name' => 'Acceptance Fee', 'blocks_registration' => true, 'is_recurring' => false],
        );
        $ict = FeeType::updateOrCreate(
            ['code' => 'ICT'],
            ['name' => 'ICT/Portal Charge', 'blocks_registration' => false, 'is_recurring' => true],
        );

        foreach ([
            [$schoolFees, 95000],
            [$acceptance, 50000],
            [$ict, 10000],
        ] as [$feeType, $amount]) {
            FeeStructure::updateOrCreate(
                [
                    'fee_type_id' => $feeType->id,
                    'academic_session_id' => $session->id,
                    'programme_id' => null,
                    'level_id' => null,
                    'entry_mode' => null,
                    'indigene_scope' => null,
                ],
                ['amount' => $amount],
            );
        }

        // Demo accounts
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.edu.ng'],
            ['name' => 'Demo Administrator', 'password' => Hash::make('password')],
        );
        $admin->assignRole('super-admin');

        $lecturerUser = User::updateOrCreate(
            ['email' => 'lecturer@demo.edu.ng'],
            ['name' => 'Dr. Ada Obi', 'password' => Hash::make('password')],
        );
        $lecturerUser->assignRole('lecturer');

        $lecturer = Staff::updateOrCreate(
            ['staff_no' => 'FUTD/AC/0001'],
            [
                'user_id' => $lecturerUser->id,
                'department_id' => $csc->id,
                'type' => StaffType::Academic,
                'designation' => 'Senior Lecturer',
            ],
        );

        // Allocate the demo lecturer to the first-semester CSC courses.
        foreach (['CSC 101', 'MTH 101'] as $code) {
            $lecturer->courseAllocations()->updateOrCreate(
                [
                    'course_id' => Course::where('code', $code)->firstOrFail()->id,
                    'semester_id' => $first->id,
                ],
                ['is_coordinator' => $code === 'CSC 101'],
            );
        }

        $studentUser = User::updateOrCreate(
            ['email' => 'student@demo.edu.ng'],
            ['name' => 'Chinedu Okafor', 'password' => Hash::make('password')],
        );
        $studentUser->assignRole('student');

        Student::updateOrCreate(
            ['matric_no' => 'FUTD/25/CSC/0001'],
            [
                'user_id' => $studentUser->id,
                'programme_id' => $bscCsc->id,
                'level_id' => $level100->id,
                'entry_session_id' => $session->id,
                'entry_mode' => EntryMode::Utme,
                'jamb_reg_no' => '202541089721EF',
                'gender' => 'male',
                'state_of_origin' => 'Anambra',
                'lga_of_origin' => 'Awka South',
            ],
        );
    }
}
