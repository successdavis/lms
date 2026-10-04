<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained(); // level at time of registration
            $table->string('status')->default('draft');   // RegistrationStatus
            $table->unsignedTinyInteger('total_units')->default(0);
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('staff');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id']);
        });

        Schema::create('registered_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            // Snapshot: curriculum credit units can change in later sessions.
            $table->unsignedTinyInteger('credit_units');
            $table->boolean('is_carryover')->default(false);
            $table->timestamps();

            $table->unique(['course_registration_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registered_courses');
        Schema::dropIfExists('course_registrations');
    }
};
