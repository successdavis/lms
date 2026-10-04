<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        if ($user->hasRole('student')) {
            return redirect()->route('student.dashboard');
        }

        if ($user->hasRole('applicant') || $user->getRoleNames()->isEmpty()) {
            return redirect()->route('applicant.show');
        }

        if ($user->hasAnyRole(['lecturer']) && ! $user->hasAnyRole(['hod', 'dean', 'registrar', 'super-admin'])) {
            return redirect()->route('lecturer.courses.index');
        }

        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/portal.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
