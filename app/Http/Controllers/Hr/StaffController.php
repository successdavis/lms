<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Staff;
use App\Services\Hr\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Establishment register: designation, salary structure, grade level and
 * step, IPPIS and bank details, plus payroll hooks (export, annual increment).
 */
class StaffController extends Controller
{
    public const STRUCTURES = ['CONUASS', 'CONPCASS', 'CONTISS', 'CONTEDISS'];

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('hr/staff', [
            'staff' => Staff::query()
                ->with(['user:id,name,email', 'department:id,name'])
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('staff_no', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
                ->orderBy('staff_no')
                ->paginate(25)
                ->withQueryString(),
            'filters' => ['search' => $search],
            'structures' => self::STRUCTURES,
        ]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $validated = $request->validate([
            'designation' => ['nullable', 'string', 'max:100'],
            'salary_structure' => ['nullable', Rule::in(self::STRUCTURES)],
            'grade_level' => ['nullable', 'integer', 'min:1', 'max:17'],
            'step' => ['nullable', 'integer', 'min:1', 'max:'.config('lms.hr.max_step')],
            'rank_since' => ['nullable', 'date', 'before_or_equal:today'],
            'ippis_number' => ['nullable', 'string', 'max:20', Rule::unique('staff', 'ippis_number')->ignore($staff->id)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'digits:10'], // NUBAN
            'is_active' => ['boolean'],
        ]);

        $staff->update($validated);

        return back()->with('success', "Record updated for {$staff->staff_no}.");
    }

    public function stepIncrement(PromotionService $promotions): RedirectResponse
    {
        $count = $promotions->annualStepIncrement();

        AuditLog::record('payroll.step_increment', Staff::class, null, ['staff_incremented' => $count], "Annual step increment applied to {$count} staff");

        return back()->with('success', "Annual step increment applied to {$count} staff member(s).");
    }

    /**
     * Payroll hook: active staff in an IPPIS-style layout for upload to the
     * payroll system.
     */
    public function payrollExport(): StreamedResponse
    {
        $staff = Staff::query()
            ->where('is_active', true)
            ->with(['user:id,name', 'department:id,name'])
            ->orderBy('staff_no')
            ->get();

        AuditLog::record('payroll.exported', Staff::class, null, ['rows' => $staff->count()], 'Payroll export downloaded');

        return response()->streamDownload(function () use ($staff) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Staff No', 'IPPIS No', 'Full Name', 'Designation', 'Department', 'Salary Structure', 'Grade Level', 'Step', 'Bank', 'Account Number']);

            foreach ($staff as $member) {
                fputcsv($out, [
                    $member->staff_no,
                    $member->ippis_number,
                    $member->user->name,
                    $member->designation,
                    $member->department?->name,
                    $member->salary_structure,
                    $member->grade_level,
                    $member->step,
                    $member->bank_name,
                    $member->account_number,
                ]);
            }

            fclose($out);
        }, 'payroll-'.now()->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }
}
