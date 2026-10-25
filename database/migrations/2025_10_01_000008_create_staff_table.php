<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('staff_no')->unique();
            $table->string('type');                 // StaffType
            // Graduate Assistant ... Professor | Assistant Lecturer ... Chief Lecturer
            $table->string('designation')->nullable();
            $table->date('employed_on')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('faculties', function (Blueprint $table) {
            $table->foreign('dean_staff_id')->references('id')->on('staff')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('hod_staff_id')->references('id')->on('staff')->nullOnDelete();
        });

        // Which lecturer teaches a course in a given semester (may upload scores).
        Schema::create('course_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_coordinator')->default(false);
            $table->timestamps();

            $table->unique(['course_id', 'staff_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_allocations');
        Schema::table('faculties', fn (Blueprint $table) => $table->dropForeign(['dean_staff_id']));
        Schema::table('departments', fn (Blueprint $table) => $table->dropForeign(['hod_staff_id']));
        Schema::dropIfExists('staff');
    }
};
