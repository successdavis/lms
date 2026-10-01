<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();     // CSC 201, GST 101
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('credit_units');
            $table->unsignedTinyInteger('semester_number'); // 1 or 2
            $table->unsignedTinyInteger('ca_weight')->default(30);   // CA % of total
            $table->unsignedTinyInteger('exam_weight')->default(70); // Exam % of total
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Curriculum: which courses a programme takes, at which level/semester, and how.
        Schema::create('programme_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester_number');
            $table->string('type'); // CourseType: core | required | elective | general
            $table->timestamps();

            $table->unique(['programme_id', 'course_id', 'level_id']);
        });

        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('courses')->cascadeOnDelete();

            $table->unique(['course_id', 'prerequisite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('programme_courses');
        Schema::dropIfExists('courses');
    }
};
