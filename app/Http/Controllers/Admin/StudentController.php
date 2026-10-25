<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $students = Student::query()
            ->with(['user:id,name,email', 'programme:id,name,code', 'level:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('matric_no', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/students', [
            'students' => $students,
            'filters' => ['search' => $search],
        ]);
    }
}
