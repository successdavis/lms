<?php

namespace App\Support\Dashboard;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Which departments a dashboard may report on. Institution-wide offices see
 * everything and may narrow to a faculty or department; a Dean is held to
 * their own faculty (and may narrow to one of its departments); an HOD is
 * held to their own department. Same rule as the clearance and approval
 * queues: the department comes from the user's staff record.
 */
class DashboardScope
{
    public const INSTITUTION_ROLES = ['registrar', 'super-admin', 'exam-officer', 'bursar', 'admission-officer', 'student-affairs', 'hr-officer'];

    /**
     * @param  list<int>|null  $departmentIds  null means every department
     */
    private function __construct(
        public readonly ?array $departmentIds,
        public readonly string $label,
        public readonly ?int $facultyId,
        public readonly ?int $departmentId,
        /** Faculty the user may not leave (Dean), null when free to choose. */
        private readonly ?int $lockedFacultyId,
        private readonly bool $lockedToDepartment,
    ) {}

    public static function forUser(User $user, Request $request): self
    {
        $staffDepartment = $user->staff?->department;

        if ($user->hasAnyRole(self::INSTITUTION_ROLES)) {
            return self::narrowed($request->integer('faculty_id') ?: null, $request->integer('department_id') ?: null, null);
        }

        if ($user->hasRole('dean')) {
            if ($staffDepartment === null) {
                return self::nothing('No department on your staff record');
            }

            return self::narrowed($staffDepartment->faculty_id, $request->integer('department_id') ?: null, $staffDepartment->faculty_id);
        }

        if ($user->hasRole('hod')) {
            if ($staffDepartment === null) {
                return self::nothing('No department on your staff record');
            }

            return new self([$staffDepartment->id], $staffDepartment->name, $staffDepartment->faculty_id, $staffDepartment->id, $staffDepartment->faculty_id, true);
        }

        return self::nothing('No department on your staff record');
    }

    private static function narrowed(?int $facultyId, ?int $departmentId, ?int $lockedFacultyId): self
    {
        $faculty = $facultyId ? Faculty::query()->find($facultyId) : null;
        $department = $departmentId ? Department::query()->find($departmentId) : null;

        // A department outside the chosen (or locked) faculty is ignored.
        if ($department !== null && $faculty !== null && $department->faculty_id !== $faculty->id) {
            $department = null;
        }

        if ($department !== null) {
            return new self([$department->id], $department->name, $department->faculty_id, $department->id, $lockedFacultyId, false);
        }

        if ($faculty !== null) {
            $ids = Department::query()->where('faculty_id', $faculty->id)->pluck('id')->all();

            return new self($ids, $faculty->name, $faculty->id, null, $lockedFacultyId, false);
        }

        return new self(null, 'Whole institution', null, null, null, false);
    }

    private static function nothing(string $label): self
    {
        return new self([], $label, null, null, null, true);
    }

    public function isInstitution(): bool
    {
        return $this->departmentIds === null;
    }

    /** Faculties and departments this user may switch between, for the pickers. */
    public function options(): array
    {
        if ($this->lockedToDepartment) {
            return [];
        }

        return Faculty::query()
            ->when($this->lockedFacultyId, fn ($q, $id) => $q->whereKey($id))
            ->with(['departments' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'departments' => $f->departments->map(fn (Department $d) => ['id' => $d->id, 'name' => $d->name])->values(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'faculty_id' => $this->facultyId,
            'department_id' => $this->departmentId,
            'locked' => $this->lockedToDepartment,
            'faculty_locked' => $this->lockedFacultyId !== null,
            'institution' => $this->isInstitution(),
        ];
    }
}
