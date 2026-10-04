<?php

use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admissions\AdmissionListController;
use App\Http\Controllers\Admissions\ApplicantController as AdmissionsApplicantController;
use App\Http\Controllers\Applicant\ApplicationController;
use App\Http\Controllers\Bursary\FeeStructureController;
use App\Http\Controllers\Bursary\PaymentController as BursaryPaymentController;
use App\Http\Controllers\Bursary\ReportController;
use App\Http\Controllers\Lecturer\CourseController;
use App\Http\Controllers\Staff\ApprovalController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\FeeController;
use App\Http\Controllers\Student\RegistrationController;
use App\Http\Controllers\Student\ResultController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::get('registration', [RegistrationController::class, 'index'])->name('registration.index');
    Route::post('registration', [RegistrationController::class, 'store'])->name('registration.store');

    Route::get('results', [ResultController::class, 'index'])->name('results.index');

    Route::get('fees', [FeeController::class, 'index'])->name('fees.index');
    Route::post('fees/generate', [FeeController::class, 'generate'])->name('fees.generate');
    Route::post('fees/{invoice}/pay', [FeeController::class, 'pay'])->name('fees.pay');
    Route::get('fees/payments/{payment}/verify', [FeeController::class, 'verify'])->name('fees.verify');

    Route::get('print/course-form', [DocumentController::class, 'courseForm'])->name('print.course-form');
    Route::get('print/exam-card', [DocumentController::class, 'examCard'])->name('print.exam-card');
    Route::get('print/receipts/{payment}', [DocumentController::class, 'receipt'])->name('print.receipt');
});

Route::middleware(['auth', 'role:lecturer|hod|dean'])->prefix('lecturer')->name('lecturer.')->group(function () {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::post('courses/{course}/scores', [CourseController::class, 'storeScores'])->name('courses.scores');
    Route::post('courses/{course}/scores/csv', [CourseController::class, 'storeCsv'])->name('courses.scores.csv');
});

Route::middleware(['auth', 'role:hod|dean|exam-officer|registrar|super-admin'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{course}', [ApprovalController::class, 'approve'])->name('approvals.approve');
});

Route::middleware(['auth', 'role:registrar|admission-officer|super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
});

Route::middleware(['auth', 'role:bursar|registrar|super-admin'])->prefix('bursary')->name('bursary.')->group(function () {
    Route::get('fees', [FeeStructureController::class, 'index'])->name('fees.index');
    Route::post('fees/types', [FeeStructureController::class, 'storeType'])->name('fees.types.store');
    Route::post('fees/structures', [FeeStructureController::class, 'storeStructure'])->name('fees.structures.store');
    Route::delete('fees/structures/{feeStructure}', [FeeStructureController::class, 'destroyStructure'])->name('fees.structures.destroy');

    Route::get('payments', [BursaryPaymentController::class, 'index'])->name('payments.index');
    Route::post('payments', [BursaryPaymentController::class, 'store'])->name('payments.store');
    Route::patch('payments/{payment}', [BursaryPaymentController::class, 'update'])->name('payments.update');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});

// Applicant portal: open to any authenticated user without a staff/student
// role; saving an application assigns the applicant role.
Route::middleware(['auth'])->prefix('apply')->name('applicant.')->group(function () {
    Route::get('/', [ApplicationController::class, 'show'])->name('show');
    Route::post('/', [ApplicationController::class, 'store'])->name('store');
    Route::post('submit', [ApplicationController::class, 'submit'])->name('submit');
    Route::post('accept', [ApplicationController::class, 'accept'])->name('accept');
    Route::get('print/admission-letter', [ApplicationController::class, 'admissionLetter'])->name('print.letter');

    Route::post('fees/pay', [ApplicationController::class, 'payFee'])->name('fees.pay');
    Route::get('fees/payments/{payment}/verify', [ApplicationController::class, 'verifyFee'])->name('fees.verify');

    Route::post('documents', [ApplicationController::class, 'uploadDocument'])->name('documents.store');
    Route::get('documents/{document}/download', [ApplicationController::class, 'downloadDocument'])->name('documents.download');
});

Route::middleware(['auth', 'role:admission-officer|registrar|super-admin'])->prefix('admissions')->name('admissions.')->group(function () {
    Route::get('applicants', [AdmissionsApplicantController::class, 'index'])->name('applicants.index');
    Route::get('applicants/{applicant}', [AdmissionsApplicantController::class, 'show'])->name('applicants.show');
    Route::patch('applicants/{applicant}/score', [AdmissionsApplicantController::class, 'updateScore'])->name('applicants.score');
    Route::patch('applicants/{applicant}/caps', [AdmissionsApplicantController::class, 'updateCaps'])->name('applicants.caps');
    Route::patch('documents/{document}', [AdmissionsApplicantController::class, 'updateDocument'])->name('documents.update');
    Route::get('documents/{document}/download', [AdmissionsApplicantController::class, 'downloadDocument'])->name('documents.download');
    Route::post('screen', [AdmissionsApplicantController::class, 'screen'])->name('screen');

    Route::get('lists', [AdmissionListController::class, 'index'])->name('lists.index');
    Route::post('lists', [AdmissionListController::class, 'store'])->name('lists.store');
    Route::post('lists/{list}/publish', [AdmissionListController::class, 'publish'])->name('lists.publish');
    Route::post('lists/{list}/admit', [AdmissionListController::class, 'admit'])->name('lists.admit');
    Route::post('lists/{list}/matriculate', [AdmissionListController::class, 'matriculate'])->name('lists.matriculate');
});

// Gateway webhooks: signature-authenticated, CSRF-exempt (see bootstrap/app.php).
Route::post('webhooks/paystack', [PaymentWebhookController::class, 'paystack'])->name('webhooks.paystack');
Route::post('webhooks/flutterwave', [PaymentWebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');
