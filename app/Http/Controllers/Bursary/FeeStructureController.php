<?php

namespace App\Http\Controllers\Bursary;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\Programme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeeStructureController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = AcademicSession::query()->orderByDesc('name')->get(['id', 'name', 'is_current']);
        $sessionId = $request->integer('session') ?: AcademicSession::current()?->id;

        $feeTypes = FeeType::query()
            ->with(['structures' => fn ($q) => $q
                ->where('academic_session_id', $sessionId)
                ->with(['programme:id,name,code', 'level:id,name'])])
            ->orderBy('name')
            ->get();

        return Inertia::render('bursary/fees', [
            'sessions' => $sessions,
            'selectedSession' => $sessionId,
            'feeTypes' => $feeTypes,
            'programmes' => Programme::query()->orderBy('name')->get(['id', 'name', 'code']),
            'levels' => Level::query()->orderBy('rank')->get(['id', 'name', 'code']),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:fee_types,code'],
            'blocks_registration' => ['boolean'],
            'is_recurring' => ['boolean'],
        ]);

        FeeType::create($validated);

        return back()->with('success', 'Fee type created.');
    }

    public function storeStructure(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fee_type_id' => ['required', 'exists:fee_types,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'level_id' => ['nullable', 'exists:levels,id'],
            'entry_mode' => ['nullable', Rule::in(['utme', 'direct_entry', 'transfer', 'hnd'])],
            'indigene_scope' => ['nullable', Rule::in(['indigene', 'non_indigene'])],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        FeeStructure::create($validated);

        return back()->with('success', 'Fee rule added.');
    }

    public function destroyStructure(FeeStructure $feeStructure): RedirectResponse
    {
        $feeStructure->delete();

        return back()->with('success', 'Fee rule removed. Existing invoices are not affected.');
    }
}
