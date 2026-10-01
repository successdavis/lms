<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registered_course_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('ca_score', 5, 2)->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            // Snapshots resolved from the grade scale at grading time.
            $table->string('grade_letter')->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->decimal('quality_points', 6, 2)->nullable(); // grade_point * credit_units
            $table->boolean('is_passed')->nullable();
            $table->string('status')->default('pending'); // ResultStatus
            $table->foreignId('entered_by_id')->nullable()->constrained('staff');
            $table->timestamps();
        });

        // Per-semester GPA snapshot; the source of truth for transcripts.
        Schema::create('semester_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total_units');            // TNU this semester
            $table->decimal('total_quality_points', 8, 2);          // TCP this semester
            $table->decimal('gpa', 3, 2);
            $table->unsignedSmallInteger('cumulative_units');
            $table->decimal('cumulative_quality_points', 8, 2);
            $table->decimal('cgpa', 3, 2);
            $table->string('standing')->default('good');            // AcademicStanding
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_results');
        Schema::dropIfExists('results');
    }
};
