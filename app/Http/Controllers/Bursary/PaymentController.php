<?php

namespace App\Http\Controllers\Bursary;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InvoiceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Payment reconciliation: every gateway/bank payment with filters, plus
     * manual actions for teller payments that never hit a webhook.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'gateway' => $request->string('gateway')->toString(),
            'search' => $request->string('search')->trim()->toString(),
        ];

        $payments = Payment::query()
            ->with(['student.user:id,name', 'invoice:id,number'])
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['gateway'], fn ($q, $gateway) => $q->where('gateway', $gateway))
            ->when($filters['search'], function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('rrr', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($s) => $s->where('matric_no', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('bursary/payments', [
            'payments' => $payments,
            'filters' => $filters,
            'totals' => [
                'successful' => Payment::where('status', PaymentStatus::Successful)->sum('amount'),
                'pending' => Payment::where('status', PaymentStatus::Pending)->sum('amount'),
            ],
        ]);
    }

    /**
     * Record an offline bank-teller/transfer payment against an invoice.
     */
    public function store(Request $request, InvoiceGenerator $invoices): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'exists:invoices,number'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:100', 'unique:payments,reference'],
        ]);

        $invoice = Invoice::query()->where('number', $validated['invoice_number'])->firstOrFail();

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'gateway' => PaymentGateway::Bank,
            'reference' => $validated['reference'],
            'amount' => $validated['amount'],
            'status' => PaymentStatus::Successful,
            'channel' => 'bank_teller',
            'paid_at' => now(),
        ]);

        $invoices->applyPayment($payment);

        return back()->with('success', "Bank payment recorded against {$invoice->number}.");
    }

    /**
     * Resolve a pending payment by hand: confirm it (teller slip sighted) or
     * mark it failed. Gateway payments should normally settle via webhooks.
     */
    public function update(Request $request, Payment $payment, InvoiceGenerator $invoices): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'fail'])],
        ]);

        if ($payment->status !== PaymentStatus::Pending) {
            return back()->withErrors(['payment' => 'Only pending payments can be reconciled.']);
        }

        if ($validated['action'] === 'confirm') {
            $payment->update(['status' => PaymentStatus::Successful, 'paid_at' => now()]);
            $invoices->applyPayment($payment);

            return back()->with('success', 'Payment confirmed and applied.');
        }

        $payment->update(['status' => PaymentStatus::Failed]);

        return back()->with('success', 'Payment marked as failed.');
    }
}
