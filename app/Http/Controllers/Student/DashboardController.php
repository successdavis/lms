<?php

namespace App\Http\Controllers\Student;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $student = $request->user()->student()->with(['programme.department', 'level'])->firstOrFail();
        $semester = Semester::current();

        $latestResult = $student->semesterResults()->latest('id')->first();

        $registration = $semester
            ? $student->courseRegistrations()->where('semester_id', $semester->id)->first()
            : null;

        $unpaidInvoices = $student->invoices()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
            ->count();

        return Inertia::render('student/dashboard', [
            'student' => $student,
            'session' => AcademicSession::current()?->only('id', 'name'),
            'semester' => $semester?->only('id', 'name'),
            'cgpa' => $latestResult?->cgpa,
            'standing' => $latestResult?->standing,
            'registration' => $registration?->only('id', 'status', 'total_units'),
            'carryoverCount' => $student->outstandingCarryovers()->count(),
            'unpaidInvoices' => $unpaidInvoices,
        ]);
    }
}
