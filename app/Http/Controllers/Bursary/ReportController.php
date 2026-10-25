<?php

namespace App\Http\Controllers\Bursary;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = AcademicSession::query()->orderByDesc('name')->get(['id', 'name', 'is_current']);
        $sessionId = $request->integer('session') ?: AcademicSession::current()?->id;

        $invoiced = (float) Invoice::query()->where('academic_session_id', $sessionId)->sum('total');
        $collected = (float) Invoice::query()->where('academic_session_id', $sessionId)->sum('amount_paid');

        $byFeeType = InvoiceItem::query()
            ->whereHas('invoice', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->selectRaw('fee_type_id, sum(amount) as invoiced, count(*) as count')
            ->groupBy('fee_type_id')
            ->with('feeType:id,name')
            ->get()
            ->map(fn ($row) => [
                'fee_type' => $row->feeType->name,
                'invoiced' => (float) $row->invoiced,
                'count' => (int) $row->count,
            ]);

        $byGateway = Payment::query()
            ->where('status', PaymentStatus::Successful)
            ->whereHas('invoice', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->selectRaw('gateway, sum(amount) as collected, count(*) as count')
            ->groupBy('gateway')
            ->get()
            ->map(fn ($row) => [
                'gateway' => $row->gateway,
                'collected' => (float) $row->collected,
                'count' => (int) $row->count,
            ]);

        return Inertia::render('bursary/reports', [
            'sessions' => $sessions,
            'selectedSession' => $sessionId,
            'summary' => [
                'invoiced' => $invoiced,
                'collected' => $collected,
                'outstanding' => round($invoiced - $collected, 2),
                'collectionRate' => $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0,
                'invoiceCount' => Invoice::query()->where('academic_session_id', $sessionId)->count(),
                'fullyPaidCount' => Invoice::query()->where('academic_session_id', $sessionId)->where('status', 'paid')->count(),
            ],
            'byFeeType' => $byFeeType,
            'byGateway' => $byGateway,
        ]);
    }
}
