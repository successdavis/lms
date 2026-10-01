<?php

use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Lecturer\CourseController;
use App\Http\Controllers\Staff\ApprovalController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\FeeController;
use App\Http\Controllers\Student\RegistrationController;
use App\Http\Controllers\Student\ResultController;
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
});

Route::middleware(['auth', 'role:lecturer|hod|dean'])->prefix('lecturer')->name('lecturer.')->group(function () {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::post('courses/{course}/scores', [CourseController::class, 'storeScores'])->name('courses.scores');
});

Route::middleware(['auth', 'role:hod|dean|exam-officer|registrar|super-admin'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{course}', [ApprovalController::class, 'approve'])->name('approvals.approve');
});

Route::middleware(['auth', 'role:registrar|admission-officer|super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
});
