<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Hostel;
use App\Services\Hostels\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class HostelController extends Controller
{
    public function index(Request $request, HostelService $hostels): Response
    {
        $student = $request->user()->student()->firstOrFail();
        $session = AcademicSession::current();

        $hostels->expireLapsed();

        $available = $session
            ? Hostel::query()
                ->where('is_active', true)
                ->whereIn('gender', [$student->gender, 'mixed'])
                ->with('rooms')
                ->orderBy('name')
                ->get()
                ->map(function (Hostel $hostel) use ($session) {
                    $capacity = $hostel->rooms->sum('capacity');
                    $occupied = $hostel->rooms->sum(fn ($room) => $room->occupiedSpaces($session->id));

                    return [
                        'id' => $hostel->id,
                        'name' => $hostel->name,
                        'gender' => $hostel->gender,
                        'fee' => $hostel->fee,
                        'capacity' => $capacity,
                        'available' => max(0, $capacity - $occupied),
                    ];
                })
            : collect();

        $allocation = $session ? $hostels->activeAllocation($student, $session->id) : null;

        return Inertia::render('student/hostel', [
            'session' => $session?->only('id', 'name'),
            'hostels' => $available,
            'allocation' => $allocation ? [
                'status' => $allocation->status->value,
                'hostel' => $allocation->room->hostel->name,
                'room' => $allocation->room->number,
                'expires_at' => $allocation->expires_at?->toDayDateTimeString(),
                'invoice' => $allocation->invoice?->only('id', 'number', 'status', 'total'),
            ] : null,
        ]);
    }

    public function store(Request $request, Hostel $hostel, HostelService $hostels): RedirectResponse
    {
        $student = $request->user()->student()->firstOrFail();

        try {
            $allocation = $hostels->book($student, $hostel);
        } catch (RuntimeException $e) {
            return back()->withErrors(['hostel' => $e->getMessage()]);
        }

        return back()->with('success', "Room {$allocation->room->number} reserved. Pay the hostel invoice within "
            .config('lms.hostel.reservation_hours').' hours to confirm it.');
    }
}
