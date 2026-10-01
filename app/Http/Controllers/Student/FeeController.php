<?php

namespace App\Http\Controllers\Student;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\Gateways\GatewayManager;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FeeController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()->student()->firstOrFail();
        $session = AcademicSession::current();

        $invoices = $student->invoices()
            ->with(['items.feeType:id,name', 'academicSession:id,name', 'payments' => fn ($q) => $q->latest()])
            ->latest()
            ->get();

        return Inertia::render('student/fees', [
            'invoices' => $invoices,
            'session' => $session?->only('id', 'name'),
            'hasCurrentInvoice' => $session
                ? $invoices->contains(fn ($invoice) => $invoice->academic_session_id === $session->id)
                : false,
        ]);
    }

    public function generate(Request $request, InvoiceGenerator $generator): RedirectResponse
    {
        $student = $request->user()->student()->firstOrFail();
        $session = AcademicSession::current();

        if ($session === null) {
            return back()->withErrors(['fees' => 'No current academic session is configured.']);
        }

        if ($student->invoices()->where('academic_session_id', $session->id)->exists()) {
            return back()->withErrors(['fees' => 'An invoice already exists for this session.']);
        }

        try {
            $generator->generateFor($student, $session);
        } catch (RuntimeException $e) {
            return back()->withErrors(['fees' => $e->getMessage()]);
        }

        return back()->with('success', 'Invoice generated.');
    }

    public function pay(Request $request, Invoice $invoice, GatewayManager $gateways): HttpResponse|RedirectResponse
    {
        $student = $request->user()->student()->firstOrFail();

        abort_unless($invoice->student_id === $student->id, 403);

        if ($invoice->status === InvoiceStatus::Paid) {
            return back()->withErrors(['fees' => 'This invoice is already fully paid.']);
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'gateway' => $gateways->defaultGateway(),
            'reference' => 'PAY-'.Str::upper(Str::random(12)),
            'amount' => $invoice->balance(),
            'status' => PaymentStatus::Pending,
        ]);

        $redirectUrl = $gateways->driver($payment->gateway)->initialize($payment);

        if ($redirectUrl !== null) {
            return Inertia::location($redirectUrl);
        }

        return back()->with('success', 'Payment successful.');
    }

    public function verify(Request $request, Payment $payment, GatewayManager $gateways): RedirectResponse
    {
        $student = $request->user()->student()->firstOrFail();

        abort_unless($payment->student_id === $student->id, 403);

        $succeeded = $gateways->driver($payment->gateway)->verify($payment);

        return redirect()
            ->route('student.fees.index')
            ->with($succeeded ? 'success' : 'error', $succeeded ? 'Payment confirmed.' : 'Payment was not successful.');
    }
}
