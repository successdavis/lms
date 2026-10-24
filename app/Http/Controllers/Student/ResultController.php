<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Records\TranscriptBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultController extends Controller
{
    /**
     * Semester-by-semester results. Students only ever see courses whose
     * results have final (senate/academic board) approval.
     */
    public function index(Request $request, TranscriptBuilder $transcripts): Response
    {
        $student = $request->user()->student()->firstOrFail();
        $record = $transcripts->build($student);

        return Inertia::render('student/results', [
            'semesters' => $record['semesters'],
            'cgpa' => $record['cgpa'],
            'classification' => $record['classification'],
        ]);
    }
}
