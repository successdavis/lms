<?php

namespace App\Http\Controllers\Student;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printable documents: course registration form, examination card, payment
 * receipt. Each renders a print-styled page; the exam card is gated on full
 * payment of registration-blocking fees plus a submitted registration.
 */
class DocumentController extends Controller
{
    public function courseForm(Request $request): Response
    {
        $student = $this->student($request);
        $semester = Semester::current();

        abort_if($semester === null, 404, 'No current semester.');

        $registration = $student->courseRegistrations()
            ->where('semester_id', $semester->id)
            ->with('registeredCourses.course:id,code,title')
            ->first();

        abort_if($registration === null, 404, 'No course registration for the current semester.');

        return Inertia::render('student/print/course-form', [
            'institution' => Institution::current()?->only('name', 'short_name', 'motto', 'address'),
            'student' => $this->bio($student),
            'session' => $semester->academicSession->name,
            'semester' => $semester->name,
            'registration' => [
                'status' => $registration->status,
                'total_units' => $registration->total_units,
                'courses' => $registration->registeredCourses->map(fn ($rc) => [
                    'code' => $rc->course->code,
                    'title' => $rc->course->title,
                    'credit_units' => $rc->credit_units,
                    'is_carryover' => $rc->is_carryover,
                ]),
            ],
        ]);
    }

    public function examCard(Request $request): Response
    {
        $student = $this->student($request);
        $semester = Semester::current();

        abort_if($semester === null, 404, 'No current semester.');

        $registration = $student->courseRegistrations()
            ->where('semester_id', $semester->id)
            ->with('registeredCourses.course:id,code,title')
            ->first();

        abort_if($registration === null, 403, 'Register your courses before printing an exam card.');

        // Exam clearance demands FULL payment of registration-blocking fees,
        // regardless of any installment allowance used for registration.
        $unpaid = $student->invoices()
            ->where('academic_session_id', $semester->academic_session_id)
            ->whereHas('items.feeType', fn ($q) => $q->where('blocks_registration', true))
            ->get()
            ->filter(fn ($invoice) => $invoice->percentPaid() < 100);

        abort_if($unpaid->isNotEmpty(), 403, 'Exam card requires full payment of school fees.');

        return Inertia::render('student/print/exam-card', [
            'institution' => Institution::current()?->only('name', 'short_name', 'motto'),
            'student' => $this->bio($student),
            'session' => $semester->academicSession->name,
            'semester' => $semester->name,
            'courses' => $registration->registeredCourses->map(fn ($rc) => [
                'code' => $rc->course->code,
                'title' => $rc->course->title,
                'credit_units' => $rc->credit_units,
            ]),
        ]);
    }

    public function receipt(Request $request, Payment $payment): Response
    {
        $student = $this->student($request);

        abort_unless($payment->student_id === $student->id, 403);
        abort_unless($payment->status === PaymentStatus::Successful, 404, 'Receipt available for successful payments only.');

        $payment->load('invoice.items.feeType', 'invoice.academicSession');

        return Inertia::render('student/print/receipt', [
            'institution' => Institution::current()?->only('name', 'short_name', 'address'),
            'student' => $this->bio($student),
            'payment' => [
                'reference' => $payment->reference,
                'rrr' => $payment->rrr,
                'amount' => $payment->amount,
                'gateway' => $payment->gateway,
                'channel' => $payment->channel,
                'paid_at' => $payment->paid_at?->toDayDateTimeString(),
            ],
            'invoice' => [
                'number' => $payment->invoice->number,
                'session' => $payment->invoice->academicSession->name,
                'total' => $payment->invoice->total,
                'amount_paid' => $payment->invoice->amount_paid,
                'status' => $payment->invoice->status,
                'items' => $payment->invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'amount' => $item->amount,
                ]),
            ],
        ]);
    }

    private function student(Request $request): Student
    {
        return $request->user()->student()
            ->with(['programme.department.faculty', 'level'])
            ->firstOrFail();
    }

    private function bio(Student $student): array
    {
        return [
            'name' => $student->user->name,
            'matric_no' => $student->matric_no,
            'programme' => $student->programme->name,
            'department' => $student->programme->department->name,
            'faculty' => $student->programme->department->faculty->name,
            'level' => $student->level->name,
        ];
    }
}
