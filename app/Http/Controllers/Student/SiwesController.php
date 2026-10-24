<?php

namespace App\Http\Controllers\Student;

use App\Enums\SiwesStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SiwesController extends Controller
{
    public function show(Request $request): Response
    {
        $student = $request->user()->student()->firstOrFail();
        $session = AcademicSession::current();

        return Inertia::render('student/siwes', [
            'session' => $session?->only('id', 'name'),
            'placement' => $session
                ? $student->siwesPlacements()->where('academic_session_id', $session->id)->first()
                : null,
            'minWeeks' => config('lms.siwes.min_weeks'),
            'history' => $student->siwesPlacements()->with('academicSession:id,name')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employer_name' => ['required', 'string', 'max:255'],
            'employer_address' => ['required', 'string', 'max:1000'],
            'industry_supervisor_name' => ['required', 'string', 'max:255'],
            'industry_supervisor_phone' => ['required', 'string', 'max:20'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);

        $minWeeks = config('lms.siwes.min_weeks');
        $weeks = Carbon::parse($validated['starts_on'])->diffInWeeks(Carbon::parse($validated['ends_on']));

        if ($weeks < $minWeeks) {
            return back()->withErrors(['ends_on' => "The attachment must run at least {$minWeeks} weeks."]);
        }

        $student = $request->user()->student()->firstOrFail();
        $session = AcademicSession::current();

        if ($session === null) {
            return back()->withErrors(['siwes' => 'No current academic session is configured.']);
        }

        $existing = $student->siwesPlacements()->where('academic_session_id', $session->id)->first();

        if ($existing !== null && ! in_array($existing->status, [SiwesStatus::Pending, SiwesStatus::Rejected], true)) {
            return back()->withErrors(['siwes' => 'Your placement is already approved and can no longer be changed.']);
        }

        $student->siwesPlacements()->updateOrCreate(
            ['academic_session_id' => $session->id],
            $validated + ['status' => SiwesStatus::Pending],
        );

        return back()->with('success', 'Placement submitted to the SIWES coordinator.');
    }
}
