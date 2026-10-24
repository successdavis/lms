<?php

namespace App\Http\Controllers\Hostels;

use App\Enums\AllocationStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Services\Hostels\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student Affairs: hostels, rooms, bed-space occupancy and allocations.
 */
class HostelAdminController extends Controller
{
    public function index(HostelService $hostels): Response
    {
        $session = AcademicSession::current();
        $hostels->expireLapsed();

        return Inertia::render('hostels/admin', [
            'session' => $session?->only('id', 'name'),
            'hostels' => Hostel::query()->with('rooms')->orderBy('name')->get()->map(function (Hostel $hostel) use ($session) {
                $capacity = $hostel->rooms->sum('capacity');

                return [
                    'id' => $hostel->id,
                    'name' => $hostel->name,
                    'gender' => $hostel->gender,
                    'fee' => $hostel->fee,
                    'is_active' => $hostel->is_active,
                    'rooms' => $hostel->rooms->count(),
                    'capacity' => $capacity,
                    'occupied' => $session ? $hostel->rooms->sum(fn ($room) => $room->occupiedSpaces($session->id)) : 0,
                ];
            }),
            'allocations' => $session
                ? HostelAllocation::query()
                    ->where('academic_session_id', $session->id)
                    ->whereIn('status', [AllocationStatus::Reserved, AllocationStatus::Confirmed])
                    ->with(['student.user:id,name', 'room.hostel:id,name'])
                    ->latest()
                    ->get()
                    ->map(fn (HostelAllocation $a) => [
                        'id' => $a->id,
                        'student' => $a->student->user->name,
                        'matric_no' => $a->student->matric_no,
                        'hostel' => $a->room->hostel->name,
                        'room' => $a->room->number,
                        'status' => $a->status->value,
                        'expires_at' => $a->expires_at?->toDayDateTimeString(),
                    ])
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female', 'mixed'])],
            'fee' => ['required', 'numeric', 'min:0'],
        ]);

        Hostel::create($validated);

        return back()->with('success', 'Hostel created. Add rooms to open bed spaces.');
    }

    /**
     * Add a run of rooms, e.g. prefix "A", 1-20, 4 beds each => A1..A20.
     */
    public function storeRooms(Request $request, Hostel $hostel): RedirectResponse
    {
        $validated = $request->validate([
            'prefix' => ['nullable', 'string', 'max:10'],
            'from' => ['required', 'integer', 'min:1'],
            'to' => ['required', 'integer', 'gte:from', 'lte:'.($request->integer('from') + 499)],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $created = 0;

        foreach (range($validated['from'], $validated['to']) as $n) {
            $room = $hostel->rooms()->firstOrCreate(
                ['number' => ($validated['prefix'] ?? '').$n],
                ['capacity' => $validated['capacity']],
            );
            $created += $room->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', "{$created} room(s) added to {$hostel->name}.");
    }

    public function toggle(Hostel $hostel): RedirectResponse
    {
        $hostel->update(['is_active' => ! $hostel->is_active]);

        return back()->with('success', "{$hostel->name} ".($hostel->is_active ? 'opened' : 'closed').' for booking.');
    }

    public function cancel(HostelAllocation $allocation, HostelService $hostels): RedirectResponse
    {
        $hostels->cancel($allocation);

        return back()->with('success', 'Allocation cancelled; the bed space is free again.');
    }
}
